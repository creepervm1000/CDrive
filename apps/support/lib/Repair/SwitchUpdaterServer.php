<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2026 CDrive contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * CDrive fork: subscription-based updater switching removed. Repair step
 * intentionally does nothing (kept so migrations stay consistent).
 */

namespace OCA\Support\Repair;

use OCP\IConfig;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use OCP\Support\Subscription\IRegistry;

class SwitchUpdaterServer implements IRepairStep {
	public function __construct(
		protected readonly IConfig $config,
		protected readonly IRegistry $subscriptionRegistry,
	) {
	}

	#[\Override]
	public function getName(): string {
		return 'CDrive: updater server switching disabled (no subscriptions)';
	}

	#[\Override]
	public function run(IOutput $output): void {
		// CDrive: never switch to a customer updater server.
		$this->config->setAppValue('support', 'SwitchUpdaterServerHasRun', 'yes');
	}
}
