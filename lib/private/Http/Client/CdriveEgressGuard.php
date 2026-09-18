<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 CDrive contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * CDrive egress firewall (Graphene-style, for the webapp): private by
 * default. Every outbound HTTP request made through OCP\Http\Client is
 * checked here before any packet leaves the server.
 *
 * - `cdrive_egress_denylist` (list of domains): always blocked. An entry
 *   blocks the domain itself *and* all of its subdomains.
 * - `cdrive_egress_mode`: 'allowlist' (default) or 'denylist'.
 *   In allowlist mode nothing leaves the server unless the destination
 *   host is in `cdrive_egress_allowed_hosts` (domain + subdomains) or the
 *   calling app is in `cdrive_egress_app_allowlist`. The admin must
 *   explicitly allow traffic. In denylist mode only the denylist applies.
 *
 * See config/config.sample.php for documented examples.
 *
 * Network log (admin UI: Administration -> CDrive):
 * - Every blocked request is logged here with no response attached.
 * - Allowed requests are logged by the trafficLogger() Guzzle middleware
 *   with method, URL, calling app, HTTP status, duration and proxy used.
 * - `cdrive_egress_log` (default true) toggles logging;
 *   `cdrive_egress_log_max` (default 500) caps the ring buffer.
 *   NOTE: logged URLs can contain tokens/credentials from query strings.
 *   The log is only visible to admins.
 *
 * Per-app forced proxying (anti-tracking):
 * - `cdrive_proxy_list`: global list of HTTP/HTTPS proxy URLs.
 * - `cdrive_app_proxy`: map of app id => true/'auto' (random proxy from the
 *   global list) or a specific proxy URL. Forced: every request of that app
 *   that passed the egress check above goes through the proxy.
 * - Failover: when a proxied request fails to connect, one retry is made
 *   through a different random proxy. A proxy that fails 5 times within 6
 *   hours is nuked (added to the ban list) and never selected again until
 *   the admin clears it (`occ config:app:delete cdrive_egress proxy_banned`).
 *   Successful requests reset that proxy's failure counter.
 */

namespace OC\Http\Client;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use OCP\Http\Client\LocalServerException;
use OCP\IConfig;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;

class CdriveEgressGuard {
	public const MODE_ALLOWLIST = 'allowlist';
	public const MODE_DENYLIST = 'denylist';

	/** Proxy failures within this window count towards nuking. */
	private const PROXY_FAILURE_WINDOW = 21600; // 6 hours
	/** Failures within the window after which a proxy is nuked. */
	private const PROXY_FAILURE_THRESHOLD = 5;

	private const APP_VALUES_APP = 'cdrive_egress';

	public function __construct(
		private IConfig $config,
		private ?LoggerInterface $logger = null,
	) {
	}

	/**
	 * Policy denial. Thrown as a Guzzle ConnectException (rather than
	 * LocalServerException) on purpose: every existing caller already
	 * treats connection failures as "offline / try later", so a blocked
	 * request degrades gracefully instead of 500ing. The message still
	 * says exactly why it was blocked. Blocked requests are logged by
	 * the caller (see check()) before this is thrown.
	 */
	private function denied(string $uri, string $message): ConnectException {
		if ($uri === '') {
			$uri = 'http://localhost/';
		}
		return new ConnectException($message, new GuzzleRequest('GET', $uri));
	}

