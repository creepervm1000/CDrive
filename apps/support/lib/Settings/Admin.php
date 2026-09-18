<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2026 CDrive contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * CDrive fork: subscription system removed. This settings page only renders
 * the free system report and community support sections; no subscription
 * key input, tiers or enterprise upsell.
 */

namespace OCA\Support\Settings;

use OCA\Support\Service\SubscriptionService;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\ServerVersion;
use OCP\Settings\IDelegatedSettings;

class Admin implements IDelegatedSettings {

	public function __construct(
		protected readonly IConfig $config,
		protected readonly IAppConfig $appConfig,
		protected readonly IUserManager $userManager,
		protected readonly IURLGenerator $urlGenerator,
		protected readonly SubscriptionService $subscriptionService,
		protected readonly ServerVersion $serverVersion,
	) {
	}

	#[\Override]
	public function getForm(): TemplateResponse {
		// CDrive: the template no longer consumes any subscription params.
		return new TemplateResponse('support', 'admin', []);
	}

	#[\Override]
	public function getSection(): string {
		return 'support';
	}

	/**
	 * @return int whether the form should be rather on the top or bottom of
	 *             the admin section. The forms are arranged in ascending order of the
	 *             priority values. It is required to return a value between 0 and 100.
	 *
	 * keep the server setting at the top, right after "server settings"
	 */
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
			'support' => ['.*'],
		];
	}
}
