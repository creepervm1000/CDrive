<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2026 CDrive contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * CDrive fork: subscription/paywall system removed. All features are free,
 * user thresholds disabled, no phone-home to subscription backends.
 * Original notices preserved per AGPLv3.
 */

namespace OCA\Support\Service;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use OC\User\Backend;
use OCP\Http\Client\IClientService;
use OCP\IAppConfig;
use OCP\ICacheFactory;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Mail\IMailer;
use OCP\Notification\IManager;
use OCP\ServerVersion;
use OCP\User\Backend\ICountUsersBackend;
use Psr\Log\LoggerInterface;

class SubscriptionService {
	public const ERROR_FAILED_RETRY = 1;
	public const ERROR_FAILED_INVALID = 2;
	public const ERROR_NO_INTERNET_CONNECTION = 3;
	public const ERROR_INVALID_SUBSCRIPTION_KEY = 4;

	// CDrive: user thresholds removed - no paywall tiers. Kept as constants
	// for API compatibility; set to max so instanceSize is always 'small'.
	public const THRESHOLD_MEDIUM = PHP_INT_MAX;
	public const THRESHOLD_LARGE = PHP_INT_MAX;

	private int $userCount = -1;
	private int $activeUserCount = -1;

	private ?array $subscriptionInfoCache = null;

	public function __construct(
		protected readonly IConfig $config,
		protected readonly IClientService $clientService,
		protected readonly LoggerInterface $log,
		protected readonly IUserManager $userManager,
		protected readonly IManager $notifications,
		protected readonly IURLGenerator $urlGenerator,
		protected readonly IGroupManager $groupManager,
		protected readonly IMailer $mailer,
		protected readonly IFactory $l10nFactory,
		protected readonly ICacheFactory $cacheFactory,
		protected readonly IAppConfig $appConfig,
		protected readonly ServerVersion $serverVersion,
	) {
	}

	public function setSubscriptionKey(string $subscriptionKey): void {
		// CDrive: subscription system removed. Keys are not accepted, stored
		// or verified. Kept as no-op for API compatibility.
		return;
	}

	public function getUserCount(): int {
		if ($this->userCount > 0) {
			return $this->userCount;
		}

		$userCount = 0;
		$backends = $this->userManager->getBackends();
		foreach ($backends as $backend) {
			if ($backend->implementsActions(Backend::COUNT_USERS)) {
				/** @var ICountUsersBackend $backend */
				try {
					$backendUsers = $backend->countUsers();
				} catch (\Exception $e) {
					$backendUsers = false;

					$this->log->error($e->getMessage(), ['exception' => $e]);
				}
				if ($backendUsers !== false) {
					$userCount += $backendUsers;
				} else {
					// TODO what if the user count can't be determined?
					$this->log->warning('Can not determine user count for ' . get_class($backend), ['app' => 'support']);
				}
			}
		}

		$disabledUsers = $this->config->getUsersForUserValue('core', 'enabled', 'false');
		$disabledUsersCount = count($disabledUsers);
		$this->userCount = $userCount - $disabledUsersCount;

		if ($this->userCount < 0) {
			$this->userCount = 0;

			// TODO this should never happen
			$this->log->warning("Total user count was negative (users: $userCount, disabled: $disabledUsersCount)", ['app' => 'support']);
		}

		return $this->userCount;
	}

	public function getActiveUserCount(): int {
		if ($this->activeUserCount > 0) {
			return $this->activeUserCount;
		}

		$this->activeUserCount = $this->userManager->countSeenUsers();

		return $this->activeUserCount;
	}

	public function renewSubscriptionInfo(bool $fast): void {
		// CDrive: subscription phone-home removed. Never contacts the
		// subscription backend (no user counts, app lists or versions leave
		// the server). Kept as no-op for API compatibility.
		return;
	}

	public function getSubscriptionInfo(): array {
		// CDrive: no subscriptions exist. Always report: small instance,
		// no subscription, nothing invalid, never over limit.
		if ($this->subscriptionInfoCache !== null) {
			return $this->subscriptionInfoCache;
		}

		$this->subscriptionInfoCache = [
			'small',
			false,
			false,
			false,
			null,
		];

		return $this->subscriptionInfoCache;
	}

	public function getLastResponseSubscriptionInfo(): ?array {
		$subscriptionInfo = $this->appConfig->getValueArray('support', 'last_response', lazy: true);
		if (empty($subscriptionInfo)) {
			return null;
		}

		return $subscriptionInfo;
	}

	public function checkSubscription(): void {
		// CDrive: subscription nags removed. No notifications or emails are
		// ever sent about missing/expired/over-limit subscriptions.
		return;
	}

	private function handleNoSubscription(string $instanceSize): void {
		// CDrive: removed. No subscription nags.
		return;
	}

	private function handleOverLimit(string $accountManager, string $accountManagerEmail, string $accountManagerPhone): void {
		// CDrive: removed. No over-limit nags; there is no user limit.
		return;
	}

	private function handleExpired(string $accountManager, string $accountManagerEmail, string $accountManagerPhone): void {
		// CDrive: removed. Subscriptions do not exist, so nothing can expire.
		return;
	}

	private function sendNoSubscriptionEmail(IUser $user): void {
		// CDrive: removed. No subscription marketing emails.
		return;
	}

	private function sendOverLimitEmail(IUser $user, string $accountManager, string $accountManagerEmail, string $accountManagerPhone): void {
		// CDrive: removed. No over-limit emails; there is no user limit.
		return;
	}

	private function sendExpiredEmail(IUser $user, string $accountManager, string $accountManagerEmail, string $accountManagerPhone): void {
		// CDrive: removed. Subscriptions do not exist, so nothing can expire.
		return;
	}

	/**
	 * return details about installed apps
	 *
	 *  [
	 *    appId => [
	 *      'enabled' => string,
	 *      'version' => string
	 *    ]
	 * ]
	 *
	 * 'enabled' can be:
	 *     'disabled', if app is disabled
	 *     'enabled', if app is enabled
	 *     'group-limited', if app is limited to groups
	 *     'invalid', if stored value does not fit previous condition
	 *
	 * @return array<string, array<string, string>>
	 */
	private function getAppsDetails(): array {
		/** @var array<string, string> */
		$enabled = $this->appConfig->searchValues('enabled', false, IAppConfig::VALUE_STRING);
		/** @var array<string, string> */
		$installed = $this->appConfig->searchValues('installed_version', false, IAppConfig::VALUE_STRING);

		/** @var array<string, array<string, string>> $details */
		$details = [];
		foreach ($enabled as $appId => $enabledStatus) {
			$enabledFlag = 'invalid';
			try {
				$enabledFlag = match ($enabledStatus) {
					'no' => 'disabled',
					'yes' => 'enabled',
					default => (is_array(json_decode($enabledStatus, flags: JSON_THROW_ON_ERROR))) ? 'group-limited' : $enabledFlag
				};
			} catch (\JsonException) {
			}

			$details[$appId] = [
				'enabled' => $enabledFlag,
				'version' => $installed[$appId] ?? 'missing',
			];
		}

		return $details;
	}
}
