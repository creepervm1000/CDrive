<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2026 CDrive contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * CDrive fork: push paywall removed; check always passes.
 */

namespace OCA\Settings\SetupChecks;

use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IConfig;
use OCP\IL10N;
use OCP\Notification\IManager;
use OCP\SetupCheck\ISetupCheck;
use OCP\SetupCheck\SetupResult;
use OCP\Support\Subscription\IRegistry;

class PushService implements ISetupCheck {
	public function __construct(
		private IL10N $l10n,
		private IConfig $config,
		private IManager $notificationsManager,
		private IRegistry $subscriptionRegistry,
		private ITimeFactory $timeFactory,
	) {
	}

	#[\Override]
	public function getName(): string {
		return $this->l10n->t('Push service');
	}

	#[\Override]
	public function getCategory(): string {
		return 'system';
	}

	#[\Override]
	public function run(): SetupResult {
		// CDrive: push is free with no tiers or limits.
		return SetupResult::success($this->l10n->t('Push service (no limits)'));
	}
}
