<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Circles\Activity;

use OCA\Circles\Exceptions\FakeException;
use OCP\Activity\IEvent;

class ProviderSubjectCircleMember extends ProviderParser {
	/**
	 * @param IEvent $event
	 * @param array $params
	 * @param string $ownEvent
	 * @param string $othersEvent
	 */
	protected function parseMemberCircleEvent(
		IEvent $event,
		array $params,
		string $ownEvent,
		string $othersEvent,
	): void {
		$data = [
			'author' => $this->generateUserParameter($params['initiator'] ?? []),
			'circle' => $this->generateCircleParameter($params['circle']),
			'member' => $this->generateUserParameter($params['member'] ?? []),
			'external' => $this->generateExternalMemberParameter($params['member'] ?? []),
			'group' => $this->generateGroupParameter($params['member'] ?? []),
		];

		if ($this->isViewerTheAuthor($params['initiator'] ?? [], $this->activityManager->getCurrentUserId())) {
			$this->setSubject($event, $ownEvent, $data);

			return;
		}

		$this->setSubject($event, $othersEvent, $data);
	}

	/**
	 * @param IEvent $event
	 * @param array $params
	 *
	 * @throws FakeException
	 */
	public function parseSubjectCircleMemberJoin(
		IEvent $event,
		array $params,
	): void {
		if ($event->getSubject() !== 'member_circle_joined') {
			return;
		}

		$this->parseMemberCircleEvent(
			$event, $params,
			$this->l10n->t('You made {member} join {circle}'),
			$this->l10n->t('{author} made {member} join {circle}')
		);

		throw new FakeException();
	}

	/**
	 * @param IEvent $event
	 * @param array $params
	 *
	 * @throws FakeException
	 */
	public function parseSubjectCircleMemberAdd(
		IEvent $event,
		array $params,
	): void {
		if ($event->getSubject() !== 'member_circle_added') {
			return;
		}

		$this->parseMemberCircleEvent(
			$event, $params,
			$this->l10n->t('You added team {member} as member to {circle}'),
			$this->l10n->t('{author} added team {member} as member to {circle}')
		);

		throw new FakeException();
	}

	/**
	 * @param IEvent $event
	 * @param array $params
	 *
	 * @throws FakeException
	 */
	public function parseSubjectCircleMemberLeft(
		IEvent $event,
		array $params,
	): void {
		if ($event->getSubject() !== 'member_circle_left') {
			return;
		}

		$this->parseCircleMemberEvent(
			$event, $params,
			$this->l10n->t('You made {member} leave {circle}'),
			$this->l10n->t('{author} made {member} leave {circle}')
		);

		throw new FakeException();
	}

	/**
	 * @param IEvent $event
	 * @param array $params
	 *
	 * @throws FakeException
	 */
	public function parseSubjectCircleMemberRemove(
		IEvent $event,
		array $params,
	): void {
		if ($event->getSubject() !== 'member_circle_removed') {
			return;
		}

		$this->parseCircleMemberEvent(
			$event, $params,
			$this->l10n->t('You removed {member} from {circle}'),
			$this->l10n->t('{author} removed {member} from {circle}')
		);

		throw new FakeException();
	}
}
