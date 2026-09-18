<?php
/**
 * SPDX-FileCopyrightText: 2026 CDrive contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

return [
	'routes' => [
		['name' => 'config#save', 'url' => '/config', 'verb' => 'POST'],
		['name' => 'config#clearProxyBans', 'url' => '/config/clear-proxy-bans', 'verb' => 'POST'],
	]
];
