<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2026 CDrive contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * CDrive fork: subscription reporting removed from system reports.
 */

namespace OCA\Support\Sections;

use OC\IntegrityCheck\Checker;
use OC\SystemConfig;
use OCA\Files_External\Lib\StorageConfig;
use OCA\Files_External\Service\GlobalStoragesService;
use OCA\Support\IDetail;
use OCA\Support\Section;
use OCP\App\IAppManager;
use OCP\DB\Exception;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IUserManager;
use OCP\Server;
use OCP\ServerVersion;
use OCP\Support\Subscription\IRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\BufferedOutput;

class ServerSection extends Section {
	public function __construct(
		protected readonly IConfig $config,
		protected readonly Checker $checker,
		protected readonly IAppManager $appManager,
		protected readonly IDBConnection $connection,
		protected readonly IUserManager $userManager,
		protected readonly LoggerInterface $logger,
		protected readonly SystemConfig $systemConfig,
		protected readonly IAppConfig $appConfig,
		protected readonly ServerVersion $serverVersion,
	) {
		parent::__construct('server-detail', 'Server configuration detail');
	}

	#[\Override]
	public function getDetails(): array {
		$this->createDetail('Operating system', $this->getOsVersion());
		$this->createDetail('Webserver', $this->getWebserver());
		$this->createDetail('Database', $this->getDatabaseInfo());
		$this->createDetail('PHP version', $this->getPhpVersion());
		$this->createDetail('Nextcloud version', $this->getNextcloudVersion());
		$this->createDetail('Updated from an older Nextcloud/ownCloud or fresh install', '');
		$this->createDetail('Where did you install Nextcloud from', $this->getInstallMethod());
		$this->createDetail('Signing status', $this->getIntegrityResults(), IDetail::TYPE_COLLAPSIBLE);
		$this->createDetail('List of activated apps', $this->renderAppList(), IDetail::TYPE_COLLAPSIBLE_PREFORMAT);

		$this->createDetail('Configuration (config/config.php)', print_r(json_encode($this->getConfig(), JSON_PRETTY_PRINT), true), IDetail::TYPE_COLLAPSIBLE_PREFORMAT);
		$this->createDetail('Cron Configuration', $this->getCronConfig());

		$externalStorageEnabled = $this->appManager->isEnabledForUser('files_external');
		$this->createDetail('External storages', $externalStorageEnabled ? 'yes' : 'files_external is disabled');
		if ($externalStorageEnabled) {
			$this->createDetail('External storage configuration', $this->getExternalStorageInfo(), IDetail::TYPE_COLLAPSIBLE_PREFORMAT);
		}

		$this->createDetail('Encryption', $this->getEncryptionInfo());
		$this->createDetail('User-backends', $this->getUserBackendInfo());
		$this->createDetail('Subscription', $this->getSubscriptionInfo());

		$this->createDetail('Browser', $this->getBrowser());

		return parent::getDetails();
	}

	private function getWebserver(): string {
		return ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . ' (' . PHP_SAPI . ')';
	}

	private function getNextcloudVersion(): string {
		return $this->serverVersion->getHumanVersion() . ' - ' . $this->config->getSystemValueString('version');
	}
	private function getOsVersion(): string {
		return function_exists('php_uname') ? php_uname('s') . ' ' . php_uname('r') . ' ' . php_uname('v') . ' ' . php_uname('m') : PHP_OS;
	}
	private function getPhpVersion(): string {
		return PHP_VERSION . "\n\nModules loaded: " . implode(', ', get_loaded_extensions());
	}

	protected function getDatabaseInfo(): string {
		return $this->config->getSystemValueString('dbtype') . ' ' . $this->getDatabaseVersion();
	}

