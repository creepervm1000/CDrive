<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 CDrive contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Cdrive\AppInfo;

use OCA\Cdrive\Settings\Admin;
use OCA\Cdrive\Settings\Section;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Settings\IManager as ISettingsManager;

class Application extends App implements IBootstrap {
	public const APP_ID = 'cdrive';

	public function __construct() {
		parent::__construct(self::APP_ID);
	}

	#[\Override]
	public function register(IRegistrationContext $context): void {
	}

	#[\Override]
	public function boot(IBootContext $context): void {
		$container = $context->getAppContainer();
		$settingsManager = $container->get(ISettingsManager::class);
		$settingsManager->registerSection('admin', Section::class);
		$settingsManager->registerSetting('admin', Admin::class);
	}
}
