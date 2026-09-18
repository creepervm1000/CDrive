<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2026 CDrive contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * CDrive fork: disabled. Unregistered in Application.php; run() always
 * succeeds in case it is ever invoked directly.
 */

namespace OCA\Notifications\Settings;

use OCP\AppFramework\Services\IAppConfig;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IL10N;
use OCP\SetupCheck\ISetupCheck;
use OCP\SetupCheck\SetupResult;

class SetupWarningOnRateLimitReached implements ISetupCheck {
	public function __construct(
		private readonly IAppConfig $appConfig,
		private readonly ITimeFactory $timeFactory,
		private readonly IL10N $l,
	) {
	}

	#[\Override]
	public function getCategory(): string {
		return 'notifications';
	}

	#[\Override]
	public function getName(): string {
		return $this->l->t('Push notifications');
	}

	#[\Override]
	public function run(): SetupResult {
		// CDrive: no fair-use policy, no rate-limit warning. Always pass.
		return SetupResult::success();
	}
}
