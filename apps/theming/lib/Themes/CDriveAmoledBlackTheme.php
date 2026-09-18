<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * SPDX-FileCopyrightText: 2026 CDrive contributors
 * CDrive fork modifications (see below).
 *
 * CDrive AMOLED Black theme. Pure #000000 backgrounds, NO WHITE surfaces.
 * Original Nextcloud copyright preserved per AGPLv3 sec. 4-5.
 * Modified version marked per AGPLv3 sec. 5(a): CDrive fork, 2026-09-18.
 */

namespace OCA\Theming\Themes;

use OCA\Theming\ITheme;

class CDriveAmoledBlackTheme extends DarkTheme implements ITheme {

	#[\Override]
	public function getId(): string {
		return 'cdrive-amoled-black';
	}

	#[\Override]
	public function getTitle(): string {
		return $this->l->t('CDrive AMOLED Black');
	}

	#[\Override]
	public function getEnableLabel(): string {
		return $this->l->t('Enable CDrive AMOLED Black');
	}

	#[\Override]
	public function getDescription(): string {
		return $this->l->t('Pure AMOLED black (#000000). No white surfaces. Neutral gray accent for maximum battery saving.');
	}

	#[\Override]
	public function getMediaQuery(): string {
		return '';
	}

	#[\Override]
	public function getMeta(): array {
		return [[
			'name' => 'color-scheme',
			'content' => 'dark',
		]];
	}

	#[\Override]
	public function getCSSVariables(): array {
		// Neutral gray accent, readable on pure black
		$this->primaryColor = '#BDBDBD';
		$variables = parent::getCSSVariables();

		// Force pure AMOLED black everywhere - override DarkTheme #171717
		$variables['--color-main-background'] = '#000000';
		$variables['--color-main-background-rgb'] = '0,0,0';
		$variables['--color-main-background-translucent'] = 'rgba(0,0,0, .97)';
		$variables['--color-main-background-blur'] = 'rgba(0,0,0, .85)';
		$variables['--color-main-text'] = '#EBEBEB';
		$variables['--image-background'] = 'none';
		$variables['--color-background-plain'] = '#000000';
		$variables['--color-background-plain-text'] = '#EBEBEB';

		return $variables;
	}

	#[\Override]
	public function getCustomCss(): string {
		return "
			body, #content, #app-content, #app-navigation, #body-login {
				background-color: #000000 !important;
				background-image: none !important;
			}
		";
	}
}
