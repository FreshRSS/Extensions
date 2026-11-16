'use strict';

// Replace the preview of a video by its player once clicked, so that the player is only loaded when the user wants to watch the video
document.addEventListener('click', function (ev) {
	if (ev.button !== 0 || ev.ctrlKey || ev.metaKey || ev.shiftKey || ev.altKey) {
		return;	// Keep the default behaviour of the link, e.g. to open the video in a new tab
	}
	const preview = ev.target.closest('a.yt-player-preview[data-yt-player-src]');
	if (!preview) {
		return;
	}
	ev.preventDefault();

	const src = new URL(preview.dataset.ytPlayerSrc, document.baseURI);
	src.searchParams.set('autoplay', '1');

	const iframe = document.createElement('iframe');
	iframe.className = 'youtube-plugin-video';
	if (preview.classList.contains('yt-player-autosize')) {
		iframe.classList.add('yt-player-autosize');
	} else {
		iframe.width = preview.dataset.ytPlayerWidth;
		iframe.height = preview.dataset.ytPlayerHeight;
	}
	iframe.src = src.href;
	iframe.setAttribute('frameborder', '0');
	iframe.referrerPolicy = 'strict-origin-when-cross-origin';
	iframe.allow = 'autoplay';	// Start the video, as it has just been clicked
	iframe.allowFullscreen = true;
	preview.replaceWith(iframe);
});
