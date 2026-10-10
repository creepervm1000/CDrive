#!/usr/bin/env php
<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 CDrive contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Regression checks for the CDrive egress firewall (CdriveEgressGuard and its
 * integration in OC\Http\Client\Client). Needs neither PHPUnit nor a database:
 *
 *     php scripts/test-cdrive-egress.php
 */

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use OC\Http\Client\CdriveEgressGuard;
use OC\Http\Client\Client;

// OC\Config refuses to read config files once output has started.
define('PHPUNIT_RUN', true);

$root = dirname(__DIR__);
require $root . '/3rdparty/autoload.php';
require $root . '/lib/composer/autoload.php';

/** An OCP interface whose methods run the closures given in $impl. */
function stub(string $interface, array $impl): object {
	static $counter = 0;
	$class = 'CdriveTestStub' . ++$counter;
	$methods = '';
	foreach ((new ReflectionClass($interface))->getMethods() as $method) {
		$params = [];
		foreach ($method->getParameters() as $p) {
			$param = ($p->getType() !== null ? $p->getType() . ' ' : '') . ($p->isVariadic() ? '...' : '') . '$' . $p->getName();
			if ($p->isDefaultValueAvailable()) {
				$param .= ' = ' . var_export($p->getDefaultValue(), true);
			}
			$params[] = $param;
		}
		$return = (string)$method->getReturnType();
		$call = '($this->impl[' . var_export($method->getName(), true) . '] ?? throw new \LogicException("not stubbed"))(...func_get_args())';
		$methods .= 'public function ' . $method->getName() . '(' . implode(', ', $params) . ')'
			. ($return !== '' ? ': ' . $return : '') . ' { ' . ($return === 'void' ? '' : 'return ') . $call . '; }' . "\n";
	}
	eval('class ' . $class . ' implements \\' . $interface . ' { public function __construct(public array $impl) {} ' . $methods . '}');
	return new $class($impl);
}

/** Real system config read from a temp config.php, with in-memory app values. */
class TestConfig extends \OC\AllConfig {
	public array $apps = [];

	public function getAppValue($appName, $key, $default = '') {
		return $this->apps[$appName][$key] ?? $default;
	}

	public function setAppValue($appName, $key, $value) {
		$this->apps[$appName][$key] = (string)$value;
	}

	public function deleteAppValue($appName, $key) {
		unset($this->apps[$appName][$key]);
	}
}

$tempDirs = [];
function tempDir(): string {
	global $tempDirs;
	$dir = sys_get_temp_dir() . '/cdrive-egress-test-' . bin2hex(random_bytes(4)) . '/';
	mkdir($dir, 0777, true);
	return $tempDirs[] = $dir;
}
register_shutdown_function(function () use (&$tempDirs): void {
	foreach ($tempDirs as $dir) {
		exec('rm -rf ' . escapeshellarg($dir));
	}
});

function makeConfig(array $values = []): TestConfig {
	$dir = tempDir();
	// Logging needs app config storage; tests that want it pass a stub explicitly.
	file_put_contents($dir . 'config.php', '<?php $CONFIG = ' . var_export($values + ['cdrive_egress_log' => false], true) . ';');
	return new TestConfig(new \OC\SystemConfig(new \OC\Config($dir)));
}

function makeClient(TestConfig $config, array $responses, array &$history): Client {
	$stack = HandlerStack::create(new MockHandler($responses));
	$stack->push(Middleware::history($history));
	return new Client(
		$config,
		stub(\OCP\ICertificateManager::class, [
			'getDefaultCertificatesBundlePath' => fn () => '/etc/ssl/certs/ca-certificates.crt',
			'getAbsoluteBundlePath' => fn () => '/etc/ssl/certs/ca-certificates.crt',
		]),
		new GuzzleHttp\Client(['handler' => $stack]),
		stub(\OCP\Security\IRemoteHostValidator::class, ['isValid' => fn () => true]),
		new Psr\Log\NullLogger(),
		new \OCP\ServerVersion(),
	);
}