	/**
	 * Check a single outbound URI. Throws when the request must not leave.
	 *
	 * @throws ConnectException when blocked by policy (degrades gracefully)
	 * @throws LocalServerException when no host can be detected at all
	 */
	public function check(string $uri): void {
		// Master bypass: every security rule off. The admin was warned.
		if ($this->config->getSystemValue('cdrive_egress_disabled', false) ? true : false) {
			return;
		}
		$host = parse_url($uri, PHP_URL_HOST);
		if (!is_string($host) || $host === '') {
			throw new LocalServerException('CDrive egress blocked: could not detect any host');
		}
		$host = strtolower(rtrim($host, '.'));

		// Per-app kill switch: a listed app gets no network at all,
		// regardless of any allow rule below. A bypassed app skips every
		// rule below (but never the kill switch). Context is detected
		// lazily so requests pay for a backtrace only when a list is used.
		$killList = $this->getDomainList('cdrive_egress_app_denylist');
		$bypassList = $this->getDomainList('cdrive_egress_app_bypass');
		if ($killList !== [] || $bypassList !== []) {
			$ctx = $this->detectContext();
			if (in_array($ctx['app'], $killList, true)) {
				$this->log('CDrive egress denied by per-app kill switch', $host, $ctx['app']);
				$this->logTraffic($ctx['method'], $ctx['app'], $uri, 'blocked', null, null, null, 'app kill switch: "' . $ctx['app'] . '"');
				throw $this->denied($uri, 'CDrive egress blocked: app "' . $ctx['app'] . '" is kill-switched (cdrive_egress_app_denylist)');
			}
			if (in_array($ctx['app'], $bypassList, true)) {
				return;
			}
		}

		foreach ($this->getDomainList('cdrive_egress_denylist') as $entry) {
			if ($this->hostMatches($host, $entry)) {
				$this->log('CDrive egress denied by denylist', $host, $entry);
				$this->logBlocked($uri, 'denylist: ' . $entry);
				throw $this->denied($uri, 'CDrive egress blocked: host "' . $host . '" is denied by cdrive_egress_denylist');
			}
		}

		if ($this->config->getSystemValueString('cdrive_egress_mode', self::MODE_ALLOWLIST) !== self::MODE_ALLOWLIST) {
			return;
		}

		foreach ($this->getDomainList('cdrive_egress_allowed_hosts') as $entry) {
			if ($this->hostMatches($host, $entry)) {
				return;
			}
		}

		// The instance calling itself (cron callbacks, local federation,
		// notify_push, public-URL previews...) is allowed automatically
		// unless the admin turned that off. The denylist above still wins.
		if ($this->config->getSystemValue('cdrive_egress_allow_self', true) ? true : false) {
			foreach ($this->getSelfHosts() as $self) {
				if ($this->hostMatches($host, $self)) {
					return;
				}
			}
		}

		$app = $this->detectCallingApp();
		if (in_array($app, $this->getDomainList('cdrive_egress_app_allowlist'), true)) {
			return;
		}

		$this->log('CDrive egress denied by allowlist', $host, $app);
		$this->logBlocked($uri, 'allowlist: app "' . $app . '" not allowed', $app);
		throw $this->denied($uri, 'CDrive egress blocked: app "' . $app . '" may not contact host "' . $host . '" (allow it via cdrive_egress_app_allowlist or cdrive_egress_allowed_hosts)');
	}

	/**
	 * Log a blocked request (no response exists by definition).
	 */
	private function logBlocked(string $uri, string $reason, ?string $app = null): void {
		$ctx = $this->detectContext();
		$this->logTraffic($ctx['method'], $app ?? $ctx['app'], $uri, 'blocked', null, null, null, $reason);
	}

	/**
	 * Exact match or any subdomain match (case-insensitive).
	 */
	public function hostMatches(string $host, string $entry): bool {
		$entry = strtolower(rtrim(trim($entry), '.'));
		if ($entry === '') {
			return false;
		}
		return $host === $entry || str_ends_with($host, '.' . $entry);
	}

	/**
	 * Best-effort detection of the app that initiated the request.
	 * Returns the app id (e.g. 'dav', 'theming') or 'core'.
	 */
	public function detectCallingApp(): string {
		return $this->detectContext()['app'];
	}

	private const CLIENT_METHODS = ['get' => true, 'head' => true, 'post' => true, 'put' => true, 'patch' => true, 'delete' => true, 'options' => true, 'request' => true, 'getasync' => true, 'headasync' => true, 'postasync' => true, 'putasync' => true, 'deleteasync' => true, 'optionsasync' => true, 'sendrequest' => true];

