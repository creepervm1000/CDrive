<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Support;

class Detail implements IDetail {
	public function __construct(
		private readonly string $section,
		private readonly string $title,
		private readonly string $information,
		private readonly int $type,
	) {
	}

	#[\Override]
	public function getTitle(): string {
		return $this->title;
	}

	#[\Override]
	public function getSection(): string {
		return $this->section;
	}

	#[\Override]
	public function getInformation(): string {
		return $this->information;
	}

	#[\Override]
	public function getType(): int {
		return $this->type;
	}
}
