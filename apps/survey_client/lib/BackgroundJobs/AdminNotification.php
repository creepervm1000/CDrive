<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2016-2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2016 ownCloud, Inc.
 * SPDX-FileCopyrightText: 2026 CDrive contributors
 * SPDX-License-Identifier: AGPL-3.0-only
 *
 * CDrive fork: survey nag notifications disabled.
 */

namespace OCA\Survey_Client\BackgroundJobs;

use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\QueuedJob;
use OCP\IGroupManager;
use OCP\Notification\IManager;

class AdminNotification extends QueuedJob {
	public function __construct(
		ITimeFactory $time,
		protected IManager $manager,
		protected IGroupManager $groupManager,
	) {
		parent::__construct($time);
	}

	#[\Override]
	protected function run($argument): void {
		// CDrive: survey nag notifications disabled. Admins are never
		// prompted about the (disabled) usage survey.
		return;
	}
}
