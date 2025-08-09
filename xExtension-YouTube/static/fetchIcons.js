'use strict';

/* globals context, slider */

function initFetchBtn() {
	const i18n = context.extensions.yt_i18n;

	const fetchIcons = document.querySelector('button[value="iconFetchFinish"]');
	if (!fetchIcons) {
		return;
	}

	document.querySelectorAll('#yt_action_btn').forEach(el => { el.style.marginBottom = '1rem'; });

	fetchIcons.form.querySelectorAll('button').forEach(btn => btn.removeAttribute('disabled'));
	fetchIcons.removeAttribute('title');

	fetchIcons.onclick = function (e) {
		e.preventDefault();

		const closeSlider = document.querySelector('#close-slider');
		if (closeSlider) {
			closeSlider.onclick = (e) => e.preventDefault();
			closeSlider.style.cursor = 'not-allowed';
			closeSlider.querySelector('img.icon').remove();
		}

		fetchIcons.form.onsubmit = window.onbeforeunload = (e) => e.preventDefault();
		fetchIcons.onclick = null;
		fetchIcons.disabled = true;
		fetchIcons.parentElement.insertAdjacentHTML('afterend', `
    <hr /><br />
    <center>
        ${i18n.fetching_icons}: <b id="iconFetchCount">…</b> • <b id="iconFetchChannel">…</b>
    </center><br /><br />
    `);

		const iconFetchCount = document.querySelector('b#iconFetchCount');
		const iconFetchChannel = document.querySelector('b#iconFetchChannel');

		const configureUrl = fetchIcons.form.action;

		function ajaxBody(action, args) {
			return JSON.stringify({
				'_csrf': context.csrf,
				'yt_action_btn': 'ajax' + action,
				...args
			});
		}

		fetch(configureUrl, {
			method: 'POST',
			body: ajaxBody('GetYtFeeds'),
			headers: {
				'Content-Type': 'application/json; charset=UTF-8'
			}
		}).then(resp => {
			if (!resp.ok) {
				return;
			}
			return resp.json();
		}).then(json => {
			let completed = 0;
			json.forEach(async (feed) => {
				await fetch(configureUrl, {
					method: 'POST',
					body: ajaxBody('FetchIcon', { 'id': feed.id }),
					headers: {
						'Content-Type': 'application/json; charset=UTF-8'
					}
				}).then(async () => {
					iconFetchChannel.innerText = feed.title;
					iconFetchCount.innerText = `${++completed}/${json.length}`;
					if (completed === json.length) {
						fetchIcons.disabled = false;
						fetchIcons.form.onsubmit = window.onbeforeunload = null;
						fetchIcons.click();
					}
				});
			});
		});
	};
}

window.addEventListener('load', function () {
	if (typeof slider !== 'undefined') {
		slider.addEventListener('freshrss:slider-load', initFetchBtn);
	}
	initFetchBtn();
});
