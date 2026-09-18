<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Support\Settings;

use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Settings\IIconSection;

class Section implements IIconSection {
	public function __construct(
		protected readonly IL10N $l,
		protected readonly IURLGenerator $url,
	) {
	}

	#[\Override]
	public function getID(): string {
		return 'support';
	}

	#[\Override]
	public function getName(): string {
		return $this->l->t('Support');
	}

	#[\Override]
	public function getPriority(): int {
		return 1;
	}

	#[\Override]
	public function getIcon(): string {
		return $this->url->imagePath('support', 'section.svg');
	}
}
