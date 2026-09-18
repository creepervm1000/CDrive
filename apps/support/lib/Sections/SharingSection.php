<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Support\Sections;

use OCA\Support\IDetail;
use OCA\Support\Section;
use OCP\Exceptions\AppConfigUnknownKeyException;
use OCP\IAppConfig;
use OCP\IConfig;

class SharingSection extends Section {
	public function __construct(
		protected readonly IAppConfig $appConfig,
	) {
		parent::__construct('sharing-detail', 'Sharing configuration');
	}

	#[\Override]
	public function getDetails(): array {
		$this->createDetail('Privacy settings for sharing', $this->getPrivacySettings(), IDetail::TYPE_COLLAPSIBLE_PREFORMAT);

		return parent::getDetails();
	}

	private function getPrivacySettings(): string {
		$keys = [
			['core', 'shareapi_allow_share_dialog_user_enumeration'],
			['core', 'shareapi_restrict_user_enumeration_to_group'],
			['core', 'shareapi_restrict_user_enumeration_to_phone'],
			['core', 'shareapi_restrict_user_enumeration_full_match'],
			['core', 'shareapi_restrict_user_enumeration_full_match_user_id'],
			['core', 'shareapi_restrict_user_enumeration_full_match_displayname'],
			['core', 'shareapi_restrict_user_enumeration_full_match_email'],
			['core', 'shareapi_restrict_user_enumeration_full_match_ignore_second_dn'],
		];

		$rows = [];
		foreach ($keys as [$appId, $key]) {
			$value = 'not set';

			try {
				$details = $this->appConfig->getDetails($appId, $key);
				if ($details['sensitive']) {
					$value = IConfig::SENSITIVE_VALUE;
				} else {
					$value = $details['value'];
				}
			} catch (AppConfigUnknownKeyException) {
				// okay
			}

			$rows[] = [
				$key,
				$value,
			];
		}

		return $this->renderTable(['Key', 'Value'], $rows);
	}
}
