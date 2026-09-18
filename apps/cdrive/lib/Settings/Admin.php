<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 CDrive contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Admin settings for the CDrive egress firewall and per-app proxying.
 */

namespace OCA\Cdrive\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IAppConfig;
use OCP\App\IAppManager;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\Settings\IDelegatedSettings;

class Admin implements IDelegatedSettings {

	public function __construct(
		protected readonly IConfig $config,
		protected readonly IURLGenerator $urlGenerator,
		protected readonly IAppConfig $appConfig,
		protected readonly IAppManager $appManager,
		protected readonly IRequest $request,
	) {
	}

	#[\Override]
	public function getForm(): TemplateResponse {
		$appProxy = $this->config->getSystemValue('cdrive_app_proxy', []);
		if (!is_array($appProxy)) {
			$appProxy = [];
		}
		$appProxyLines = [];
		foreach ($appProxy as $appId => $spec) {
			if (!is_string($appId)) {
				continue;
			}
			$appProxyLines[] = $appId . '=' . (is_bool($spec) ? ($spec ? 'auto' : 'off') : (string)$spec);
		}

		$failures = json_decode($this->config->getAppValue('cdrive_egress', 'proxy_failures', '[]'), true);
		$banned = json_decode($this->config->getAppValue('cdrive_egress', 'proxy_banned', '[]'), true);
		$netlog = json_decode($this->config->getAppValue('cdrive_egress', 'netlog', '[]'), true);

		$params = [
			'mode' => $this->config->getSystemValueString('cdrive_egress_mode', 'allowlist'),
			'rulesOff' => $this->config->getSystemValue('cdrive_egress_disabled', false) ? true : false,
			'allowSelf' => $this->config->getSystemValue('cdrive_egress_allow_self', true) ? true : false,
			'logging' => $this->config->getSystemValue('cdrive_egress_log', true) ? true : false,
			'denylist' => implode("\n", $this->stringList('cdrive_egress_denylist')),
			'allowedHosts' => implode("\n", $this->stringList('cdrive_egress_allowed_hosts')),
			'appAllowlist' => implode("\n", $this->stringList('cdrive_egress_app_allowlist')),
			'proxyList' => implode("\n", $this->stringList('cdrive_proxy_list')),
			'appProxy' => implode("\n", $appProxyLines),
			'feedUrl' => $this->config->getSystemValueString('announcements.feed_url', ''),
			'lookupServer' => $this->config->getSystemValueString('lookup_server', ''),
			'proxyFailures' => is_array($failures) ? $failures : [],
			'proxyBanned' => is_array($banned) ? $banned : [],
			'netlog' => is_array($netlog) ? array_slice(array_values($netlog), 0, 100) : [],
			'appDenylist' => implode("\n", $this->stringList('cdrive_egress_app_denylist')),
			'appBypass' => implode("\n", $this->stringList('cdrive_egress_app_bypass')),
			'depsQuery' => trim((string)$this->request->getParam('showDeps', '')),
			'depsResult' => $this->lookupDeps(trim((string)$this->request->getParam('showDeps', ''))),
			'saveUrl' => $this->urlGenerator->linkToRoute('cdrive.config.save'),
			'depsUrl' => $this->urlGenerator->linkToRoute('cdrive.config.deps'),
			'clearBansUrl' => $this->urlGenerator->linkToRoute('cdrive.config.clearProxyBans'),
			'reinitUrl' => $this->urlGenerator->linkToRoute('cdrive.config.reinit'),
			'lastReinit' => $this->appConfig->getAppValueInt('last_reinit'),
			'showDiagnostics' => $this->request->getParam('showDiagnostics', '') === '1',
			'diagnostics' => $this->request->getParam('showDiagnostics', '') === '1' ? $this->getDiagnostics() : [],
			'diagnosticsUrl' => $this->urlGenerator->linkToRoute('settings.AdminSettings.index', ['section' => 'cdrive', 'showDiagnostics' => '1']),
		];

		return new TemplateResponse('cdrive', 'admin', $params);
	}

	#[\Override]
	public function getSection(): string {
		return 'cdrive';
	}

	#[\Override]
	public function getPriority(): int {
		return 0;
	}

	#[\Override]
	public function getName(): ?string {
		return null; // Only one setting in this section
	}

	#[\Override]
	public function getAuthorizedAppConfig(): array {
		return [
			'cdrive' => ['.*'],
			'cdrive_egress' => ['.*'],
		];
	}

