<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2016-2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2016 ownCloud, Inc.
 * SPDX-FileCopyrightText: 2026 CDrive contributors
 * SPDX-License-Identifier: AGPL-3.0-only
 *
 * CDrive fork: usage survey disabled. This job intentionally does nothing;
 * no statistics ever leave the server.
 */

namespace OCA\Survey_Client\BackgroundJobs;

use OCA\Survey_Client\Collector;
use OCP\AppFramework\Http;
use OCP\AppFramework\Services\IAppConfig;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJob;
use OCP\BackgroundJob\IJobList;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;

class MonthlyReport extends TimedJob {
	public function __construct(
		ITimeFactory $time,
		protected Collector $collector,
		protected LoggerInterface $logger,
		protected IJobList $jobList,
		protected IAppConfig $appConfig,
	) {
		parent::__construct($time);

		// Run all 28 days
		$this->setInterval(28 * 24 * 60 * 60);
		// keeping time sensitive to not overload the target server at a single specific time of the day
		$this->setTimeSensitivity(IJob::TIME_SENSITIVE);
	}

	#[\Override]
	protected function run($argument) {
		// CDrive: usage survey disabled. Never collect or send statistics.
		return;
	}
}
