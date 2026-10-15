<?php

return array(
	'gridview' => array(
		'view_mode_name' => 'Grid View',
		'config' => array(
			'columns' => 'Number of columns',
			'columns_label' => 'columns',
			'columns_help' => 'Choose how many columns to display in the grid view (2-4). On smaller screens, this will automatically adjust.',
			'fetch_og_image' => 'Thumbnail fetching',
			'fetch_og_image_label' => 'Fetch thumbnails from article pages',
			'fetch_og_image_help' => 'When enabled, the extension will fetch Open Graph images from article pages that have no thumbnail in the RSS feed. This provides better card images but may slow down page loading.',
			'sort_by_date' => 'Default sorting',
			'sort_by_date_label' => 'Sort by publication date (newest first)',
			'sort_by_date_help' => 'When enabled, the default sort order is set to publication date, newest first. This is applied once; you can still change the sort order manually in FreshRSS.',
			'mobile_menu_button' => 'Sidebar toggle button',
			'mobile_menu_button_label' => 'Show a floating sidebar toggle button',
			'mobile_menu_button_help' => 'When enabled, a floating hamburger button appears at the bottom-left of the screen to open the FreshRSS sidebar.',
			'sticky_nav' => 'Sticky navigation bar',
			'sticky_nav_label' => 'Keep the top navigation bar visible while scrolling',
			'sticky_nav_help' => 'When enabled, the navigation bar (with read/unread, favourites, etc.) stays fixed at the top while scrolling through articles in grid mode.',
			'usage_title' => 'How to use',
			'usage_info' => 'After saving your settings, click the grid icon (▦) in the header or press "G" on your keyboard to toggle grid view. Click on any card to open the article in a new tab.',
		),
	),
);
