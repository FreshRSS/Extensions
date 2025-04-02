(function reading_time() {
	'use strict';

	const reading_time = {
		flux_list: null,
		flux: null,
		textContent: null,
		count: null,
		read_time: null,
		reading_time: null,

		init: function () {
			const flux_list = document.querySelectorAll('[id^="flux_"]');
			const speed = window.context.extensions.reading_time_speed;
			const metrics = window.context.extensions.reading_time_metrics;
			const language = window.context.i18n.language;

			for (let i = 0; i < flux_list.length; i++) {
				if ('readingTime' in flux_list[i].dataset) {
					continue;
				}

				reading_time.flux = flux_list[i];

				if (metrics == 'letters') {
					reading_time.count = reading_time.flux_letters_count(flux_list[i], language);
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
			// get textContent, from the article itself (not the header, not the bottom line):
			reading_time.textContent = flux.querySelector('.flux_content .content').textContent;

			// split the text to count the words correctly (source: http://www.mediacollege.com/internet/javascript/text/count-words.html)
			reading_time.textContent = reading_time.textContent.replace(/(^\s*)|(\s*$)/gi, ''); // exclude  start and end white-space
			reading_time.textContent = reading_time.textContent.replace(/[ ]{2,}/gi, ' '); // 2 or more space to 1
			reading_time.textContent = reading_time.textContent.replace(/\n /, '\n'); // exclude newline with a start spacing

			return reading_time.textContent.split(' ').length;
		},

		flux_letters_count: function flux_letters_count(flux, language) {
			const segmenter = new Intl.Segmenter(language, { granularity: 'grapheme' });

			// get textContent, from the article itself (not the header, not the bottom line):
			reading_time.textContent = flux.querySelector('.flux_content .content').textContent;

			// clean the text by removing excessive whitespace
			reading_time.textContent = reading_time.textContent.replace(/\s/gi, ''); // exclude white-space

			return [...segmenter.segment(reading_time.textContent)].length;
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

	if (typeof window.context !== 'undefined' && typeof window.context.extensions !== 'undefined') {
		add_load_more_listener();
	} else {
		document.addEventListener('freshrss:globalContextLoaded', add_load_more_listener, false);
	}
}());