$total = 0;
$failed = 0;
function check(string $name, callable $test): void {
	global $total, $failed;
	$total++;
	try {
		$ok = $test() === true;
		$note = '';
	} catch (\Throwable $e) {
		$ok = false;
		$note = ' (' . get_class($e) . ': ' . $e->getMessage() . ')';
	}
	$failed += $ok ? 0 : 1;
	echo ($ok ? 'ok    ' : 'FAIL  ') . $name . $note . "\n";
}

function blocked(callable $request): bool {
	try {
		$request();
	} catch (ConnectException) {
		return true;
	}
	return false;
}

function guardFor(array $values): CdriveEgressGuard {
	return new CdriveEgressGuard(makeConfig($values));
}

// --- Pure helpers -----------------------------------------------------------

check('normalizeMode: only an explicit denylist leaves allowlist mode', fn () =>
	CdriveEgressGuard::normalizeMode('allowlist') === 'allowlist'
	&& CdriveEgressGuard::normalizeMode(' DenyList ') === 'denylist'
	&& CdriveEgressGuard::normalizeMode('allowList') === 'allowlist'
	&& CdriveEgressGuard::normalizeMode('typo') === 'allowlist'
	&& CdriveEgressGuard::normalizeMode('') === 'allowlist');

check('normalizeHost: case, port, brackets and trailing dot', fn () =>
	CdriveEgressGuard::normalizeHost('Example.COM.') === 'example.com'
	&& CdriveEgressGuard::normalizeHost('[::1]') === '::1'
	&& CdriveEgressGuard::normalizeHost('[::1]:8080') === '::1'
	&& CdriveEgressGuard::normalizeHost('host:8080') === 'host'
	&& CdriveEgressGuard::normalizeHost('::1') === '::1');

check('redactUserInfo: credentials in URLs and proxies are masked', fn () =>
	CdriveEgressGuard::redactUserInfo('http://user:pass@proxy:8080') === 'http://***@proxy:8080'
	&& CdriveEgressGuard::redactUserInfo('user:pass@proxy:8080') === '***@proxy:8080'
	&& CdriveEgressGuard::redactUserInfo('http://u:p@ss@host/x') === 'http://***@host/x'
	&& CdriveEgressGuard::redactUserInfo('https://example.com/a?b=c@d') === 'https://example.com/a?b=c@d');

check('hostMatches: domain and subdomains only', function () {
	$g = guardFor([]);
	return $g->hostMatches('a.example.com', 'example.com')
		&& $g->hostMatches('example.com', 'EXAMPLE.com.')
		&& !$g->hostMatches('evil-example.com', 'example.com')
		&& $g->hostMatches('[::1]', '::1');
});

// --- Policy decisions -------------------------------------------------------

$allowlist = ['cdrive_egress_allowed_hosts' => ['allowed.example']];
check('allowlist: unknown host blocked, allowed host and subdomains pass', fn () =>
	blocked(fn () => guardFor($allowlist)->check('https://other.example/'))
	&& !blocked(fn () => guardFor($allowlist)->check('https://allowed.example/'))
	&& !blocked(fn () => guardFor($allowlist)->check('https://sub.allowed.example/')));

check('an unknown cdrive_egress_mode value fails closed', fn () =>
	blocked(fn () => guardFor(['cdrive_egress_mode' => 'allowList'])->check('https://other.example/'))
	&& blocked(fn () => guardFor(['cdrive_egress_mode' => 'typo'])->check('https://other.example/')));

check('denylist mode allows unknown hosts but the denylist still wins', fn () =>
	!blocked(fn () => guardFor(['cdrive_egress_mode' => 'denylist'])->check('https://other.example/'))
	&& blocked(fn () => guardFor(['cdrive_egress_mode' => 'denylist', 'cdrive_egress_denylist' => ['tracker.example']])->check('https://a.tracker.example/')));

