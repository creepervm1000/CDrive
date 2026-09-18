<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2026 CDrive contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * CDrive fork: subscription key endpoint disabled (no-op).
 */

namespace OCA\Support\Controller;

use OCA\Support\DetailManager;
use OCA\Support\Service\SubscriptionService;
use OCA\Support\Settings\Admin;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Constants;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserSession;
use OCP\Security\Events\GenerateSecurePasswordEvent;
use OCP\Security\ISecureRandom;
use OCP\Share\IManager;
use OCP\Share\IShare;
use Psr\Log\LoggerInterface;

class ApiController extends Controller {
	private Folder $userFolder;

	public function __construct(
		string $appName,
		IRequest $request,
		protected readonly IURLGenerator $urlGenerator,
		protected readonly SubscriptionService $subscriptionService,
		protected readonly DetailManager $detailManager,
		protected readonly IUserSession $userSession,
		protected readonly LoggerInterface $logger,
		protected readonly IL10N $l10n,
		protected readonly IManager $shareManager,
		protected readonly IEventDispatcher $eventDispatcher,
		protected readonly ISecureRandom $random,
		protected readonly ITimeFactory $timeFactory,
		protected readonly ?string $userId,
		IRootFolder $rootFolder,
	) {
		parent::__construct($appName, $request);

		$this->userFolder = $rootFolder->getUserFolder($this->userId);
	}

	#[AuthorizedAdminSetting(settings: Admin::class)]
	public function setSubscriptionKey(string $subscriptionKey): RedirectResponse {
		// CDrive: subscription system removed. Endpoint kept for route
		// compatibility but intentionally does nothing.
		return new RedirectResponse($this->urlGenerator->getAbsoluteURL($this->urlGenerator->linkToRoute('settings.AdminSettings.index', ['section' => 'support'])));
	}

	#[AuthorizedAdminSetting(settings: Admin::class)]
	public function generateSystemReport(): DataResponse {
		try {
			$directory = $this->userFolder->get('System information');
		} catch (NotFoundException $e) {
			try {
				$directory = $this->userFolder->newFolder('System information');
			} catch (\Exception $ex) {
				$this->logger->warning('Could not create folder "System information" to store generated report.', [
					'app' => 'support',
					'exception' => $e,
				]);
				$response = new DataResponse(['message' => $this->l10n->t('Could not create folder "System information" to store generated report.')]);
				$response->setStatus(Http::STATUS_INTERNAL_SERVER_ERROR);
				return $response;
			}
		}

		if (!($directory instanceof Folder)) {
			$this->logger->warning('Could not create folder "System information" to store generated report, a file exists with this name.', [
				'app' => 'support',
			]);
			$response = new DataResponse(['message' => $this->l10n->t('Could not create folder "System information" to store generated report, a file exists with this name.')]);
			$response->setStatus(Http::STATUS_INTERNAL_SERVER_ERROR);
			return $response;
		}

		$date = $this->timeFactory->getDateTime()->format('Y-m-d');
		$filename = $date . '.md';
		$filename = $directory->getNonExistingName($filename);

		try {
			$file = $directory->newFile($filename);
			$details = $this->detailManager->getRenderedDetails();
			$file->putContent($details);
		} catch (\Exception $e) {
			$this->logger->warning('Could not create file "' . $filename . '" to store generated report.', [
				'app' => 'support',
				'exception' => $e,
			]);
			$response = new DataResponse(['message' => $this->l10n->t('Could not create file "%s" to store generated report.', [ $filename ])]);
			$response->setStatus(Http::STATUS_INTERNAL_SERVER_ERROR);
			return $response;
		}

		try {
			$passwordEvent = new GenerateSecurePasswordEvent();
			$this->eventDispatcher->dispatchTyped($passwordEvent);
			$password = $passwordEvent->getPassword() ?? $this->random->generate(20);
			$share = $this->shareManager->newShare();
			$share->setNode($file);
			$share->setPermissions(Constants::PERMISSION_READ);
			$share->setShareType(IShare::TYPE_LINK);
			$share->setSharedBy($this->userId);
			$share->setPassword($password);

			if ($this->shareManager->shareApiLinkDefaultExpireDateEnforced()) {
				$expiry = $this->timeFactory->getDateTime();
				$expiry->add(new \DateInterval('P' . $this->shareManager->shareApiLinkDefaultExpireDays() . 'D'));
			} else {
				$expiry = $this->timeFactory->getDateTime();
				$expiry->add(new \DateInterval('P2W'));
			}

			$share->setExpirationDate($expiry);

			$share = $this->shareManager->createShare($share);
		} catch (\Exception $e) {
			$this->logger->warning('Could not share file "' . $filename . '".', [
				'app' => 'support',
				'exception' => $e,
			]);
			$response = new DataResponse(['message' => $this->l10n->t('Could not share file "%s". Nevertheless, you can find it in the folder "System information".', [$filename])]);
			$response->setStatus(Http::STATUS_INTERNAL_SERVER_ERROR);
			return $response;
		}

		return new DataResponse(
			[
				'link' => $this->urlGenerator->linkToRouteAbsolute('files_sharing.sharecontroller.showShare', ['token' => $share->getToken()]),
				'password' => $password,
			],
			Http::STATUS_CREATED
		);
	}
}
