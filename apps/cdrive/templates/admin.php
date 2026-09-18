<?php
/**
 * SPDX-FileCopyrightText: 2026 CDrive contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
script('cdrive', 'admin');
/** @var array $_ */
?>

<div class="section">
	<h2><?php p($l->t('CDrive instance')); ?></h2>
	<p><?php p($l->t('Reinit the running instance without touching the host: resets PHP OPcache, flushes APCu, clears the stat cache and regenerates all themed CSS/icons. This does not restart php-fpm or the machine.')); ?></p>
	<?php if (!empty($_['lastReinit'])) {
		?>
	<p><?php p($l->t('Last reinit: %s', date('Y-m-d H:i:s', (int)$_['lastReinit']))); ?></p>
	<?php
	} ?>
	<form action="<?php p($_['reinitUrl']); ?>" method="POST">
		<input type="hidden" name="requesttoken" value="<?php p($_['requesttoken']) ?>">
		<input type="submit" class="button primary" value="<?php p($l->t('Reinit instance')); ?>">
	</form>
</div>

<div class="section">
	<h2><?php p($l->t('CDrive network log')); ?></h2>
	<?php if (!$_['logging']) {
		?>
	<p><?php p($l->t('Logging is off. Enable it above to record requests.')); ?></p>
	<?php
	} elseif ($_['netlog'] === []) {
		?>
	<p><?php p($l->t('No requests logged yet.')); ?></p>
	<?php
	} else {
		?>
	<p><?php p($l->t('Newest first. Blocked rows have no response.')); ?></p>
	<table class="grid">
		<thead><tr>
			<th><?php p($l->t('Time')); ?></th>
			<th><?php p($l->t('App')); ?></th>
			<th><?php p($l->t('Method')); ?></th>
			<th><?php p($l->t('URL')); ?></th>
			<th><?php p($l->t('Result')); ?></th>
			<th><?php p($l->t('Code')); ?></th>
			<th><?php p($l->t('ms')); ?></th>
			<th><?php p($l->t('Proxy')); ?></th>
		</tr></thead>
		<tbody>
		<?php foreach ($_['netlog'] as $row) {
			?>
		<tr>
			<td><?php p(date('H:i:s', (int)($row['t'] ?? 0))); ?></td>
			<td><code><?php p($row['app'] ?? ''); ?></code></td>
			<td><?php p($row['method'] ?? ''); ?></td>
			<td style="max-width:420px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?php p($row['url'] ?? ''); ?>"><code><?php p($row['url'] ?? ''); ?></code></td>
			<td><?php p($row['status'] ?? ''); ?><?php if (!empty($row['error'])) {
				?> (<?php p($row['error']); ?>)<?php
			} ?></td>
			<td><?php p(isset($row['code']) && $row['code'] !== null ? (string)$row['code'] : '-'); ?></td>
			<td><?php p(isset($row['ms']) && $row['ms'] !== null ? (string)$row['ms'] : '-'); ?></td>
			<td><?php p(!empty($row['proxy']) ? $row['proxy'] : '-'); ?></td>
		</tr>
		<?php
		} ?>
		</tbody>
	</table>
	<?php
	} ?>
</div>

<div class="section">
	<h2><?php p($l->t('CDrive egress firewall')); ?></h2>
	<p><?php p($l->t('Private by default: outbound requests are blocked unless allowed below. A denied domain blocks that domain and all its subdomains.')); ?></p>
	<form action="<?php p($_['saveUrl']); ?>" method="POST">
		<input type="hidden" name="requesttoken" value="<?php p($_['requesttoken']) ?>">
		<p>
			<label for="cdrive-mode"><?php p($l->t('Mode')); ?></label><br>
			<select id="cdrive-mode" name="mode">
				<option value="allowlist" <?php if ($_['mode'] === 'allowlist') {
	p('selected');
} ?>><?php p($l->t('Allowlist (block everything not allowed)')); ?></option>
				<option value="denylist" <?php if ($_['mode'] === 'denylist') {
	p('selected');
} ?>><?php p($l->t('Denylist only')); ?></option>
			</select>
		</p>
		<p>
			<label for="cdrive-self"><?php p($l->t('Requests to this instance itself')); ?></label><br>
			<select id="cdrive-self" name="allowSelf">
				<option value="on" <?php if ($_['allowSelf']) {
	p('selected');
} ?>><?php p($l->t('Allow automatically')); ?></option>
				<option value="off" <?php if (!$_['allowSelf']) {
	p('selected');
} ?>><?php p($l->t('Treat like any other host')); ?></option>
			</select>
		</p>
		<p>
			<label for="cdrive-logging"><?php p($l->t('Network log')); ?></label><br>
			<select id="cdrive-logging" name="logging">
				<option value="on" <?php if ($_['logging']) {
	p('selected');
} ?>><?php p($l->t('On (log requests, responses and blocks)')); ?></option>
				<option value="off" <?php if (!$_['logging']) {
	p('selected');
} ?>><?php p($l->t('Off')); ?></option>
			</select>
		</p>
		<p>
			<label for="cdrive-denylist"><?php p($l->t('Denied domains (one per line, subdomains included)')); ?></label><br>
			<textarea id="cdrive-denylist" name="denylist" rows="4" cols="60" placeholder="tracker.example"><?php p($_['denylist']); ?></textarea>
		</p>
		<p>
			<label for="cdrive-allowed"><?php p($l->t('Allowed hosts (one per line, subdomains included)')); ?></label><br>
			<textarea id="cdrive-allowed" name="allowedHosts" rows="4" cols="60"><?php p($_['allowedHosts']); ?></textarea>
		</p>
		<p>
			<label for="cdrive-apps"><?php p($l->t('Apps allowed to reach the network (one app id per line, e.g. dav)')); ?></label><br>
			<textarea id="cdrive-apps" name="appAllowlist" rows="4" cols="60"><?php p($_['appAllowlist']); ?></textarea>
		</p>
		<input type="hidden" name="proxyList" value="<?php p($_['proxyList']); ?>">
		<input type="hidden" name="appProxy" value="<?php p($_['appProxy']); ?>">
		<input type="hidden" name="feedUrl" value="<?php p($_['feedUrl']); ?>">
		<input type="hidden" name="lookupServer" value="<?php p($_['lookupServer']); ?>">
		<input type="submit" class="button primary" value="<?php p($l->t('Save firewall settings')); ?>">
	</form>
