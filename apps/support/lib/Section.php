<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Support;

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\BufferedOutput;

class Section implements ISection {
	/** @var IDetail[] */
	private array $details = [];

	public function __construct(
		private readonly string $identifier,
		private readonly string $title,
		int $order = 0,
	) {
	}

	#[\Override]
	public function getIdentifier(): string {
		return $this->identifier;
	}

	#[\Override]
	public function getTitle(): string {
		return $this->title;
	}

	#[\Override]
	public function addDetail(IDetail $details): void {
		$this->details[] = $details;
	}

	#[\Override]
	public function getDetails(): array {
		return $this->details;
	}

	public function createDetail(string $title, string $information, int $type = IDetail::TYPE_SINGLE_LINE): IDetail {
		$detail = new Detail($this->getIdentifier(), $title, $information, $type);
		$this->addDetail($detail);
		return $detail;
	}

	protected function renderTable(array $headers, array $rows): string {
		$output = new BufferedOutput();

		$table = new Table($output);
		$table->setHeaders($headers);
		$table->setRows($rows);
		$table->render();

		return $output->fetch();
	}
}
