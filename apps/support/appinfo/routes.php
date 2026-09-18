<?php

/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

return [
	'routes' => [
		['name' => 'api#setSubscriptionKey', 'url' => '/subscriptionKey', 'verb' => 'POST'],
		['name' => 'api#generateSystemReport', 'url' => '/generateSystemReport', 'verb' => 'POST'],
	]
];