	/**
	 * original source from nextcloud/survey_client
	 * @link https://github.com/nextcloud/survey_client/blob/master/lib/Categories/Database.php#L80-L107
	 *
	 * @copyright Copyright (c) 2016, ownCloud, Inc.
	 * @author Joas Schilling <coding@schilljs.com>
	 * @license AGPL-3.0
	 */
	private function getDatabaseVersion(): string {
		switch ($this->config->getSystemValueString('dbtype')) {
			case 'sqlite':
			case 'sqlite3':
				$sql = 'SELECT sqlite_version() AS version';
				break;
			case 'oci':
				$sql = 'SELECT VERSION FROM PRODUCT_COMPONENT_VERSION';
				break;
			case 'mysql':
			case 'pgsql':
			default:
				$sql = 'SELECT VERSION() AS version';
				break;
		}

		try {
			$result = $this->connection->executeQuery($sql);
			$version = $result->fetchOne();
			$result->closeCursor();
			if ($version) {
				return $this->cleanVersion($version);
			}
		} catch (Exception $e) {
			$this->logger->debug('Unable to determine database version', [
				'exception' => $e
			]);
		}

		return 'N/A';
	}

	/**
	 * Try to strip away additional information
	 *
	 * @copyright Copyright (c) 2016, ownCloud, Inc.
	 * @author Joas Schilling <coding@schilljs.com>
	 * @license AGPL-3.0
	 *
	 * @param string $version E.g. `5.6.27-0ubuntu0.14.04.1`
	 * @return string `5.6.27`
	 */
	protected function cleanVersion(string $version): string {
		$matches = [];
		preg_match('/^(\d+)(\.\d+)(\.\d+)/', $version, $matches);
		if (isset($matches[0])) {
			return $matches[0];
		}
		return $version;
	}

	private function getCronConfig(): string {
		$mode = $this->appConfig->getValueString('core', 'backgroundjobs_mode', 'ajax');
		$last = $this->appConfig->getValueInt('core', 'lastcron', 0);

		if ($last === 0) {
			$formattedLast = 'never';
		} else {
			$formattedLast = date('c', $last) . ' (' . (time() - $last) . ' seconds ago)';
		}

		return PHP_EOL . PHP_EOL
			. 'Mode: ' . $mode . PHP_EOL
			. 'Last: ' . $formattedLast . PHP_EOL;
	}

	private function getIntegrityResults(): string {
		if (!$this->checker->isCodeCheckEnforced()) {
			return 'Integrity checker has been disabled. Integrity cannot be verified.';
		}
		return print_r(json_encode($this->checker->getResults(), JSON_PRETTY_PRINT), true);
	}

	private function getInstallMethod(): string {
		$base = \OC::$SERVERROOT;
		if (file_exists($base . '/.git')) {
			return 'git';
		}
		return 'unknown';
	}

	private function renderAppList(): string {
		$apps = $this->getAppList();

		$result = '';
		if ($apps['supported'] !== []) {
			$result .= "Supported:\n";
			foreach ($apps['supported'] as $name => $version) {
				$result .= ' - ' . $name . ': ' . $version . "\n";
			}
		}

		$result .= "Enabled:\n";
		foreach ($apps['enabled'] as $name => $version) {
			$result .= ' - ' . $name . ': ' . $version . "\n";
		}

		$result .= "Disabled:\n";
		foreach ($apps['disabled'] as $name => $version) {
			if ($version) {
				$result .= ' - ' . $name . ': ' . $version . "\n";
			} else {
				$result .= ' - ' . $name . "\n";
			}
		}
		return $result;
	}

	/**
	 * @return array<string, array<string, string|bool>>
	 */
	private function getAppList(): array {
		$apps = $this->appManager->getAllAppsInAppsFolders();
		$alwaysEnabled = $this->appManager->getAlwaysEnabledApps();

		$subscriptionRegistry = Server::get(IRegistry::class);
		$supportedAppsIDs = $subscriptionRegistry->delegateGetSupportedApps();

		$supportedApps = $enabledApps = $disabledApps = [];
		$versions = $this->appManager->getAppInstalledVersions();

		// sort enabled apps above disabled apps
		foreach ($apps as $app) {
			if (in_array($app, $alwaysEnabled)) {
				continue;
			}
			if ($this->appManager->isEnabledForAnyone($app)) {
				if (in_array($app, $supportedAppsIDs)) {
					$supportedApps[] = $app;
					continue;
				}

				// enabled but not ours
				$enabledApps[] = $app;
				continue;
			}

			$disabledApps[] = $app;
		}

		$apps = [
			'supported' => [],
			'enabled' => [],
			'disabled' => []
		];

		sort($supportedApps);
		foreach ($supportedApps as $app) {
			$apps['supported'][$app] = $versions[$app] ?? true;
		}

		sort($enabledApps);
		foreach ($enabledApps as $app) {
			$apps['enabled'][$app] = $versions[$app] ?? true;
		}

		sort($disabledApps);
		foreach ($disabledApps as $app) {
			$apps['disabled'][$app] = $versions[$app] ?? false;
		}

		return $apps;
	}

