<?php

declare(strict_types=1);

/*!
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Appstore\Controller;

use OC\App\AppStore\Bundles\BundleFetcher;
use OCA\AppAPI\Service\ExAppsPageService;
use OCA\Appstore\AppInfo\Application;
use OCP\App\IAppManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\Server;
use OCP\Util;

#[OpenAPI(scope: OpenAPI::SCOPE_IGNORE)]
final class PageController extends Controller {

	public function __construct(
		IRequest $request,
		private readonly IL10N $l10n,
		private readonly IConfig $config,
		private readonly IAppManager $appManager,
		private readonly IURLGenerator $urlGenerator,
		private readonly IInitialState $initialState,
		private readonly BundleFetcher $bundleFetcher,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	#[NoCSRFRequired]
	#[FrontpageRoute(verb: 'GET', url: '/settings/apps', root: '')]
	#[FrontpageRoute(verb: 'GET', url: '/settings/apps/{category}', defaults: ['category' => ''], root: '')]
	#[FrontpageRoute(verb: 'GET', url: '/settings/apps/{category}/{id}', defaults: ['category' => '', 'id' => ''], root: '')]
	public function viewApps(): TemplateResponse {
		$this->initialState->provideInitialState('appstoreEnabled', $this->config->getSystemValueBool('appstoreenabled', true));
		$this->initialState->provideInitialState('appstoreBundles', $this->getBundles());
		$this->initialState->provideInitialState('appstoreDeveloperDocs', $this->urlGenerator->linkToDocs('developer-manual'));
		// Do not refresh the App Store while rendering the page. A stale
		// apps.json cache can make isUpdateAvailable() wait for the remote
		// App Store (up to 120 seconds), while PHP-FPM is limited to 30
		// seconds. That turns an otherwise usable page into a blank 500.
		// The app list API loads immediately after the page and provides the
		// update information there.
		$this->initialState->provideInitialState('appstoreUpdateCount', 0);

		if ($this->appManager->isEnabledForAnyone('app_api')) {
			try {
				/**
				 * @psalm-suppress UndefinedClass AppAPI is shipped since 30.0.1
				 */
				Server::get(ExAppsPageService::class)->provideAppApiState($this->initialState);
			} catch (\Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface) {
				// nop
			}
		}

		$policy = new ContentSecurityPolicy();
		$policy->addAllowedImageDomain('https://usercontent.apps.nextcloud.com');

		$templateResponse = new TemplateResponse(Application::APP_ID, 'empty', ['pageTitle' => $this->l10n->t('App store')]);
		$templateResponse->setContentSecurityPolicy($policy);

		Util::addStyle(Application::APP_ID, 'main');
		Util::addScript(Application::APP_ID, 'main');

		return $templateResponse;
	}

	/**
	 * @return list<array{name: string, id: string, appIdentifiers: list<string>}>
	 */
	private function getBundles(): array {
		$result = [];
		$bundles = $this->bundleFetcher->getBundles();
		foreach ($bundles as $bundle) {
			$result[] = [
				'name' => $bundle->getName(),
				'id' => $bundle->getIdentifier(),
				'appIdentifiers' => $bundle->getAppIdentifiers()
			];
		}

		return $result;
	}
}
