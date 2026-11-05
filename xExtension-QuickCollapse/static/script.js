/* globals context, loadLazyImages */

(function () {
	function loadLazyImagesIfExpanded() {
		const streamElem = document.getElementById('stream');
		// Articles collapsed by default are rendered with lazy-loaded media, which must be loaded once expanded
		if (context.does_lazyload && !streamElem.classList.contains('hide_posts')) {
			loadLazyImages(streamElem);
		}
	}

	function toggleCollapse() {
		const streamElem = document.getElementById('stream');
		const toggleElem = document.getElementById('toggle-collapse');
		const wasCollapsed = streamElem.classList.contains('hide_posts');

		if (wasCollapsed) {
			streamElem.classList.remove('hide_posts');
			toggleElem.classList.remove('collapsed');
		} else {
			streamElem.classList.add('hide_posts');
			toggleElem.classList.add('collapsed');
		}

		loadLazyImagesIfExpanded();
	}

	function syncWithContext() {
		if (!window.context) {
			// The variables might not be available yet, so we need to wait for them.
			return setTimeout(syncWithContext, 10);
		}

		const toggleElem = document.getElementById('toggle-collapse');
		toggleElem.title = context.extensions.quick_collapse.i18n.toggle_collapse;
		toggleElem.innerHTML = `<img class="icon uncollapse" src="${context.extensions.quick_collapse.icon_url_out}" alt="↕" />`;
		toggleElem.innerHTML += `<img class="icon collapse" src="${context.extensions.quick_collapse.icon_url_in}" alt="✖" />`;

		if (context.hide_posts) {
			toggleElem.classList.add('collapsed');
		}
	}

	const streamElem = document.getElementById('stream');
	if (!streamElem || !streamElem.classList.contains('normal')) {
		// The button should be enabled only on "normal" view
		return;
	}

	// create the new button
	const toggleElem = document.createElement('button');
	toggleElem.id = 'toggle-collapse';
	toggleElem.classList.add('btn');
	toggleElem.addEventListener('click', toggleCollapse);

	// articles loaded afterwards (e.g. when scrolling down) are rendered with lazy-loaded media as well
	document.body.addEventListener('freshrss:load-more', loadLazyImagesIfExpanded);

	// replace the "order" button by a stick containing the order and the
	// collapse buttons
	const orderElem = document.getElementById('toggle-order');

	const stickElem = document.createElement('div');
	stickElem.classList.add('stick');

	orderElem.parentNode.insertBefore(stickElem, orderElem);
	stickElem.appendChild(orderElem);
	stickElem.appendChild(toggleElem);

	// synchronizes the collapse button with dynamic vars passed via the
	// backend (async mode).
	syncWithContext();
}());
