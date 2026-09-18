<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Support\Sections;

use OCA\Support\IDetail;
use OCA\Support\Section;
use OCP\RichObjectStrings\IRichTextFormatter;
use OCP\SetupCheck\ISetupCheckManager;
use OCP\SetupCheck\SetupResult;

class SetupChecksSection extends Section {
	public function __construct(
		private ISetupCheckManager $setupCheckManager,
		private IRichTextFormatter $richTextFormatter,
	) {
		parent::__construct('setupchecks', 'Setup checks');
	}

	#[\Override]
	public function getDetails(): array {
		// FIXME Make sure to use the cached version once we have it
		$results = $this->setupCheckManager->runAll();
		foreach ($results as $category => $content) {
			if ($category === 'accounts') {
				/* Do not include accounts section in the report */
				continue;
			}
			$problems = '';
			foreach ($content as $class => $result) {
				if ($result->getSeverity() != SetupResult::SUCCESS) {
					$description = $result->getDescription();
					$descriptionParameters = $result->getDescriptionParameters();
					if ($description !== null && $descriptionParameters !== null) {
						$description = $this->richTextFormatter->richToParsed($description, $descriptionParameters);
					}
					$descriptionLines = explode("\n", $description);
					$problems .= ' * ' . $result->getName() . ': ' . implode("\n   ", $descriptionLines) . "\n";
				}
			}
			if ($problems !== '') {
				$this->createDetail($category, $problems, IDetail::TYPE_COLLAPSIBLE);
			}
		}
		return parent::getDetails();
	}
}
