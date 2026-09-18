<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 CDrive contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Cdrive\Settings;

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
		return 'cdrive';
	}

	#[\Override]
	public function getName(): string {
		return $this->l->t('CDrive');
	}

	#[\Override]
	public function getPriority(): int {
		return 2;
	}

	#[\Override]
	public function getIcon(): string {
		return $this->url->imagePath('cdrive', 'section.svg');
	}
}
