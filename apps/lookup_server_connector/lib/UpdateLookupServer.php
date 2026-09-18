<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2026 CDrive contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * CDrive fork: lookup server opt-in. No lookup URL is configured by
 * default, so no user data is ever sent to a lookup server unless the
 * admin explicitly sets one.
 */

namespace OCA\LookupServerConnector;

use OCA\LookupServerConnector\BackgroundJobs\RetryJob;
use OCP\BackgroundJob\IJobList;
use OCP\Config\IUserConfig;
use OCP\GlobalScale\IConfig as GlobalScaleConfig;
use OCP\IConfig;
use OCP\IUser;

/**
 * Class UpdateLookupServer
 *
 * @package OCA\LookupServerConnector
 */
class UpdateLookupServer {
	public function __construct(
		private readonly IJobList $jobList,
		private readonly IConfig $config,
		private readonly IUserConfig $userConfig,
		private readonly GlobalScaleConfig $globalScaleConfig,
	) {
	}

	public function userUpdated(IUser $user): void {
		if (!$this->shouldUpdateLookupServer()) {
			return;
		}

		// Reset retry counter
		$this->userConfig->deleteUserConfig(
			$user->getUID(),
			'lookup_server_connector',
			'update_retries'
		);
		$this->jobList->add(RetryJob::class, ['userId' => $user->getUID()]);
	}

	/**
	 * Check if we should update the lookup server, we only do it if
	 *
	 * + we have an internet connection
	 * + the lookup server update was not disabled by the admin
	 * + we have a valid lookup server URL
	 */
	private function shouldUpdateLookupServer(): bool {
		// TODO: Consider reenable for non-global-scale setups by checking "'files_sharing', 'lookupServerUploadEnabled'" instead of "gs.enabled"
		// CDrive: default lookup server is empty (opt-in). Only sync when
		// the admin explicitly configured a lookup server URL.
		return $this->globalScaleConfig->isGlobalScaleEnabled()
			&& $this->config->getSystemValueBool('has_internet_connection', true)
			&& $this->config->getSystemValueString('lookup_server', '') !== '';
	}
}
