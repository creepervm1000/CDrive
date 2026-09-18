<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Support;

use OCA\Support\Sections\AppApiSection;
use OCA\Support\Sections\LdapSection;
use OCA\Support\Sections\PhpInfoSection;
use OCA\Support\Sections\ServerSection;
use OCA\Support\Sections\SetupChecksSection;
use OCA\Support\Sections\SharingSection;
use OCA\Support\Sections\TalkSection;

class DetailManager {
	private array $sections = [];

	public function __construct(
		ServerSection $serverSection,
		SetupChecksSection $setupChecksSection,
		SharingSection $sharingSection,
		PhpInfoSection $phpInfoSection,
		TalkSection $talkSection,
		LdapSection $ldapSection,
		AppApiSection $appApiSection,
	) {
		// Register core details that are used in every report
		$this->addSection($serverSection);
		$this->addSection($sharingSection);
		$this->addSection($setupChecksSection);
		if ($talkSection->isTalkEnabled()) {
			$this->addSection($talkSection);
		}
		if ($ldapSection->isLdapEnabled()) {
			$this->addSection($ldapSection);
		}
		if ($appApiSection->isAppApiEnabled()) {
			$this->addSection($appApiSection);
		}
		$this->addSection($phpInfoSection);
	}

	public function createSection(string $identifier, string $title, int $order = 0): void {
		$section = new Section($identifier, $title, $order);
		$this->addSection($section);
	}

	public function addSection(ISection $section): void {
		if (array_key_exists($section->getIdentifier(), $this->sections)) {
			/** @var ISection $existing */
			$existing = $this->sections[$section->getIdentifier()];
			foreach ($section->getDetails() as $detail) {
				$existing->addDetail($detail);
			}
			return;
		}
		$this->sections[$section->getIdentifier()] = $section;
	}

	public function removeSection(string $section): void {
		unset($this->sections[$section]);
	}

	public function createDetail(string $sectionIdentifier, string $title, string $information, int $type = IDetail::TYPE_MULTI_LINE_PREFORMAT): void {
		$detail = new Detail($sectionIdentifier, $title, $information, $type);
		/** @var ISection $sectionObject */
		$sectionObject = $this->sections[$sectionIdentifier];
		$sectionObject->addDetail($detail);
	}

	/**
	 * @return ISection[]
	 */
	public function getSections(): array {
		return $this->sections;
	}

	public function getRenderedDetails(): string {
		$result = '';
		/** @var ISection $section */
		foreach ($this->sections as $section) {
			$result .= $this->renderSectionHeader($section);
			/** @var IDetail $detail */
			foreach ($section->getDetails() as $detail) {
				$result .= $this->renderDetail($detail);
			}
		}
		return $result;
	}

	private function renderSectionHeader(ISection $section): string {
		return '## ' . $section->getTitle() . "\n\n";
	}

	private function renderDetail(IDetail $detail): string {
		switch ($detail->getType()) {
			case IDetail::TYPE_SINGLE_LINE:
				return '**' . $detail->getTitle() . ':** ' . $detail->getInformation() . "\n\n";
			case IDetail::TYPE_MULTI_LINE:
				return '**' . $detail->getTitle() . ":** \n\n" . $detail->getInformation() . "\n\n";
			case IDetail::TYPE_MULTI_LINE_PREFORMAT:
				return '**' . $detail->getTitle() . ":** \n\n``` \n" . $detail->getInformation() . "\n```\n\n";
			case IDetail::TYPE_COLLAPSIBLE:
				return '<details><summary>' . $detail->getTitle() . "</summary>\n\n" . $detail->getInformation() . "\n</details>\n\n";
			case IDetail::TYPE_COLLAPSIBLE_PREFORMAT:
				return '<details><summary>' . $detail->getTitle() . "</summary>\n\n```\n" . $detail->getInformation() . "\n```\n</details>\n\n";
			default:
				return '**' . $detail->getTitle() . ':** ' . $detail->getInformation() . "\n\n";
		}
	}
}
