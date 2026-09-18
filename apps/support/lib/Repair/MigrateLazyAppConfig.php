<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Support\Repair;

use OCP\IAppConfig;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class MigrateLazyAppConfig implements IRepairStep {
	public function __construct(
		protected readonly IAppConfig $appConfig,
	) {
	}

	#[\Override]
	public function getName(): string {
		return 'Migrate some config values to lazy loading';
	}

	#[\Override]
	public function run(IOutput $output): void {
		$this->appConfig->updateLazy('support', 'last_response', true);

		// Copy often used values to non-lazy (also done when fetching)
		$data = $this->appConfig->getValueArray('support', 'last_response');
		if (!empty($data)) {
			$this->appConfig->setValueString('support', 'end_date', $data['endDate'] ?? '');
			$this->appConfig->setValueBool('support', 'extended_support', $data['extendedSupport'] ?? false);
		}

		// if more config values needs to be switched to lazy, just add them here
	}
}
