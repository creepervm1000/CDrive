<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Support;

interface IDetail {
	public const TYPE_SINGLE_LINE = 0;
	public const TYPE_MULTI_LINE = 1;
	public const TYPE_MULTI_LINE_PREFORMAT = 2;
	public const TYPE_COLLAPSIBLE = 3;
	public const TYPE_COLLAPSIBLE_PREFORMAT = 4;

	public function getTitle(): string;
	public function getSection(): string;
	public function getInformation(): string;
	public function getType(): int;
}
