<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Support\Sections;

use OCA\AppAPI\Db\ExAppMapper;
use OCA\AppAPI\Service\DaemonConfigService;
use OCA\Support\IDetail;
use OCA\Support\Section;
use OCP\App\IAppManager;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\Server;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class AppApiSection extends Section {
	public function __construct(
		protected readonly IConfig $config,
		protected readonly IAppManager $appManager,
		protected readonly IClientService $clientService,
	) {
		parent::__construct('app_api', 'AppAPI');
	}

	#[\Override]
	public function getDetails(): array {
		$this->createDetail('AppAPI configuration', $this->getAppApiInfo(), IDetail::TYPE_COLLAPSIBLE);

		return parent::getDetails();
	}

	public function isAppApiEnabled(): bool {
		return $this->appManager->isInstalled('app_api');
	}

	private function getAppApiInfo(): string {
		$output = PHP_EOL;

		try {
			$exAppMapper = Server::get(ExAppMapper::class);
			$exApps = $exAppMapper->findAll();
			$output .= PHP_EOL;
			$output .= '## ExApps' . PHP_EOL;
			if (!empty($exApps)) {
				foreach ($exApps as $exApp) {
					$enabled = $exApp->getEnabled() ? 'enabled' : 'disabled';
					$output .= ' * ' . $exApp->getAppid() . ' (' . $exApp->getName() . '): ' . $exApp->getVersion() . ' [' . $enabled . ']' . PHP_EOL;
				}
			} else {
				$output .= ' * no ExApps installed' . PHP_EOL;
			}

			$daemonConfigService = Server::get(DaemonConfigService::class);
			$daemonConfigs = $daemonConfigService->getRegisteredDaemonConfigs();
			$output .= PHP_EOL;
			$output .= '## Deploy daemons' . PHP_EOL;
			foreach ($daemonConfigs as $daemon) {
				$deployConfig = $daemon->getDeployConfig();
				$deployConfig['haproxy_password'] = '***';
				$output .= ' * ' . $daemon->getName() . ' (' . $daemon->getDisplayName() . ')' . PHP_EOL;
				$output .= '   - Is HaRP: ' . (isset($deployConfig['harp']) ? 'yes' : 'no') . PHP_EOL;
				$output .= '   - Deployment method: ' . $daemon->getAcceptsDeployId() . PHP_EOL;
				$output .= '   - Protocol: ' . $daemon->getProtocol() . PHP_EOL;
				$output .= '   - Host: ' . $daemon->getHost() . PHP_EOL;
				$output .= '   - Deploy config: ' . json_encode($deployConfig, JSON_PRETTY_PRINT) . PHP_EOL;
			}

			$config = [
				'default_daemon_config' => $this->config->getAppValue('app_api', 'default_daemon_config'),
				'init_timeout' => $this->config->getAppValue('app_api', 'init_timeout', '40'),
				'container_restart_policy' => $this->config->getAppValue('app_api', 'container_restart_policy', 'unless-stopped'),
			];
			$output .= PHP_EOL;
			$output .= '## Config' . PHP_EOL;
			$output .= ' * Default daemon config (default_daemon_config): ' . $config['default_daemon_config'] . PHP_EOL;
			$output .= ' * Init timeout (init_timeout): ' . $config['init_timeout'] . PHP_EOL;
			$output .= ' * Container restart policy (container_restart_policy): ' . $config['container_restart_policy'] . PHP_EOL;

		} catch (NotFoundExceptionInterface|ContainerExceptionInterface) {
		}

		return $output;
	}
}
