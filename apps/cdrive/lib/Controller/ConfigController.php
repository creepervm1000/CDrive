<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 CDrive contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Cdrive\Controller;

use OCA\Cdrive\Settings\Admin;
use OCA\Theming\ThemingDefaults;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Services\IAppConfig;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IURLGenerator;
use Psr\Log\LoggerInterface;

class ConfigController extends Controller {

	public function __construct(
		string $appName,
		IRequest $request,
		protected readonly IConfig $config,
		protected readonly IURLGenerator $urlGenerator,
		protected readonly IAppConfig $appConfig,
		protected readonly LoggerInterface $logger,
		protected readonly ThemingDefaults $themingDefaults,
	) {
		parent::__construct($appName, $request);
	}

	#[AuthorizedAdminSetting(settings: Admin::class)]
	public function save(string $mode = 'allowlist',
		string $rulesOff = 'off',
		string $allowSelf = 'on',
		string $logging = 'on',
		string $denylist = '',
		string $allowedHosts = '',
		string $appAllowlist = '',
		string $appDenylist = '',
		string $appBypass = '',
		string $proxyList = '',
		string $appProxy = '',
		string $feedUrl = '',
		string $lookupServer = ''): RedirectResponse {
		$mode = strtolower(trim($mode)) === 'denylist' ? 'denylist' : 'allowlist';
		$this->config->setSystemValue('cdrive_egress_mode', $mode);
		$this->config->setSystemValue('cdrive_egress_disabled', strtolower(trim($rulesOff)) === 'on');
		$this->config->setSystemValue('cdrive_egress_allow_self', strtolower(trim($allowSelf)) !== 'off');
		$this->config->setSystemValue('cdrive_egress_log', strtolower(trim($logging)) !== 'off');
		$this->config->setSystemValue('cdrive_egress_denylist', self::lines($denylist));
		$this->config->setSystemValue('cdrive_egress_allowed_hosts', self::lines($allowedHosts));
		$this->config->setSystemValue('cdrive_egress_app_allowlist', self::lines(strtolower($appAllowlist)));
		$this->config->setSystemValue('cdrive_egress_app_denylist', self::lines(strtolower($appDenylist)));
		$this->config->setSystemValue('cdrive_egress_app_bypass', self::lines(strtolower($appBypass)));
		$this->config->setSystemValue('cdrive_proxy_list', self::lines($proxyList));
		$this->config->setSystemValue('cdrive_app_proxy', self::mapLines($appProxy));
		$this->config->setSystemValue('announcements.feed_url', trim($feedUrl));
		$this->config->setSystemValue('lookup_server', trim($lookupServer));

		return new RedirectResponse($this->urlGenerator->getAbsoluteURL($this->urlGenerator->linkToRoute('settings.AdminSettings.index', ['section' => 'cdrive'])));
	}

	#[AuthorizedAdminSetting(settings: Admin::class)]
	public function deps(string $showDeps = ''): RedirectResponse {
		return new RedirectResponse($this->urlGenerator->getAbsoluteURL($this->urlGenerator->linkToRoute('settings.AdminSettings.index', ['section' => 'cdrive', 'showDeps' => trim($showDeps)])));
	}

	#[AuthorizedAdminSetting(settings: Admin::class)]
	public function clearProxyBans(): RedirectResponse {		$this->config->deleteAppValue('cdrive_egress', 'proxy_banned');
		$this->config->deleteAppValue('cdrive_egress', 'proxy_failures');

		return new RedirectResponse($this->urlGenerator->getAbsoluteURL($this->urlGenerator->linkToRoute('settings.AdminSettings.index', ['section' => 'cdrive'])));
	}

	/**
	 * Reinit the instance without touching the host: reset PHP OPcache,
	 * flush APCu user cache, clear the stat cache and bump the theming
	 * cache buster so all CSS/icons regenerate. Cannot restart php-fpm
	 * itself (needs root); run `systemctl restart php-fpm` for that.
	 */
	#[AuthorizedAdminSetting(settings: Admin::class)]
	public function reinit(): RedirectResponse {
		$did = [];
		if (function_exists('opcache_reset') && @opcache_reset()) {
			$did[] = 'opcache';
		}
		if (function_exists('apcu_clear_cache') && @apcu_clear_cache()) {
			$did[] = 'apcu';
		}
		clearstatcache(true);
		$did[] = 'statcache';
		try {
			$this->themingDefaults->increaseCacheBuster();
			$did[] = 'theming';
		} catch (\Throwable $e) {
			$this->logger->warning('CDrive reinit: theming cache buster failed', ['exception' => $e, 'app' => 'cdrive']);
		}
		$this->appConfig->setAppValueInt('last_reinit', time());
		$this->logger->info('CDrive reinit completed (' . implode(',', $did) . ')', ['app' => 'cdrive']);

		return new RedirectResponse($this->urlGenerator->getAbsoluteURL($this->urlGenerator->linkToRoute('settings.AdminSettings.index', ['section' => 'cdrive'])));
	}

	/** @return list<string> */
	private static function lines(string $text): array {
		$list = [];
		foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
			$line = trim($line);
			if ($line !== '' && !str_starts_with($line, '#')) {
				$list[] = $line;
			}
		}
		return array_values(array_unique($list));
	}

	/** @return array<string, string> */
	private static function mapLines(string $text): array {
		$map = [];
		foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
			$line = trim($line);
			if ($line === '' || str_starts_with($line, '#')) {
				continue;
			}
			$parts = explode('=', $line, 2);
			if (count($parts) !== 2) {
				continue;
			}
			[$app, $spec] = [strtolower(trim($parts[0])), trim($parts[1])];
			if ($app === '' || $spec === '' || strtolower($spec) === 'off') {
				continue;
			}
			$map[$app] = $spec;
		}
		return $map;
	}
}
