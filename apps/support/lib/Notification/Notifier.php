<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2026 CDrive contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * CDrive fork: subscription notifications removed; all subjects unknown.
 */

namespace OCA\Support\Notification;

use OCA\Support\AppInfo\Application;
use OCP\IConfig;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use OCP\Notification\IManager;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;
use OCP\Notification\UnknownNotificationException;

class Notifier implements INotifier {
	public function __construct(
		protected readonly IURLGenerator $url,
		protected readonly IConfig $config,
		protected readonly IManager $notificationManager,
		protected readonly IFactory $l10nFactory,
	) {
	}

	#[\Override]
	public function getID(): string {
		return 'support';
	}

	#[\Override]
	public function getName(): string {
		return $this->l10nFactory->get(Application::APP_ID)->t('Subscription notifications');
	}

	/**
	 * @param INotification $notification
	 * @param string $languageCode The code of the language that should be used to prepare the notification
	 * @return INotification
	 * @throws UnknownNotificationException When the notification was not prepared by a notifier
	 * @since 9.0.0
	 */
	#[\Override]
	public function prepare(INotification $notification, string $languageCode): INotification {
		if ($notification->getApp() !== 'support') {
			throw new UnknownNotificationException();
		}

		// CDrive: subscription notifications no longer exist. Any lingering
		// subscription notification is treated as unknown so no ghost
		// upsell text is ever rendered.
		throw new UnknownNotificationException();
	}
}
