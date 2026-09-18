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
}