check('entries pasted as URLs or wildcards are still effective', fn () =>
	blocked(fn () => guardFor(['cdrive_egress_mode' => 'denylist', 'cdrive_egress_denylist' => ['https://tracker.example/']])->check('https://a.tracker.example/'))
	&& blocked(fn () => guardFor(['cdrive_egress_mode' => 'denylist', 'cdrive_egress_denylist' => ['*.tracker.example']])->check('https://tracker.example/'))
	&& !blocked(fn () => guardFor(['cdrive_egress_allowed_hosts' => ['https://allowed.example/']])->check('https://allowed.example/')));

check('the instance itself is allowed (overwritehost with port, IPv6 trusted domain)', fn () =>
	!blocked(fn () => guardFor(['overwritehost' => 'cloud.example:8443'])->check('https://cloud.example/status.php'))
	&& !blocked(fn () => guardFor(['trusted_domains' => ['[::1]:8080']])->check('http://[::1]:8080/'))
	&& blocked(fn () => guardFor(['overwritehost' => 'cloud.example', 'cdrive_egress_allow_self' => false])->check('https://cloud.example/')));

// --- Redirects must pass the firewall (HTTP client) -------------------------

foreach ([
	'default settings' => [],
	'allow_local_remote_servers enabled' => ['allow_local_remote_servers' => true],
] as $label => $extra) {
	$cfg = $allowlist + $extra;
	$redirectToEvil = fn () => [new Response(302, ['Location' => 'https://evil.example/x']), new Response(200, [], 'evil')];

	check("redirect to a blocked host is refused ($label)", function () use ($cfg, $redirectToEvil) {
		$history = [];
		$client = makeClient(makeConfig($cfg), $redirectToEvil(), $history);
		return blocked(fn () => $client->get('https://allowed.example/start')) && count($history) === 1;
	});

	check("redirect is still refused when the caller passes allow_redirects ($label)", function () use ($cfg, $redirectToEvil) {
		$history = [];
		$client = makeClient(makeConfig($cfg), $redirectToEvil(), $history);
		return blocked(fn () => $client->get('https://allowed.example/start', ['allow_redirects' => ['max' => 3]]))
			&& blocked(fn () => makeClient(makeConfig($cfg), $redirectToEvil(), $history)->get('https://allowed.example/start', ['allow_redirects' => true]));
	});
}

check('redirect to an allowed host works and the caller\'s on_redirect still runs', function () use ($allowlist) {
	$history = [];
	$hits = 0;
	$client = makeClient(makeConfig($allowlist + ['allow_local_remote_servers' => true]), [
		new Response(302, ['Location' => 'https://sub.allowed.example/ok']),
		new Response(200, [], 'fine'),
	], $history);
	$response = $client->get('https://allowed.example/start', ['allow_redirects' => ['on_redirect' => function () use (&$hits) {
		$hits++;
	}]]);
	return $response->getStatusCode() === 200 && $hits === 1 && count($history) === 2;
});

check('allow_redirects => false stays disabled', function () use ($allowlist) {
	$history = [];
	$client = makeClient(makeConfig($allowlist), [new Response(302, ['Location' => 'https://evil.example/x'])], $history);
	return $client->get('https://allowed.example/start', ['allow_redirects' => false])->getStatusCode() === 302 && count($history) === 1;
});

// --- Calling app detection --------------------------------------------------

$tree = tempDir();
mkdir($tree . 'apps/weather_status/lib', 0777, true);
mkdir($tree . 'lib/private', 0777, true);
// Single-method job calling the client directly, invoked from core code.
file_put_contents($tree . 'apps/weather_status/lib/Job.php', '<?php namespace OCA\WeatherStatus; class Job { public function run(\OCP\Http\Client\IClient $c): mixed { return $c->get("https://x.example/"); } }');
file_put_contents($tree . 'lib/private/Runner.php', '<?php namespace OC\CdriveTest; class Runner { public function exec(object $job, \OCP\Http\Client\IClient $c): mixed { return $job->run($c); } }');
require_once $tree . 'apps/weather_status/lib/Job.php';
require_once $tree . 'lib/private/Runner.php';

