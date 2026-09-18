<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Support;

use OCA\Support\Subscription\SubscriptionAdapter;
use OCP\Capabilities\ICapability;
use OCP\IConfig;

class Capabilities implements ICapability {
	public function __construct(
		protected readonly SubscriptionAdapter $adapter,
		protected readonly IConfig $config,
	) {
	}

	/**
	 * @return array{
	 *   support?: array{
	 *     hasValidSubscription: bool,
	 *     desktopEnterpriseChannel: string
	 *   },
	 * }
	 */
	#[\Override]
	public function getCapabilities(): array {
		if (!$this->adapter->hasValidSubscription()) {
			return [];
		}

		return [
			'support' => [
				'hasValidSubscription' => true,
				'desktopEnterpriseChannel' => $this->config->getSystemValueString('desktopEnterpriseChannel', 'enterprise'),
			],
		];
	}
}
