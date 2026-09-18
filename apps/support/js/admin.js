/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2026 CDrive contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * CDrive fork: subscription key toggle removed with the subscription UI.
 */

document.addEventListener('DOMContentLoaded', function() {
	const generateReportButton = document.getElementById('generate-report-button');

	generateReportButton?.addEventListener('click', async function(e) {
		const reportStatus = document.getElementById('report-status');

		e.target.disabled = true;
		try {
			reportStatus.innerText = '';
			reportStatus.classList.add('icon-loading');

			const response = await window.fetch(
				window.OC.generateUrl('apps/support/generateSystemReport?forceLanguage=en'),
				{
					method: 'POST',
					headers: {
						requestToken: OC.requestToken,
					},
				},
			)
			const { link, password, message } = await response.json();
			if (!response.ok) {
				throw new Error(message);
			}

			const downloadLinkElement = document.createElement('a');
			downloadLinkElement.href = link;
			downloadLinkElement.target = '_blank';
			downloadLinkElement.textContent = link;

			const passwordCodeElement = document.createElement('code');
			passwordCodeElement.textContent = password;

			reportStatus.append(
				document.createTextNode(t('support', 'Link:') + ' '),
				downloadLinkElement,
				document.createElement('br'),
				document.createTextNode(t('support', 'Password:') + ' '),
				passwordCodeElement,
			);
		} catch (error) {
			reportStatus.innerHTML = t('support', 'Generating system report failed.') + ' ' + (error?.message || '');
		} finally {
			e.target.disabled = false;
			reportStatus.classList.remove('icon-loading');
		}
	})
})
