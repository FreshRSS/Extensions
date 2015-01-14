"use strict";

var sticky_feeds = {
	tree: null,
	initial_pos_top: 0,
	width: 0,
	window: null,

	init: function() {
		if (!window.$) {
			window.setTimeout(sticky_feeds.init, 50);
			return;
		}

		sticky_feeds.tree = $('#aside_feed .tree');
		if (sticky_feeds.tree.length > 0) {
			sticky_feeds.window = $(window);
			sticky_feeds.initial_pos_top = sticky_feeds.tree.position().top;
			sticky_feeds.tree.css('min-width', $('#aside_feed').width());
			sticky_feeds.tree.addClass('sticky');

			sticky_feeds.window.on('scroll', sticky_feeds.scroller);
			sticky_feeds.scroller();
		}
	},

	scroller: function() {
		var pos_top_window = sticky_feeds.window.scrollTop();

		if (pos_top_window < sticky_feeds.initial_pos_top) {
			sticky_feeds.tree.css('top', sticky_feeds.initial_pos_top - pos_top_window + 10);
		} else {
			sticky_feeds.tree.css('top', 0);
		}
	},
};


window.onload = sticky_feeds.init;
