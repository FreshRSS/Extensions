"use strict";

var sticky_feeds_aside_tree = null,
    sticky_feeds_initial_pos_top = 0,
    sticky_feeds_window = null;


function sticky_feeds_scroller() {
	var pos_top_window = sticky_feeds_window.scrollTop();

	if (pos_top_window >= sticky_feeds_initial_pos_top &&
			!sticky_feeds_aside_tree.hasClass('sticky')) {
		sticky_feeds_aside_tree.addClass('sticky');
		sticky_feeds_aside_tree.css('width', $('#aside_feed').width());
	} else if (pos_top_window < sticky_feeds_initial_pos_top) {
		sticky_feeds_aside_tree.removeClass('sticky');
	}
}


function sticky_feeds_init() {
	if (!window.$) {
		window.setTimeout(init_sticky_feeds, 50);
		return;
	}

	sticky_feeds_aside_tree = $('#aside_feed .tree');
	if (sticky_feeds_aside_tree.length > 0) {
		sticky_feeds_initial_pos_top = sticky_feeds_aside_tree.position().top;
		sticky_feeds_window = $(window);
		sticky_feeds_window.on('scroll', sticky_feeds_scroller);
		sticky_feeds_scroller();
	}
}


window.onload = sticky_feeds_init;
