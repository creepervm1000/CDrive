<?php

/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2026 CDrive contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * CDrive fork: subscription sections removed (subscription details,
 * enterprise upsell, subscription key form). Only the free system report
 * and community support sections remain.
 */
script('support', 'admin');
style('support', 'support');

/** @var array $_ */
?>

<div class="section system-information">
	<div>
		<img src="<?php p(\OCP\Template::image_path('support', 'system-info.svg')); ?>">
		<h3><?php p($l->t('System information')); ?></h3>
		<p>
			<?php p(
				$l->t('The button below generates a text file in the folder "System information" and shares it as password protected public link. The link is valid for 2 weeks.')
			); ?>
		</p>
		<button id="generate-report-button" class="button generate-report-button"><?php p($l->t('Generate system report')); ?></button>
		<div id="report-status"></div>
	</div>
</div>

<div class="section community-support">
	<h2><?php p($l->t('Community support')); ?></h2>

	<div class="columns">
		<div>
			<img src="<?php p(\OCP\Template::image_path('support', 'discourse.svg')) ?>">
			<h3><?php p($l->t('Forum')); ?></h3>
			<p>
				<?php p(
					$l->t('Nextcloud is free software which is supported by a very active community. Please register at the forum to ask questions and discuss with others.')
				); ?>
			</p>
			<a href="https://help.nextcloud.com"
				target="blank" rel="no" class="button link-button"><?php p($l->t('Nextcloud forum')); ?></a>
		</div>
		<div>
			<img src="<?php p(\OCP\Template::image_path('support', 'github.svg')) ?>">
			<h3>
				<?php p($l->t('GitHub')); ?>
			</h3>
			<p>
				<?php p(
					$l->t('Nextcloud uses GitHub as platform to collaboratively work. You can file bug reports directly there.')
				); ?>
			</p>
			<a href="https://github.com/nextcloud/"
				target="blank" rel="no" class="button link-button"><?php p($l->t('Nextcloud at GitHub')); ?></a>
		</div>
	</div>
</div>
