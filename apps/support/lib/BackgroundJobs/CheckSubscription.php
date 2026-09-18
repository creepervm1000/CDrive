<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2026 CDrive contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * CDrive fork: subscription checks removed; job intentionally does nothing.
 */

namespace OCA\Support\BackgroundJobs;

use OCA\Support\Service\SubscriptionService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\IAppConfig;

class CheckSubscription extends TimedJob {
	public function __construct(
		ITimeFactory $factory,
		private readonly IAppConfig $appConfig,
		private readonly SubscriptionService $subscriptionService,
	) {
		parent::__construct($factory);
		// Run every 5 minutes
		$this->setInterval(60 * 5);
	}

	#[\Override]
	public function run($argument) {
		// CDrive: subscription system removed. Nothing to renew or check.
		return;
	}
}
