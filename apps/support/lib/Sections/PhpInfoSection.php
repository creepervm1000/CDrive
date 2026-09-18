<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Support\Sections;

use DOMDocument;
use DOMXPath;
use OCA\Support\IDetail;
use OCA\Support\Section;

class PhpInfoSection extends Section {
	public function __construct(
	) {
		parent::__construct('phpinfo', 'Phpinfo');
	}

	#[\Override]
	public function getDetails(): array {
		if (!function_exists('phpinfo')) {
			$this->createDetail('error', 'phpinfo function is disabled.', IDetail::TYPE_SINGLE_LINE);
			return parent::getDetails();
		}
		ob_start();
		phpinfo(INFO_CONFIGURATION | INFO_MODULES);
		$phpinfo = ob_get_clean();

		if ($phpinfo === false) {
			$this->createDetail('error', 'Failed to retrieve phpinfo output.', IDetail::TYPE_SINGLE_LINE);
			return parent::getDetails();
		}

		if (str_contains($phpinfo, '<!DOCTYPE html') === false) {
			// If phpinfo output is not HTML, we dump the raw output
			$this->createDetail('phpinfo', $phpinfo, IDetail::TYPE_COLLAPSIBLE_PREFORMAT);
			return parent::getDetails();
		}

		$parsedInfo = array_values($this->parsePhpInfoFromHtml($phpinfo))[0] ?? [];
		foreach ($parsedInfo as $sectionName => $sectionData) {
			$this->createDetail($sectionName, json_encode($sectionData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), IDetail::TYPE_COLLAPSIBLE_PREFORMAT);
		}

		return parent::getDetails();
	}

	private function parsePhpInfoFromHtml(string $phpInfoHtmlStr): array {
		$dom = new DOMDocument();
		libxml_use_internal_errors(true);
		$dom->loadHTML($phpInfoHtmlStr, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
		libxml_clear_errors();

		$xpath = new DOMXPath($dom);

		$result = [];
		$currentSection = '';
		$currentSubsection = '';

		$elements = $xpath->query('//h1 | //h2 | //table');

		foreach ($elements as $el) {
			if ($el->nodeName === 'h1') {
				$currentSection = trim($el->textContent);
				$result[$currentSection] = [];
			} elseif ($el->nodeName === 'h2') {
				$currentSubsection = trim($el->textContent);
				if (!isset($result[$currentSection][$currentSubsection])) {
					$result[$currentSection][$currentSubsection] = [];
				}
			} elseif ($el->nodeName === 'table') {
				$rows = $el->getElementsByTagName('tr');

				foreach ($rows as $row) {
					$cols = $row->getElementsByTagName('td');
					$ths = $row->getElementsByTagName('th');

					if ($cols->length === 2) {
						// Single key => value
						$key = trim($cols->item(0)->textContent);
						$val = trim($cols->item(1)->textContent);
						$result[$currentSection][$currentSubsection][$key] = $val;
					} elseif ($cols->length === 3) {
						// Directive with local/master value
						$key = trim($cols->item(0)->textContent);
						$local = trim($cols->item(1)->textContent);
						$master = trim($cols->item(2)->textContent);
						$result[$currentSection][$currentSubsection][$key] = [
							'local' => $local,
							'master' => $master,
						];
					} elseif ($ths->length > 0) {
						// This is a header row; skip or save metadata
						continue;
					}
				}
			}
		}

		return $result;
	}

}