</div>

<div class="section">
	<h2><?php p($l->t('CDrive per-app proxy')); ?></h2>
	<p><?php p($l->t('Force an allowed app through a proxy so destinations never see the server IP. Format per line: appid=auto (random from the pool) or appid=http://proxy:port. Failed proxies retry once elsewhere; 5 failures in 6 hours nukes the proxy.')); ?></p>
	<form action="<?php p($_['saveUrl']); ?>" method="POST">
		<input type="hidden" name="requesttoken" value="<?php p($_['requesttoken']) ?>">
		<input type="hidden" name="mode" value="<?php p($_['mode']); ?>">
		<input type="hidden" name="denylist" value="<?php p($_['denylist']); ?>">
		<input type="hidden" name="allowedHosts" value="<?php p($_['allowedHosts']); ?>">
		<input type="hidden" name="appAllowlist" value="<?php p($_['appAllowlist']); ?>">
		<input type="hidden" name="logging" value="<?php p($_['logging'] ? 'on' : 'off'); ?>">
		<input type="hidden" name="allowSelf" value="<?php p($_['allowSelf'] ? 'on' : 'off'); ?>">
		<p>
			<label for="cdrive-proxylist"><?php p($l->t('Global proxy pool (one URL per line)')); ?></label><br>
			<textarea id="cdrive-proxylist" name="proxyList" rows="4" cols="60" placeholder="http://proxy.internal:8080"><?php p($_['proxyList']); ?></textarea>
		</p>
		<p>
			<label for="cdrive-appproxy"><?php p($l->t('Per-app selection (one per line, e.g. weather_status=auto)')); ?></label><br>
			<textarea id="cdrive-appproxy" name="appProxy" rows="4" cols="60"><?php p($_['appProxy']); ?></textarea>
		</p>
		<input type="hidden" name="feedUrl" value="<?php p($_['feedUrl']); ?>">
		<input type="hidden" name="lookupServer" value="<?php p($_['lookupServer']); ?>">
		<input type="submit" class="button primary" value="<?php p($l->t('Save proxy settings')); ?>">
	</form>
	<?php if ($_['proxyBanned'] !== []) {
		?>
	<h3><?php p($l->t('Nuked proxies')); ?></h3>
	<ul>
		<?php foreach ($_['proxyBanned'] as $proxy => $when) {
			?>
		<li><code><?php p($proxy); ?></code> (<?php p(date('Y-m-d H:i', (int)$when)); ?>,
			<?php p($l->t('%n failures', isset($_['proxyFailures'][$proxy]) && is_array($_['proxyFailures'][$proxy]) ? count($_['proxyFailures'][$proxy]) : 0)); ?>)</li>
		<?php
		} ?>
	</ul>
	<form action="<?php p($_['clearBansUrl']); ?>" method="POST">
		<input type="hidden" name="requesttoken" value="<?php p($_['requesttoken']) ?>">
		<input type="submit" class="button" value="<?php p($l->t('Clear bans and failure counters')); ?>">
	</form>
	<?php
	} ?>
</div>

<div class="section">
	<h2><?php p($l->t('CDrive telemetry opt-ins')); ?></h2>
	<p><?php p($l->t('Everything below is empty (disabled) by default. Set a URL to opt in.')); ?></p>
	<form action="<?php p($_['saveUrl']); ?>" method="POST">
		<input type="hidden" name="requesttoken" value="<?php p($_['requesttoken']) ?>">
		<input type="hidden" name="mode" value="<?php p($_['mode']); ?>">
		<input type="hidden" name="denylist" value="<?php p($_['denylist']); ?>">
		<input type="hidden" name="allowedHosts" value="<?php p($_['allowedHosts']); ?>">
		<input type="hidden" name="appAllowlist" value="<?php p($_['appAllowlist']); ?>">
		<input type="hidden" name="proxyList" value="<?php p($_['proxyList']); ?>">
		<input type="hidden" name="appProxy" value="<?php p($_['appProxy']); ?>">
		<input type="hidden" name="logging" value="<?php p($_['logging'] ? 'on' : 'off'); ?>">
		<input type="hidden" name="allowSelf" value="<?php p($_['allowSelf'] ? 'on' : 'off'); ?>">
		<p>
			<label for="cdrive-feed"><?php p($l->t('Announcements feed URL')); ?></label><br>
			<input id="cdrive-feed" type="text" name="feedUrl" size="60" value="<?php p($_['feedUrl']); ?>">
		</p>
		<p>
			<label for="cdrive-lookup"><?php p($l->t('Lookup server URL')); ?></label><br>
			<input id="cdrive-lookup" type="text" name="lookupServer" size="60" value="<?php p($_['lookupServer']); ?>">
		</p>
		<input type="submit" class="button primary" value="<?php p($l->t('Save telemetry settings')); ?>">
	</form>
</div>
