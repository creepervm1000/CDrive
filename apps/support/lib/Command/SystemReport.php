<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Support\Command;

use OCA\Support\DetailManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SystemReport extends Command {
	public function __construct(
		protected readonly DetailManager $detailManager,
	) {
		parent::__construct();
	}

	#[\Override]
	protected function configure(): void {
		$this
			->setName('support:report')
			->setDescription('Generate a system report')
		;
	}

	#[\Override]
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$output->writeln($this->detailManager->getRenderedDetails());
		return 0;
	}
}