	/** @return list<string> */
	private function stringList(string $key): array {
		$value = $this->config->getSystemValue($key, []);
		if (!is_array($value)) {
			return [];
		}
		$list = [];
		foreach ($value as $entry) {
			if (is_string($entry) && trim($entry) !== '') {
				$list[] = trim($entry);
			}
		}
		return $list;
	}

	/**
	 * Dependency info for one app id, or an error string when unknown.
	 * Includes declared dependencies plus installed apps mentioning it.
	 *
	 * @return array{found: bool, error?: string, id?: string, name?: string, version?: string, enabled?: bool, installed?: bool, dependencies?: array, mentionedBy?: list<string>}
	 */
	private function lookupDeps(string $appId): array {
		if ($appId === '') {
			return ['found' => false];
		}
		$info = $this->appManager->getAppInfo($appId);
		if ($info === null) {
			return ['found' => false, 'error' => 'Unknown app id: ' . $appId];
		}
		$mentionedBy = [];
		foreach ($this->appManager->getInstalledApps() as $other) {
			if ($other === $appId) {
				continue;
			}
			$otherInfo = $this->appManager->getAppInfo($other);
			if ($otherInfo !== null && str_contains(json_encode($otherInfo['dependencies'] ?? []), '"' . $appId . '"')) {
				$mentionedBy[] = $other;
			}
		}
		sort($mentionedBy);
		return [
			'found' => true,
			'id' => $appId,
			'name' => $info['name'] ?? $appId,
			'version' => $this->appManager->getAppVersion($appId),
			'enabled' => (bool)$this->appManager->isEnabledForUser($appId),
			'installed' => $this->appManager->isInstalled($appId),
			'dependencies' => $info['dependencies'] ?? [],
			'mentionedBy' => $mentionedBy,
		];
	}

	/**
	 * Read-only App Store diagnostics for administrators. Keep this behind an
	 * explicit query flag because logs and cache contents can contain URLs or
	 * other instance-specific information.
	 *
	 * @return array{log: list<string>, caches: list<array{path: string, size: int, timestamp: int, ncversion: string, etag: string, count: int, apps: list<array{id: string, name: string, categories: list<string>}>}, error?: string}
	 */
	private function getDiagnostics(): array {
		$dataDirectory = $this->config->getSystemValueString('datadirectory', '');
		if ($dataDirectory === '' || !is_dir($dataDirectory)) {
			return ['log' => [], 'caches' => [], 'error' => 'The data directory is not readable.'];
		}

		$log = [];
		$logPath = rtrim($dataDirectory, '/') . '/nextcloud.log';
		if (is_readable($logPath)) {
			$contents = file_get_contents($logPath, false, null, max(0, (int)filesize($logPath) - 512 * 1024));
			if (is_string($contents)) {
				foreach (array_reverse(preg_split('/\R/', trim($contents)) ?: []) as $line) {
					if (preg_match('/appstore|apps\.nextcloud|appstore-fetcher|cdrive egress|curl error 28/i', $line) === 1) {
						$log[] = $line;
						if (count($log) >= 100) {
							break;
						}
					}
				}
			}
		}

		$caches = [];
		foreach (glob(rtrim($dataDirectory, '/') . '/appdata_*/appstore/apps.json') ?: [] as $path) {
			$decoded = json_decode((string)file_get_contents($path), true);
			$data = is_array($decoded) && isset($decoded['data']) && is_array($decoded['data']) ? $decoded['data'] : [];
			$apps = [];
			foreach (array_slice($data, 0, 200) as $app) {
				if (!is_array($app)) {
					continue;
				}
				$categories = [];
				foreach (($app['categories'] ?? []) as $category) {
					if (is_string($category)) {
						$categories[] = $category;
					}
				}
				$apps[] = [
					'id' => (string)($app['id'] ?? ''),
					'name' => (string)($app['name'] ?? $app['id'] ?? ''),
					'categories' => $categories,
				];
			}
			$caches[] = [
				'path' => $path,
				'size' => (int)(filesize($path) ?: 0),
				'timestamp' => (int)($decoded['timestamp'] ?? 0),
				'ncversion' => (string)($decoded['ncversion'] ?? ''),
				'etag' => (string)($decoded['ETag'] ?? ''),
				'count' => count($data),
				'apps' => $apps,
			];
		}

		return ['log' => $log, 'caches' => $caches];
	}
}
