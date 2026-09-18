<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Support\Sections;

use OCA\Support\IDetail;
use OCA\Support\Section;
use OCP\App\IAppManager;
use OCP\Http\Client\IClientService;
use OCP\IAppConfig;
use OCP\IConfig;

class TalkSection extends Section {
	public function __construct(
		protected readonly IConfig $config,
		protected readonly IAppManager $appManager,
		protected readonly IClientService $clientService,
		protected readonly IAppConfig $appConfig,
	) {
		parent::__construct('talk', 'Talk');
	}

	#[\Override]
	public function getDetails(): array {
		$this->createDetail('Talk configuration', $this->getTalkInfo());
		$this->createDetail('Talk app configuration', $this->getTalkAppConfiguration(), IDetail::TYPE_COLLAPSIBLE_PREFORMAT);

		return parent::getDetails();
	}

	public function isTalkEnabled(): bool {
		return $this->appManager->isEnabledForUser('spreed');
	}

	private function getTalkInfo(): string {
		$output = PHP_EOL;

		$config = $this->config->getAppValue('spreed', 'stun_servers');
		$servers = json_decode($config, true);

		$output .= PHP_EOL;
		$output .= 'STUN servers' . PHP_EOL;
		if (empty($servers)) {
			$output .= ' * no custom server configured' . PHP_EOL;
		} else {
			foreach ($servers as $server) {
				$output .= ' * ' . $server . PHP_EOL;
			}
		}

		$config = $this->config->getAppValue('spreed', 'turn_servers');
		$servers = json_decode($config, true);

		$output .= PHP_EOL;
		$output .= 'TURN servers' . PHP_EOL;
		if (empty($servers)) {
			$output .= ' * no custom server configured' . PHP_EOL;
		} else {
			foreach ($servers as $server) {
				$output .= ' * ' . ($server['schemes'] ?? 'turn') . ':' . $server['server'] . ' - ' . $server['protocols'] . PHP_EOL;
			}
		}

		$config = $this->config->getAppValue('spreed', 'signaling_mode', 'default');
		$output .= PHP_EOL;
		$output .= 'Signaling servers (mode: ' . $config . '):' . PHP_EOL;

		if ($this->config->getAppValue('spreed', 'sip_bridge_shared_secret') !== '') {
			$output .= ' * SIP dialin is enabled' . PHP_EOL;
		} else {
			$output .= ' * SIP dialin is disabled' . PHP_EOL;
		}
		if ($this->config->getAppValue('spreed', 'sip_dialout', 'no') !== 'no') {
			$output .= ' * SIP dialout is enabled' . PHP_EOL;
		} else {
			$output .= ' * SIP dialout is disabled' . PHP_EOL;
		}

		$config = $this->config->getAppValue('spreed', 'signaling_servers');
		$servers = json_decode($config, true);

		if (empty($servers['servers'])) {
			$output .= ' * no custom server configured' . PHP_EOL;
		} else {
			foreach ($servers['servers'] as $server) {
				$output .= ' * ' . $server['server'] . ' - ' . $this->getTalkComponentVersion($server['server']) . PHP_EOL;
			}
		}

		$output .= PHP_EOL;
		$output .= 'Recording servers:' . PHP_EOL;
		if ($this->config->getAppValue('spreed', 'call_recording', 'yes') !== 'yes') {
			$output .= ' * Recording is disabled' . PHP_EOL;
		} else {
			$output .= ' * Recording is enabled' . PHP_EOL;
		}
		$output .= ' * Recording consent is set to "' . $this->config->getAppValue('spreed', 'recording_consent', 'default') . '"' . PHP_EOL;

		$config = $this->config->getAppValue('spreed', 'recording_servers');
		$servers = json_decode($config, true);

		if (empty($servers['servers'])) {
			$output .= ' * no recording server configured' . PHP_EOL;
		} else {
			foreach ($servers['servers'] as $server) {
				$output .= ' * ' . $server['server'] . ' - ' . $this->getTalkComponentVersion($server['server']) . PHP_EOL;
			}
		}

		return $output;
	}

	private function getTalkAppConfiguration(): string {
		$spreedConfig = $this->appConfig->getAllValues('spreed', filtered: true);
		return json_encode($spreedConfig, JSON_PRETTY_PRINT) . PHP_EOL;
	}

	private function getTalkComponentVersion(string $url): string {
		$url = rtrim($url, '/');

		if (strpos($url, 'wss://') === 0) {
			$url = 'https://' . substr($url, 6);
		}

		if (strpos($url, 'ws://') === 0) {
			$url = 'http://' . substr($url, 5);
		}

		$client = $this->clientService->newClient();
		try {
			$response = $client->get($url . '/api/v1/welcome', [
				'verify' => false,
				'nextcloud' => [
					'allow_local_address' => true,
				],
			]);

			$body = $response->getBody();

			$data = json_decode($body, true);
			if (!is_array($data) || !isset($data['version'])) {
				return 'error';
			}

			return (string)$data['version'];
		} catch (\Exception $e) {
			return 'error: ' . $e->getMessage();
		}
	}
}