	/**
	 * Best-effort detection of calling app AND HTTP method in one pass.
	 *
	 * @return array{app: string, method: string}
	 */
	public function detectContext(): array {
		$app = 'core';
		$method = '';
		foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) as $frame) {
			$class = $frame['class'] ?? '';
			$function = $frame['function'] ?? '';
			$functionLower = strtolower($function);
			if ($class !== '') {
				if ($class === 'OC\\Http\\Client\\Client' && isset(self::CLIENT_METHODS[$functionLower])) {
					if ($method === '') {
						$method = $functionLower === 'sendrequest' ? 'SEND' : strtoupper((string)preg_replace('/async$/i', '', $functionLower));
					}
					continue;
				}
				// Skip our own HTTP client stack.
				if (str_starts_with($class, 'OC\\Http\\Client\\')) {
					continue;
				}
			}
			if ($app === 'core') {
				// File path first: the app directory is the authoritative
				// app id (namespaces don't always match it, e.g.
				// OCA\WeatherStatus lives in apps/weather_status/).
				$file = $frame['file'] ?? '';
				if ($file !== '' && preg_match('#/(?:apps|custom_apps|apps-extra)/([^/]+)/#', $file, $m)) {
					$app = strtolower($m[1]);
				} elseif ($class !== '' && str_starts_with($class, 'OCA\\')) {
					// Namespaced class or function call (no class frame).
					$parts = explode('\\', $class !== '' ? $class : $function);
					if (isset($parts[1]) && $parts[1] !== '') {
						$app = strtolower($parts[1]);
					}
				} elseif ($class === '' && str_starts_with($function, 'OCA\\')) {
					$parts = explode('\\', $function);
					if (isset($parts[1]) && $parts[1] !== '') {
						$app = strtolower($parts[1]);
					}
				}
			}
			if ($app !== 'core' && $method !== '') {
				break;
			}
		}
		return ['app' => $app, 'method' => $method === '' ? 'REQUEST' : $method];
	}

	/**
	 * Hostnames the instance itself answers on: overwrite.cli.url,
	 * overwritehost and trusted_domains. Ports are stripped.
	 *
	 * @return list<string>
	 */
	public function getSelfHosts(): array {
		$hosts = [];
		$cliUrl = $this->config->getSystemValueString('overwrite.cli.url', '');
		if ($cliUrl !== '') {
			$h = parse_url($cliUrl, PHP_URL_HOST);
			if (is_string($h) && $h !== '') {
				$hosts[] = strtolower(rtrim($h, '.'));
			}
		}
		$overwriteHost = $this->config->getSystemValueString('overwritehost', '');
		if ($overwriteHost !== '') {
			$hosts[] = strtolower(rtrim($overwriteHost, '.'));
		}
		$trusted = $this->config->getSystemValue('trusted_domains', []);
		if (is_array($trusted)) {
			foreach ($trusted as $entry) {
				if (!is_string($entry) || trim($entry) === '') {
					continue;
				}
				$entry = strtolower(trim($entry));
				// Strip a single :port suffix (leave IPv6 literals alone).
				if (substr_count($entry, ':') === 1) {
					$entry = explode(':', $entry)[0];
				}
				$entry = trim($entry, '[]');
				if ($entry !== '') {
					$hosts[] = rtrim($entry, '.');
				}
			}
		}
		return array_values(array_unique($hosts));
	}

	/**
	 * @return list<string>
	 */
	private function getDomainList(string $key): array {
		$value = $this->config->getSystemValue($key, []);
		if (!is_array($value)) {
			return [];
		}
		$list = [];
		foreach ($value as $entry) {
			if (is_string($entry) && trim($entry) !== '') {
				$list[] = strtolower(rtrim(trim($entry), '.'));
			}
		}
		return $list;
	}

	private function log(string $message, string $host, string $detail): void {
		$this->logger?->info($message . ' (host: {host}, detail: {detail})', [
			'app' => 'cdrive_egress',
			'host' => $host,
			'detail' => $detail,
		]);
	}

	/**
	 * Whether any per-app proxy is configured at all. Lets callers skip
	 * the (costly) calling-app detection when the feature is unused.
	 */
	public function hasForcedProxies(): bool {
		$map = $this->config->getSystemValue('cdrive_app_proxy', []);
		return is_array($map) && $map !== [];
	}

	/**
	 * Forced proxy for an app, or null when the app has no proxy configured.
	 *
	 * @throws ConnectException when the app requires a proxy but no
	 * working proxy is available (fail closed: no direct connection).
	 */
	public function getProxyForApp(string $app): ?string {
		$map = $this->config->getSystemValue('cdrive_app_proxy', []);
		if (!is_array($map)) {
			return null;
		}
		$lookup = [];
		foreach ($map as $key => $value) {
			if (is_string($key)) {
				$lookup[strtolower($key)] = $value;
			}
		}
		if (!array_key_exists(strtolower($app), $lookup)) {
			return null;
		}
		$spec = $lookup[strtolower($app)];
		if ($spec === true || (is_string($spec) && strtolower($spec) === 'auto')) {
			$pick = $this->pickRandomProxy();
			if ($pick === null) {
				throw $this->denied('', 'CDrive: app "' . $app . '" requires a proxy but no working proxy is available (configure cdrive_proxy_list)');
			}
			return $pick;
		}
		if (is_string($spec) && trim($spec) !== '') {
			$url = trim($spec);
			if (!$this->isProxyBanned($url)) {
				return $url;
			}
			$pick = $this->pickRandomProxy($url);
			if ($pick === null) {
				throw $this->denied('', 'CDrive: pinned proxy for app "' . $app . '" is nuked and no replacement proxy is available');
			}
			return $pick;
		}
		return null;
	}

	/**
	 * Global proxy list minus nuked (banned) proxies.
	 *
	 * @return list<string>
	 */
	public function getEffectiveProxyList(): array {
		$value = $this->config->getSystemValue('cdrive_proxy_list', []);
		if (!is_array($value)) {
			return [];
		}
		$list = [];
		foreach ($value as $entry) {
			if (is_string($entry) && trim($entry) !== '' && !$this->isProxyBanned(trim($entry))) {
				$list[] = trim($entry);
			}
		}
		return array_values($list);
	}

	/**
	 * Random working proxy, optionally excluding one. Null when none left.
	 */
	public function pickRandomProxy(?string $exclude = null): ?string {
		$list = $this->getEffectiveProxyList();
		if ($exclude !== null) {
			$list = array_values(array_filter($list, fn (string $p) => $p !== $exclude));
		}
		if ($list === []) {
			return null;
		}
		return $list[random_int(0, count($list) - 1)];
	}

	public function isProxyBanned(string $proxy): bool {
		$banned = $this->readJsonAppValue('proxy_banned');
		return isset($banned[$proxy]);
	}

	/**
	 * Record a connection failure. Nukes the proxy after 5 failures
	 * within 6 hours.
	 */
	public function recordProxyFailure(string $proxy): void {
		$now = time();
		$failures = $this->readJsonAppValue('proxy_failures');
		$stamps = $failures[$proxy] ?? [];
		if (!is_array($stamps)) {
			$stamps = [];
		}
		$stamps = array_values(array_filter($stamps, fn ($ts) => is_int($ts) && $ts > $now - self::PROXY_FAILURE_WINDOW));
		$stamps[] = $now;
		$failures[$proxy] = $stamps;
		$this->writeJsonAppValue('proxy_failures', $failures);
		if (count($stamps) >= self::PROXY_FAILURE_THRESHOLD) {
			$banned = $this->readJsonAppValue('proxy_banned');
			if (!isset($banned[$proxy])) {
				$banned[$proxy] = $now;
				$this->writeJsonAppValue('proxy_banned', $banned);
				$this->logger?->warning('CDrive nuked proxy {proxy} after {count} failures within 6 hours', [
					'app' => 'cdrive_egress',
					'proxy' => $proxy,
					'count' => count($stamps),
				]);
			}
		}
	}

	/**
	 * Record a success through a proxy: resets its failure counter.
	 */
	public function recordProxySuccess(string $proxy): void {
		$failures = $this->readJsonAppValue('proxy_failures');
		if (isset($failures[$proxy])) {
			unset($failures[$proxy]);
			$this->writeJsonAppValue('proxy_failures', $failures);
		}
	}

	/**
	 * Guzzle middleware: log every allowed request with its response.
	 * No-op when logging is disabled.
	 */
	public function trafficLogger(): callable {
		return function (callable $handler): callable {
			return function (RequestInterface $request, array $options) use ($handler) {
				if (!$this->isLoggingEnabled()) {
					return $handler($request, $options);
				}
				$ctx = $this->detectContext();
				$start = microtime(true);
				$proxy = $options['cdrive_proxy_used'] ?? null;
				return $handler($request, $options)->then(
					function ($response) use ($ctx, $request, $start, $proxy) {
						$code = method_exists($response, 'getStatusCode') ? $response->getStatusCode() : null;
						$this->logTraffic($ctx['method'], $ctx['app'], (string)$request->getUri(), 'allowed', $code, (int)round((microtime(true) - $start) * 1000), is_string($proxy) ? $proxy : null, null);
						return $response;
					},
					function ($reason) use ($ctx, $request, $start, $proxy) {
						$this->logTraffic($ctx['method'], $ctx['app'], (string)$request->getUri(), 'error', null, (int)round((microtime(true) - $start) * 1000), is_string($proxy) ? $proxy : null, $this->shortError($reason));
						throw $reason;
					}
				);
			};
		};
	}

	/**
	 * Append one entry to the ring buffer. Blocked entries carry no
	 * response data by definition.
	 */
	public function logTraffic(string $method, string $app, string $uri, string $status, ?int $code, ?int $ms, ?string $proxy, ?string $error): void {
		if (!$this->isLoggingEnabled()) {
			return;
		}
		$max = $this->config->getSystemValueInt('cdrive_egress_log_max', 500);
		if ($max < 1) {
			return;
		}
		if ($max > 5000) {
			$max = 5000;
		}
		$raw = $this->config->getAppValue(self::APP_VALUES_APP, 'netlog', '[]');
		$log = json_decode($raw, true);
		if (!is_array($log)) {
			$log = [];
		}
		array_unshift($log, [
			't' => time(),
			'app' => $app,
			'method' => $method,
			'url' => $uri,
			'status' => $status,
			'code' => $code,
			'ms' => $ms,
			'proxy' => $proxy,
			'error' => $error !== null ? mb_substr($error, 0, 200) : null,
		]);
		$this->config->setAppValue(self::APP_VALUES_APP, 'netlog', json_encode(array_slice($log, 0, $max)));
	}

	public function isLoggingEnabled(): bool {
		return $this->config->getSystemValue('cdrive_egress_log', true) ? true : false;
	}

	/** @return list<array<string, mixed>> newest first */
	public function getTrafficLog(int $limit = 100): array {
		$raw = $this->config->getAppValue(self::APP_VALUES_APP, 'netlog', '[]');
		$log = json_decode($raw, true);
		if (!is_array($log)) {
			return [];
		}
		return array_slice(array_values($log), 0, max(1, $limit));
	}

	private function shortError(mixed $reason): string {
		if ($reason instanceof \Throwable) {
			return (new \ReflectionClass($reason))->getShortName() . ': ' . $reason->getMessage();
		}
		return 'request failed';
	}

	/**
	 * Guzzle middleware: on connection failure through a forced proxy,
	 * retry once through a different random proxy.
	 */
	public function retryWithFreshProxy(): callable {
		return function (callable $handler): callable {
			return function (RequestInterface $request, array $options) use ($handler) {
				$proxy = $options['cdrive_proxy_used'] ?? null;
				$promise = $handler($request, $options);
				if (!is_string($proxy) || $proxy === '') {
					return $promise;
				}
				return $promise->then(
					function ($response) use ($proxy) {
						$this->recordProxySuccess($proxy);
						return $response;
					},
					function ($reason) use ($request, $options, $handler, $proxy) {
						if (!($reason instanceof ConnectException)) {
							throw $reason;
						}
						$this->recordProxyFailure($proxy);
						$next = $this->pickRandomProxy($proxy);
						if ($next === null) {
							throw $reason;
						}
						$options['proxy'] = ['http' => $next, 'https' => $next];
						$options['cdrive_proxy_used'] = $next;
						return $handler($request, $options)->then(
							function ($response) use ($next) {
								$this->recordProxySuccess($next);
								return $response;
							},
							function ($retryReason) use ($next) {
								if ($retryReason instanceof ConnectException) {
									$this->recordProxyFailure($next);
								}
								throw $retryReason;
							}
						);
					}
				);
			};
		};
	}

	/**
	 * @return array<string, mixed>
	 */
	private function readJsonAppValue(string $key): array {
		$raw = $this->config->getAppValue(self::APP_VALUES_APP, $key, '[]');
		$decoded = json_decode($raw, true);
		return is_array($decoded) ? $decoded : [];
	}

	private function writeJsonAppValue(string $key, array $value): void {
		$this->config->setAppValue(self::APP_VALUES_APP, $key, json_encode($value));
	}
}
