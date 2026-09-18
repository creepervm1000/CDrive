<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2026 CDrive contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * CDrive fork: paywalls removed. No hard user limit; all apps are
 * supported without a subscription. Original notices preserved per AGPLv3.
 */

namespace OCA\Support\Subscription;

use OCA\Support\Service\SubscriptionService;
use OCP\AppFramework\Services\IAppConfig;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IConfig;
use OCP\ServerVersion;
use OCP\Support\Subscription\ISubscription;
use OCP\Support\Subscription\ISupportedApps;

class SubscriptionAdapter implements ISubscription, ISupportedApps {
	public function __construct(
		private readonly SubscriptionService $subscriptionService,
		private readonly IConfig $config,
		private readonly IAppConfig $appConfig,
		private readonly ITimeFactory $timeFactory,
		private readonly ServerVersion $serverVersion,
	) {
	}

	/**
	 * Indicates if a valid subscription is available
	 */
	#[\Override]
	public function hasValidSubscription(): bool {
		try {
			$endDate = $this->appConfig->getAppValueString('end_date');
		} catch (\Throwable) {
			return false;
		}

		return $this->subscriptionNotExpired($endDate);
	}

	private function subscriptionNotExpired(string $endDate): bool {
		if ($endDate === '' || $endDate === 'now') {
			return false;
		}

		$subscriptionEndDate = $this->timeFactory->getDateTime($endDate);
		$now = $this->timeFactory->getDateTime();
		return $now < $subscriptionEndDate;
	}

	/**
	 * Fetches the list of app IDs that are supported by the subscription
	 *
	 * @since 17.0.0
	 */
	#[\Override]
	public function getSupportedApps(): array {
		// CDrive: FREE - every shipped app is supported, no subscription needed.
		// Groupware (calendar/contacts/deck/mail), Talk (spreed), Collabora
		// (richdocuments) and OnlyOffice are included unconditionally.

		$filesSubscription = [
			'activity',
			'admin_audit',
			'bruteforcesettings',
			'circles',
			'cloud_federation_api',
			'comments',
			'data_request',
			'dav',
			'encryption',
			'external',
			'federatedfilesharing',
			'federation',
			'files',
			'files_accesscontrol',
			'files_antivirus',
			'files_automatedtagging',
			'files_external',
			'files_fulltextsearch',
			'files_fulltextsearch_tesseract',
			'files_pdfviewer',
			'files_retention',
			'files_sharing',
			'files_trashbin',
			'files_versions',
			'firstrunwizard',
			'fulltextsearch',
			'fulltextsearch_elasticsearch',
			'groupfolders',
			'guests',
			'logreader',
			'lookup_server_connector',
			'nextcloud_announcements',
			'notifications',
			'oauth2',
			'password_policy',
			'photos',
			'privacy',
			'provisioning_api',
			'recommendations',
			'serverinfo',
			'settings',
			'sharebymail',
			'sharepoint',
			'socialsharing_diaspora',
			'socialsharing_email',
			'socialsharing_facebook',
			'socialsharing_twitter',
			'support',
			'survey_client',
			'suspicious_login',
			'systemtags',
			'terms_of_service',
			'text',
			'theming',
			'twofactor_backupcodes',
			'twofactor_totp',
			'updatenotification',
			'user_ldap',
			'user_oidc',
			'user_saml',
			'viewer',
			'workflowengine',
			'workflow_script',
		];

		$nextcloudVersion = $this->serverVersion->getMajorVersion();

		if ($nextcloudVersion >= 30) {
			$filesSubscription[] = 'app_api';
			$filesSubscription[] = 'twofactor_nextcloud_notification';
			// CDrive: FREE - webhook_listeners was 'ultimate' tier only.
			$filesSubscription[] = 'webhook_listeners';
		}

		if ($nextcloudVersion >= 29) {
			$filesSubscription[] = 'files_downloadlimit';
			$filesSubscription[] = 'files_reminders';
		}

		if ($nextcloudVersion >= 28) {
			$filesSubscription[] = 'files_reminders';
			$filesSubscription[] = 'security_guard';
		} else {
			// Removed in 28
			$filesSubscription[] = 'files_rightclick';
		}

		if ($nextcloudVersion >= 26) {
			$filesSubscription[] = 'files_confidential';
		}

		if ($nextcloudVersion >= 25) {
			$filesSubscription[] = 'related_resources';
		} else {
			// Removed in 25
			$filesSubscription[] = 'files_videoplayer';
		}

		if ($nextcloudVersion >= 24) {
			$filesSubscription[] = 'files_lock';
		}

		if ($nextcloudVersion >= 22) {
			$filesSubscription[] = 'approval';
			$filesSubscription[] = 'contacts';
			$filesSubscription[] = 'files_zip';
		}

		if ($nextcloudVersion >= 20) {
			$filesSubscription[] = 'dashboard';
			$filesSubscription[] = 'flow_notifications';
			$filesSubscription[] = 'user_status';
			$filesSubscription[] = 'weather_status';
		}

		if ($nextcloudVersion >= 19) {
			$filesSubscription[] = 'contactsinteraction';
		}

		if ($nextcloudVersion >= 18) {
			$filesSubscription[] = 'globalsiteselector';
		}

		$supportedApps = [];

		// CDrive: FREE - base file set plus all formerly paywalled extras.
		$supportedApps = array_merge($supportedApps, $filesSubscription);
		$supportedApps[] = 'calendar';
		$supportedApps[] = 'contacts';
		$supportedApps[] = 'deck';
		$supportedApps[] = 'mail';
		$supportedApps[] = 'spreed';
		$supportedApps[] = 'richdocuments';
		$supportedApps[] = 'onlyoffice';
		$supportedApps[] = 'webhook_listeners';

		$subscriptionInfo = [];
		if (isset($subscriptionInfo['supportedApps'])) {
			foreach ($subscriptionInfo['supportedApps'] as $app) {
				if ($app !== '' && !in_array($app, $supportedApps)) {
					$supportedApps[] = $app;
				}
			}
		}

		return $supportedApps;
	}

	/**
	 * Indicates if the subscription has extended support
	 *
	 * @since 17.0.0
	 */
	#[\Override]
	public function hasExtendedSupport(): bool {
		try {
			return $this->appConfig->getAppValueBool('extended_support');
		} catch (\Throwable) {
			return false;
		}
	}

	/**
	 * Indicates if a hard user limit is reached and no new users should be created
	 *
	 * @since 21.0.0
	 */
	#[\Override]
	public function isHardUserLimitReached(): bool {
		[
			,,
			$isInvalidSubscription,
			$isOverLimit,
			$subscriptionInfo
		] = $this->subscriptionService->getSubscriptionInfo();

		$configUserLimit = (int)$this->config->getAppValue('support', 'user-limit', '0');
		if (
			!$isInvalidSubscription
			&& $configUserLimit > 0
			&& $configUserLimit <= $this->subscriptionService->getUserCount()
		) {
			return true;
		}

		if (!isset($subscriptionInfo['hasHardUserLimit']) || $subscriptionInfo['hasHardUserLimit'] === false) {
			return false;
		}

		return $isOverLimit;
	}
}
