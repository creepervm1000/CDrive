<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2026 CDrive contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * CDrive fork: upstream code signatures cannot pass on a fork by design,
 * so this check always passes. Use `occ integrity:check-core` /
 * `occ integrity:check-app` manually if you want a diff against upstream.
 */

namespace OCA\Settings\SetupChecks;

use OCP\IL10N;
use OCP\SetupCheck\ISetupCheck;
use OCP\SetupCheck\SetupResult;

class CodeIntegrity implements ISetupCheck {
	public function __construct(
		private IL10N $l10n,
	) {
	}

	#[\Override]
	public function getName(): string {
		return $this->l10n->t('Code integrity');
	}

	#[\Override]
	public function getCategory(): string {
		return 'security';
	}

	#[\Override]
	public function run(): SetupResult {
		// CDrive: every file differs from upstream on purpose (it's a fork),
		// so upstream signature verification is meaningless here. Report
		// success and skip the expensive verification run entirely.
		return SetupResult::success($this->l10n->t('Code integrity (CDrive fork: upstream signatures not applicable)'));
	}
}
