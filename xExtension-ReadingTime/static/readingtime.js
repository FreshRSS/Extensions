(function reading_time() {
	'use strict';

	const reading_time = {
		flux_list: null,
		flux: null,
		innerText: null,
		count: null,
		read_time: null,
		reading_time: null,

		init: function () {
			const flux_list = document.querySelectorAll('[id^="flux_"]');
			const speed = window.context.extensions.reading_time_speed;
			const metrics = window.context.extensions.reading_time_metrics;

			for (let i = 0; i < flux_list.length; i++) {
				if ('readingTime' in flux_list[i].dataset) {
					continue;
				}

				reading_time.flux = flux_list[i];

				if (metrics == 'letters') {
					reading_time.count = reading_time.flux_letters_count(flux_list[i]);
				} else {  // words
					reading_time.count = reading_time.flux_words_count(flux_list[i]);
				}
				reading_time.reading_time = reading_time.calc_read_time(reading_time.count, speed);

				flux_list[i].dataset.readingTime = reading_time.reading_time;

				const li = document.createElement('li');
				li.setAttribute('class', 'item date');
				li.style.width = '40px';
				li.style.overflow = 'hidden';
				li.style.textAlign = 'right';
				li.style.display = 'table-cell';
				li.textContent = reading_time.reading_time + '\u2009m';

				const ul = document.querySelector('#' + reading_time.flux.id + ' ul.horizontal-list');
				ul.insertBefore(li, ul.children[ul.children.length - 1]);
			}
		},

		flux_words_count: function flux_words_count(flux) {
			// get innerText, from the article itself (not the header, not the bottom line):
			reading_time.innerText = flux.querySelector('.flux_content .content').innerText;

			// split the text to count the words correctly (source: http://www.mediacollege.com/internet/javascript/text/count-words.html)
			reading_time.innerText = reading_time.innerText.replace(/(^\s*)|(\s*$)/gi, ''); // exclude  start and end white-space
			reading_time.innerText = reading_time.innerText.replace(/[ ]{2,}/gi, ' '); // 2 or more space to 1
			reading_time.innerText = reading_time.innerText.replace(/\n /, '\n'); // exclude newline with a start spacing

			return reading_time.innerText.split(' ').length;
		},

		flux_letters_count: function flux_letters_count(flux) {
			// get innerText, from the article itself (not the header, not the bottom line):
			reading_time.innerText = flux.querySelector('.flux_content .content').innerText;

			// clean the text by removing excessive whitespace
			reading_time.innerText = reading_time.innerText.replace(/\s/gi, ''); // exclude white-space

			return reading_time.innerText.length;
		},

		calc_read_time: function calc_read_time(count, speed) {
			reading_time.read_time = Math.round(count / speed);

			if (reading_time.read_time === 0) {
				reading_time.read_time = '<1';
			}

			return reading_time.read_time;
		},
	};

	function add_load_more_listener() {
		reading_time.init();
		document.body.addEventListener('freshrss:load-more', function (e) {
			reading_time.init();
		});

		if (window.console) {
			console.log('ReadingTime init done.');
		}
	}

	if (document.readyState && document.readyState !== 'loading' && typeof window.context !== 'undefined' && typeof window.context.extensions !== 'undefined') {
		add_load_more_listener();
	} else {
		document.addEventListener('freshrss:globalContextLoaded', add_load_more_listener, false);
	}

	const event = new Event('freshrss:globalContextLoaded');

	function startDetectGlobalContextLoaded() {
		const globalContextElement = document.getElementById('jsonVars');

		if (globalContextElement !== null) {
			// Wait until load global context
			const observer = new MutationObserver((e) => {
				if (e[0].removedNodes[0].id != 'jsonVars') {
					return;
				}
				observer.disconnect();
				document.dispatchEvent(event);
			});

			observer.observe(globalContextElement.parentElement, {
				attributes: true,
				childList: true,
				subtree: true,
			});
		} else {
			// Already loaded global context
			document.dispatchEvent(event);
		}
	}

	if (document.readyState && document.readyState !== 'loading') {
		startDetectGlobalContextLoaded();
	} else {
		document.addEventListener('DOMContentLoaded', startDetectGlobalContextLoaded, false);
	}
}());
