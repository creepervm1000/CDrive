<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Support;

/**
 * Interface ISection
 *
 * @package OCA\IssueTemplate
 */
interface ISection {
	public function getIdentifier(): string;
	public function getTitle(): string;
	public function addDetail(IDetail $details): void;

	/**
	 * @return IDetail[]
	 */
	public function getDetails(): array;
}
