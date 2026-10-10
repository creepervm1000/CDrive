<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 CDrive contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Cdrive\Controller;

use OC\Http\Client\CdriveEgressGuard;
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

	/**
	 * Every form of the admin page posts only its own fields. A setting that
	 * was not submitted (null) is left untouched, so saving one section can
	 * never reset another one.
	 */
	#[AuthorizedAdminSetting(settings: Admin::class)]
	public function save(
		?string $mode = null,
		?string $rulesOff = null,
		?string $allowSelf = null,
		?string $logging = null,
		?string $denylist = null,
		?string $allowedHosts = null,
		?string $appAllowlist = null,
		?string $appDenylist = null,
		?string $appBypass = null,
		?string $proxyList = null,
		?string $appProxy = null,
		?string $feedUrl = null,
		?string $lookupServer = null,
	): RedirectResponse {
		if ($mode !== null) {
			$this->config->setSystemValue('cdrive_egress_mode', CdriveEgressGuard::normalizeMode($mode));
		}
		if ($rulesOff !== null) {
			$this->config->setSystemValue('cdrive_egress_disabled', strtolower(trim($rulesOff)) === 'on');
		}
		if ($allowSelf !== null) {
			$this->config->setSystemValue('cdrive_egress_allow_self', strtolower(trim($allowSelf)) !== 'off');
		}
		if ($logging !== null) {
			$this->config->setSystemValue('cdrive_egress_log', strtolower(trim($logging)) !== 'off');
		}
		if ($denylist !== null) {
			$this->config->setSystemValue('cdrive_egress_denylist', self::lines($denylist));
		}
		if ($allowedHosts !== null) {
			$this->config->setSystemValue('cdrive_egress_allowed_hosts', self::lines($allowedHosts));
		}
		if ($appAllowlist !== null) {
			$this->config->setSystemValue('cdrive_egress_app_allowlist', self::lines(strtolower($appAllowlist)));
		}
		if ($appDenylist !== null) {
			$this->config->setSystemValue('cdrive_egress_app_denylist', self::lines(strtolower($appDenylist)));
		}
		if ($appBypass !== null) {
			$this->config->setSystemValue('cdrive_egress_app_bypass', self::lines(strtolower($appBypass)));
		}
		if ($proxyList !== null) {
			$this->config->setSystemValue('cdrive_proxy_list', self::lines($proxyList));
		}
		if ($appProxy !== null) {
			$this->config->setSystemValue('cdrive_app_proxy', self::mapLines($appProxy));
		}
		if ($feedUrl !== null) {
			$this->config->setSystemValue('announcements.feed_url', trim($feedUrl));
		}
		if ($lookupServer !== null) {
			$this->config->setSystemValue('lookup_server', trim($lookupServer));
		}

		return $this->backToSettings();
	}

	#[AuthorizedAdminSetting(settings: Admin::class)]
	public function clearProxyBans(): RedirectResponse {
		$this->config->deleteAppValue('cdrive_egress', 'proxy_banned');
		$this->config->deleteAppValue('cdrive_egress', 'proxy_failures');

		return $this->backToSettings();
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

		return $this->backToSettings();
	}

	private function backToSettings(): RedirectResponse {
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
