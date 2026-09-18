<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 CDrive contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * CDrive monochrome app icons: supplementary theme that forces all app
 * menu icons to black & white (grayscale), matching the AMOLED themes.
 */

namespace OCA\Theming\Themes;

use OCA\Theming\ITheme;
use OCP\IL10N;

class CDriveMonoIconsTheme implements ITheme {

	public function __construct(
		private IL10N $l,
	) {
	}

	#[\Override]
	public function getId(): string {
		return 'cdrive-mono-icons';
	}

	#[\Override]
	public function getType(): int {
		return ITheme::TYPE_SUPPLEMENTARY;
	}

	#[\Override]
	public function getTitle(): string {
		return $this->l->t('CDrive monochrome app icons');
	}

	#[\Override]
	public function getEnableLabel(): string {
		return $this->l->t('Show app icons in black & white');
	}

	#[\Override]
	public function getDescription(): string {
		return $this->l->t('Forces all app menu icons to black & white (grayscale).');
	}

	#[\Override]
	public function getMeta(): array {
		return [];
	}

	#[\Override]
	public function getMediaQuery(): string {
		return '';
	}

	#[\Override]
	public function getCSSVariables(): array {
		return [];
	}

	#[\Override]
	public function getCustomCss(): string {
		return "
			#header-start__appmenu img, #header-start__appmenu svg,
			#appmenu img, #appmenu svg,
			.app-menu-entry img, .app-menu-entry svg,
			.app-menu-entry__link img, .app-menu-entry__link svg,
			#navigation img, #navigation svg {
				filter: grayscale(1) !important;
			}
		";
	}
}
