<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Support\AppInfo;

use OCA\Support\Capabilities;
use OCA\Support\Notification\Notifier;
use OCA\Support\Settings\Admin;
use OCA\Support\Settings\Section;
use OCA\Support\Subscription\SubscriptionAdapter;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\IConfig;
use OCP\Settings\IManager as ISettingsManager;
use OCP\Support\Subscription\Exception\AlreadyRegisteredException;
use OCP\Support\Subscription\IRegistry;
use Psr\Log\LoggerInterface;

class Application extends App implements IBootstrap {
	public const APP_ID = 'support';

	public function __construct() {
		parent::__construct(self::APP_ID);
	}

	#[\Override]
	public function register(IRegistrationContext $context): void {
		$context->registerCapability(Capabilities::class);
		$context->registerNotifierService(Notifier::class);
	}

	#[\Override]
	public function boot(IBootContext $context): void {
		$container = $context->getAppContainer();

		/* @var $registry IRegistry */
		$registry = $container->get(IRegistry::class);
		try {
			$registry->registerService(SubscriptionAdapter::class);
			if ($container->get(IConfig::class)->getAppValue('support', 'hide-app', 'no') !== 'yes') {
				$settingsManager = $container->get(ISettingsManager::class);
				$settingsManager->registerSetting('admin', Admin::class);
				$settingsManager->registerSection('admin', Section::class);
			}
		} catch (AlreadyRegisteredException $e) {
			$logger = $container->get(LoggerInterface::class);
			$logger->critical('Multiple subscription adapters are registered.', [
				'exception' => $e,
			]);
		}
	}
}
