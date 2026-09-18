<?php

/**
 * SPDX-FileCopyrightText: 2016-2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2016 ownCloud, Inc.
 * SPDX-FileCopyrightText: 2026 CDrive contributors
 * SPDX-License-Identifier: AGPL-3.0-only
 *
 * This file is part of the CDrive fork of Nextcloud server.
 * Nextcloud server is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, version 3 of the License.
 * Original Nextcloud copyright notices are preserved above per AGPLv3 sec. 4-5.
 * Modified version prominently marked per AGPLv3 sec. 5(a): renamed to CDrive, 2026-09-18.
 */

class OC_Theme {

	/**
	 * Returns the base URL
	 * @return string URL
	 */
	public function getBaseUrl(): string {
		return 'https://cdrive.example';
	}

	/**
	 * Returns the documentation URL
	 * @return string URL
	 */
	public function getDocBaseUrl(): string {
		return 'https://cdrive.example/docs';
	}

	/**
	 * Returns the title
	 * @return string title
	 */
	public function getTitle(): string {
		return 'CDrive';
	}

	/**
	 * Returns the short name of the software
	 * @return string title
	 */
	public function getName(): string {
		return 'CDrive';
	}

	/**
	 * Returns the short name of the software containing HTML strings
	 * @return string title
	 */
	public function getHTMLName(): string {
		return 'CDrive';
	}

	/**
	 * Returns entity (e.g. company name) - used for footer, copyright
	 * @return string entity name
	 */
	public function getEntity(): string {
		return 'CDrive';
	}

	/**
	 * Returns slogan
	 * @return string slogan
	 */
	public function getSlogan(): string {
		return 'Your AMOLED cloud, personalized for you!';
	}

	/**
	 * Returns short version of the footer
	 * @return string short footer
	 */
	public function getShortFooter(): string {
		$entity = $this->getEntity();

		$footer = '© ' . date('Y');

		// Add link if entity name is not empty
		if ($entity !== '') {
			$footer .= ' <a href="' . $this->getBaseUrl() . '" target="_blank">' . $entity . '</a>' . '<br/>';
		}

		$footer .= $this->getSlogan();

		return $footer;
	}

	/**
	 * Returns long version of the footer
	 * @return string long footer
	 */
	public function getLongFooter(): string {
		$footer = '© ' . date('Y') . ' <a href="' . $this->getBaseUrl() . '" target="_blank">' . $this->getEntity() . '</a>'
			. '<br/>' . $this->getSlogan();

		return $footer;
	}

	/**
	 * Generate a documentation link for a given key
	 * @return string documentation link
	 */
	public function buildDocLinkToKey($key): string {
		return $this->getDocBaseUrl() . '/go.php?to=' . $key;
	}

	/**
	 * Returns mail header color - CDrive AMOLED green
	 * @return string
	 */
	public function getColorPrimary(): string {
		return '#00C853';
	}

	/**
	 * Returns background color to be used - pure AMOLED black, NO WHITE
	 * @return string
	 */
	public function getColorBackground(): string {
		return '#000000';
	}

	/**
	 * Returns variables to overload defaults from core/css/variables.scss
	 * @return array
	 */
	public function getScssVariables(): array {
		return [
			'color-primary' => '#00C853'
		];
	}
}
