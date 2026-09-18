<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Support\Sections;

use OCA\Support\IDetail;
use OCA\Support\Section;
use OCA\User_LDAP\Configuration;
use OCA\User_LDAP\Helper;
use OCA\User_LDAP\User_Proxy;
use OCP\IUserManager;
use OCP\Server;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\BufferedOutput;

class LdapSection extends Section {
	public function __construct(
		protected readonly IUserManager $userManager,
	) {
		parent::__construct('ldap', 'LDAP');
	}

	#[\Override]
	public function getDetails(): array {
		$this->createDetail('LDAP configuration', $this->getLDAPInfo(), IDetail::TYPE_COLLAPSIBLE_PREFORMAT);

		return parent::getDetails();
	}

	public function isLDAPEnabled(): bool {
		$backends = $this->userManager->getBackends();

		foreach ($backends as $backend) {
			if ($backend instanceof User_Proxy) {
				return true;
			}
		}

		return false;
	}

	private function getLDAPInfo(): string {
		$helper = Server::get(Helper::class);

		$output = new BufferedOutput();

		// copy of OCA\User_LDAP\Command\ShowConfig::renderConfigs
		$configIDs = $helper->getServerConfigurationPrefixes();
		foreach ($configIDs as $id) {
			$configHolder = new Configuration($id);
			$configuration = $configHolder->getConfiguration();
			ksort($configuration);

			$table = new Table($output);
			$table->setHeaders(['Configuration', $id]);
			$rows = [];
			foreach ($configuration as $key => $value) {
				if ($key === 'ldapAgentPassword') {
					$value = '***';
				}
				if (is_array($value)) {
					$value = implode(';', $value);
				}
				$rows[] = [$key, $value];
			}
			$table->setRows($rows);
			$table->render();
		}

		return $output->fetch();
	}
}