check('app id comes from the directory (weather_status), not the namespace', function () use ($tree) {
	$history = [];
	$client = makeClient(makeConfig(['cdrive_egress_mode' => 'denylist', 'cdrive_egress_app_denylist' => ['weather_status']]), [new Response(200)], $history);
	try {
		(new \OC\CdriveTest\Runner())->exec(new \OCA\WeatherStatus\Job(), $client);
	} catch (ConnectException $e) {
		return str_contains($e->getMessage(), 'app "weather_status" is kill-switched');
	}
	return false;
});

check('app allowlist matches the directory id', function () {
	$history = [];
	$client = makeClient(makeConfig(['cdrive_egress_app_allowlist' => ['weather_status']]), [new Response(200)], $history);
	return (new \OC\CdriveTest\Runner())->exec(new \OCA\WeatherStatus\Job(), $client)->getStatusCode() === 200;
});

// --- Network log ------------------------------------------------------------

$store = [];
$appConfig = stub(\OCP\IAppConfig::class, [
	'getValueString' => function (string $app, string $key, string $default = '', bool $lazy = false) use (&$store) {
		return $store[$app][$key]['value'] ?? $default;
	},
	'setValueString' => function (string $app, string $key, string $value, bool $lazy = false, bool $sensitive = false) use (&$store) {
		$store[$app][$key] = ['value' => $value, 'lazy' => $lazy];
		return true;
	},
]);
$loggingConfig = fn (array $extra = []) => makeConfig($extra + ['cdrive_egress_log' => true]);

check('network log hides credentials and is stored lazily', function () use (&$store, $appConfig, $loggingConfig) {
	$guard = new CdriveEgressGuard($loggingConfig(), null, $appConfig);
	blocked(fn () => $guard->check('https://user:secret@blocked.example/p?x=1'));
	$guard->logTraffic('GET', 'core', 'https://a:b@ok.example/', 'error', null, 5, 'http://u:pw@proxy:3128', 'cURL error 7 for https://a:b@ok.example/');
	$entries = $guard->getTrafficLog();
	$raw = $store['cdrive_egress']['netlog']['value'] ?? '';
	return count($entries) === 2
		&& $entries[1]['url'] === 'https://***@blocked.example/p?x=1'
		&& $entries[0]['proxy'] === 'http://***@proxy:3128'
		&& !str_contains($raw, 'secret') && !str_contains($raw, 'pw@') && !str_contains($raw, 'a:b@')
		&& $store['cdrive_egress']['netlog']['lazy'] === true;
});

check('a failing log store never changes the verdict of a request', function () use ($loggingConfig) {
	$broken = stub(\OCP\IAppConfig::class, [
		'getValueString' => fn () => throw new \RuntimeException('database is down'),
		'setValueString' => fn () => throw new \RuntimeException('database is down'),
	]);
	$guard = new CdriveEgressGuard($loggingConfig(), null, $broken);
	return blocked(fn () => $guard->check('https://other.example/'));
});

check('log entries bound the stored URL length', function () use (&$store, $appConfig, $loggingConfig) {
	$store = [];
	$guard = new CdriveEgressGuard($loggingConfig(), null, $appConfig);
	$guard->logTraffic('GET', 'core', 'https://example.com/' . str_repeat('a', 5000), 'allowed', 200, 1, null, null);
	return mb_strlen($guard->getTrafficLog()[0]['url']) === 500;
});

echo "\n" . ($total - $failed) . "/$total checks passed\n";
exit($failed === 0 ? 0 : 1);