	protected function getEncryptionInfo(): string {
		return $this->appConfig->getValueString('core', 'encryption_enabled', 'no');
	}

	protected function getExternalStorageInfo(): string {
		$globalService = Server::get(GlobalStoragesService::class);
		$mounts = $globalService->getStorageForAllUsers();

		// copy of OCA\Files_External\Command\ListCommand::listMounts
		if ($mounts === null || count($mounts) === 0) {
			return 'No mounts configured';
		}
		$headers = ['Mount ID', 'Mount Point', 'Storage', 'Authentication Type', 'Configuration', 'Options'];
		$headers[] = 'Applicable Users';
		$headers[] = 'Applicable Groups';
		$headers[] = 'Type';

		$hideKeys = ['password', 'refresh_token', 'token', 'client_secret', 'public_key', 'private_key', 'key', 'secret'];
		/** @var StorageConfig $mount */
		foreach ($mounts as $mount) {
			$config = $mount->getBackendOptions();
			foreach ($config as $key => $value) {
				if (in_array($key, $hideKeys)) {
					$mount->setBackendOption($key, '***');
				}
			}
		}

		$defaultMountOptions = [
			'encrypt' => true,
			'previews' => true,
			'filesystem_check_changes' => 1,
			'enable_sharing' => false,
			'encoding_compatibility' => false,
			'readonly' => false,
		];
		$rows = array_map(function (StorageConfig $config) use ($defaultMountOptions) {
			$storageConfig = $config->getBackendOptions();
			$keys = array_keys($storageConfig);
			$values = array_values($storageConfig);
			$configStrings = array_map(function ($key, $value) {
				return $key . ': ' . json_encode($value);
			}, $keys, $values);
			$configString = implode(', ', $configStrings);
			$mountOptions = $config->getMountOptions();
			// hide defaults
			foreach ($mountOptions as $key => $value) {
				if (isset($defaultMountOptions[$key]) && ($value === $defaultMountOptions[$key])) {
					unset($mountOptions[$key]);
				}
			}
			$keys = array_keys($mountOptions);
			$values = array_values($mountOptions);
			$optionsStrings = array_map(function ($key, $value) {
				return $key . ': ' . json_encode($value);
			}, $keys, $values);
			$optionsString = implode(', ', $optionsStrings);
			$values = [
				$config->getId(),
				$config->getMountPoint(),
				$config->getBackend()->getText(),
				$config->getAuthMechanism()->getText(),
				$configString,
				$optionsString
			];
			$applicableUsers = implode(', ', $config->getApplicableUsers());
			$applicableGroups = implode(', ', $config->getApplicableGroups());
			if ($applicableUsers === '' && $applicableGroups === '') {
				$applicableUsers = 'All';
			}
			$values[] = $applicableUsers;
			$values[] = $applicableGroups;
			$values[] = $config->getType() === StorageConfig::MOUNT_TYPE_ADMIN ? 'Admin' : 'Personal';

			return $values;
		}, $mounts);

		$output = new BufferedOutput();
		$table = new Table($output);
		$table->setHeaders($headers);
		$table->setRows($rows);
		$table->render();

		return $output->fetch();
	}

	private function getConfig(): array {
		$keys = $this->systemConfig->getKeys();
		$configs = [];
		foreach ($keys as $key) {
			$value = $this->config->getFilteredSystemValue($key, serialize(null));
			if ($value !== 'N;') {
				$configs[$key] = $value;
			}
		}
		return $configs;
	}

	private function getBrowser(): string {
		return $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
	}

	private function getUserBackendInfo(): string {
		$backends = $this->userManager->getBackends();

		$output = PHP_EOL;
		foreach ($backends as $backend) {
			$output .= ' * ' . get_class($backend) . PHP_EOL;
		}

		return $output;
	}

	private function getSubscriptionInfo(): string {
		// CDrive: no subscription system; system reports carry no
		// subscription, key or limit state.
		return PHP_EOL . ' * CDrive: subscription system removed, all features free' . PHP_EOL;
	}
}
