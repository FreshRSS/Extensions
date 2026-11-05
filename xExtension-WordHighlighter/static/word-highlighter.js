'use strict';
/* globals context */

/* WordHighlighter v0.0.2 (FreshRSS Extension) */

function wordHighlighter(c /* console */, Mark, context, OPTIONS) {
	const markConf = (done, counter) => ({
		caseSensitive: OPTIONS.case_sensitive || false,
		separateWordSearch: OPTIONS.separate_word_search || false,
		ignoreJoiners: OPTIONS.ignore_joiners || false,
		exclude: [
			'mark',
			...(OPTIONS.enable_in_article ? [] : ['article *']),
		],
		done: (n) => (counter.value += n) && done(),
		noMatch: done,
	});

	const m = new Mark(context);
	const changePageListener = debounce(200, (x) => {
		OPTIONS.enable_logs && c.group('WordHighlighter: page change');
		stopObserving();
		highlightWords(m, startObserving);
	});

	const mo = new MutationObserver(changePageListener);
	mo.observe(context, { subtree: true, childList: true });

	function startObserving() {
		mo.observe(context, { subtree: true, childList: true });
	}

	function stopObserving() {
		mo.disconnect();
	}

	function highlightWords(m, done) {
		const start = performance.now();
		const hCounter = { value: 0 };

		new Promise((resolve) =>
			m.mark(OPTIONS.words || [], { ...markConf(resolve, hCounter) })
		)
			.finally(() => {
				if (OPTIONS.enable_logs) {
					c.log(`WordHighlighter: ${hCounter.value} new highlights added in ${performance.now() - start}ms.`);
					c.groupEnd();
				}
				typeof done === 'function' && done();
			});
	}

	highlightWords(m);
}

// MAIN:

(function main() {
	let started = false;
	let interval = null;

	function hasGlobalContext() {
		return typeof context !== 'undefined' && context && context.extensions;
	}

	function start() {
		if (started) {
			return;
		}
		started = true;
		clearInterval(interval);
		document.removeEventListener('freshrss:globalContextLoaded', start);
		try {
			const configuration = (hasGlobalContext() && context.extensions['Word highlighter'] &&
				context.extensions['Word highlighter'].configuration) || window.WordHighlighterConf || { };
			const OPTIONS = {
				...configuration,
				words: Array.isArray(configuration.words)
					? configuration.words.filter((word) => typeof word === 'string').map((word) => word.trim()).filter((word) => word !== '')
					: [],
			};
			const onMainPage = !(new URL(window.location)).searchParams.get('c');
			if (onMainPage) {
				// This script is loaded async and may run before the parser has reached #stream:
				// wait for the DOM, otherwise Mark/MutationObserver get a null context and nothing is highlighted.
				const run = () => {
					const stream = document.querySelector('#stream');
					if (!stream) {
						OPTIONS.enable_logs && console.log('WordHighlighter: ❗️ no #stream on this page');
						return;
					}
					console.log('WordHighlighter: script load...');
					wordHighlighter(console, window.Mark || (Error('mark.js library is not loaded ❗️')), stream, OPTIONS);
					console.log('WordHighlighter: script loaded.✅');
				};
				if (document.readyState === 'loading') {
					document.addEventListener('DOMContentLoaded', run, { once: true });
				} else {
					run();
				}
			} else {
				OPTIONS.enable_logs && console.log('WordHighlighter: ❗️ paused outside of feed page');
			}
			return Promise.resolve();
		} catch (error) {
			console.error('WordHighlighter: ❌', error);
			return Promise.reject(error);
		}
	}

	if (hasGlobalContext()) {
		start();
	} else {
		document.addEventListener('freshrss:globalContextLoaded', start);
		let attempts = 0;
		interval = setInterval(() => {
			if (hasGlobalContext() || ++attempts >= 100) {
				start();
			}
		}, 50);
	}
})();

// Util functions:

function debounce(duration, func) {
	let timeout;
	return function (...args) {
		const effect = () => {
			timeout = null;
			return func.apply(this, args);
		};
		clearTimeout(timeout);
		timeout = setTimeout(effect, duration);
	};
}
