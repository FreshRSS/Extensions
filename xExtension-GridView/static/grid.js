/**
 * Grid View Extension - JavaScript
 * Transforms the default FreshRSS view into a card-based grid layout
 */

(function () {
	'use strict';

	const STORAGE_KEY = 'freshrss-gridview-enabled';
	let gridEnabled = false;
	let columns = 3;
	let placeholderUrl = '';
	let streamObserver = null;
	let parentObserver = null;
	let classObserver = null;
	let initialized = false;
	let ogFetchUrl = '';
	let showMobileMenuButton = false;
	let shareMenuHtml = '';
	let stickyNavEnabled = false;
	/** @type {function|null} Bound scroll handler for auto-load-more cleanup */
	let autoLoadScrollHandler = null;

	/**
	 * Create a loading overlay with skeleton cards to show while
	 * grid items are being transformed.
	 * @param {HTMLElement} stream
	 */
	function showLoadingSkeleton(stream) {
		// Don't add duplicates
		if (stream.querySelector('.gridview-loading-overlay')) return;

		const overlay = document.createElement('div');
		overlay.className = 'gridview-loading-overlay';

		const count = (columns || 3) * 2; // two rows of skeleton cards
		for (let i = 0; i < count; i++) {
			const card = document.createElement('div');
			card.className = 'gridview-skeleton-card';
			card.innerHTML =
				'<div class="skeleton-thumbnail"></div>' +
				'<div class="skeleton-content">' +
					'<div class="skeleton-line short"></div>' +
					'<div class="skeleton-line long"></div>' +
					'<div class="skeleton-line medium"></div>' +
				'</div>';
			overlay.appendChild(card);
		}

		// Insert at top so it appears immediately
		stream.prepend(overlay);
	}

	/**
	 * Remove the loading overlay from the stream.
	 * @param {HTMLElement} stream
	 */
	function removeLoadingSkeleton(stream) {
		const overlay = stream.querySelector('.gridview-loading-overlay');
		if (overlay) overlay.remove();
	}

	/**
	 * Initialize the grid view extension
	 */
	function initGridView() {
		if (initialized) return;
		initialized = true;

		// Get configuration from context.extensions (where js_vars hook puts data)
		if (typeof window.context !== 'undefined' && window.context.extensions) {
			if (window.context.extensions.gridview) {
				columns = window.context.extensions.gridview.columns || 3;
				gridEnabled = window.context.extensions.gridview.enabled || false;
				placeholderUrl = window.context.extensions.gridview.placeholderUrl || '';
				ogFetchUrl = window.context.extensions.gridview.ogFetchUrl || '';
				showMobileMenuButton = window.context.extensions.gridview.showMobileMenuButton || false;
				stickyNavEnabled = window.context.extensions.gridview.stickyNavEnabled || false;
				shareMenuHtml = window.context.extensions.gridview.shareMenuHtml || '';
			}
		}

		// Check localStorage for user preference (overrides server setting)
		const storedPref = localStorage.getItem(STORAGE_KEY);
		if (storedPref !== null) {
			gridEnabled = storedPref === 'true';
		}

		// Set up toggle button
		setupToggleButton();

		// Apply grid view if enabled
		if (gridEnabled) {
			enableGridView();
		}

		// Deselect card when clicking/tapping outside
		function deselectCard(e) {
			if (!e.target.closest('.flux.card-selected')) {
				const selected = document.querySelector('.flux.card-selected');
				if (selected) {
					selected.classList.remove('card-selected');
				}
			}
		}
		document.addEventListener('touchend', deselectCard);
		document.addEventListener('click', deselectCard);

		// Watch for FreshRSS replacing the #stream element entirely
		// (e.g. on feed navigation) so we can re-apply grid mode immediately.
		observeStreamReplacement();
	}

	/**
	 * Set up the grid toggle button in the navigation
	 */
	function setupToggleButton() {
		// Try to find or create the toggle button
		let toggleBtn = document.querySelector('.read_grid, [data-grid-toggle]');

		if (!toggleBtn) {
			// Create toggle button and add to reading modes
			const readingModes = document.querySelector('.reading_modes, .nav_menu .group-controls, #nav_menu_read_all');
			if (readingModes) {
				toggleBtn = document.createElement('a');
				toggleBtn.href = '#';
				toggleBtn.className = 'read_grid btn';
				toggleBtn.setAttribute('data-grid-toggle', '');
				toggleBtn.title = 'Grid View';
				toggleBtn.innerHTML = '<span class="icon">▦</span>';
				readingModes.appendChild(toggleBtn);
			}
		}

		// Also try adding to the header actions area
		if (!toggleBtn) {
			const headerActions = document.querySelector('.header .group-controls, .nav_menu, #stream .flux_header');
			if (headerActions) {
				toggleBtn = document.createElement('a');
				toggleBtn.href = '#';
				toggleBtn.className = 'read_grid btn';
				toggleBtn.setAttribute('data-grid-toggle', '');
				toggleBtn.title = 'Grid View';
				toggleBtn.innerHTML = '▦';

				// Insert at beginning
				headerActions.insertBefore(toggleBtn, headerActions.firstChild);
			}
		}

		if (toggleBtn) {
			toggleBtn.addEventListener('click', function (e) {
				e.preventDefault();
				e.stopPropagation();
				toggleGridView();
			});

			// Update button state
			updateToggleButtonState(toggleBtn);
		}

		// Add keyboard shortcut (g key)
		document.addEventListener('keydown', function (e) {
			// Don't trigger if typing in an input or editable element
			if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.isContentEditable) return;

			if (e.key === 'g' && !e.ctrlKey && !e.metaKey && !e.altKey) {
				toggleGridView();
			}
		});

		// Set up mobile menu button (if enabled in extension settings)
		if (showMobileMenuButton) {
			setupMobileMenuButton();
		}
	}

	/**
	 * Set up a fixed mobile bottom bar with a menu button that
	 * toggles the FreshRSS sidebar (#aside_feed).
	 */
	function setupMobileMenuButton() {
		if (document.querySelector('.gridview-mobile-bar')) return;

		const bar = document.createElement('div');
		bar.className = 'gridview-mobile-bar';

		const menuBtn = document.createElement('button');
		menuBtn.className = 'gridview-mobile-menu-btn';
		menuBtn.type = 'button';
		menuBtn.title = 'Open menu';
		menuBtn.setAttribute('aria-label', 'Open menu');
		// Hamburger icon (three horizontal lines)
		menuBtn.innerHTML = '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" ' +
			'stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
			'<line x1="3" y1="6" x2="21" y2="6"></line>' +
			'<line x1="3" y1="12" x2="21" y2="12"></line>' +
			'<line x1="3" y1="18" x2="21" y2="18"></line>' +
			'</svg>';

		menuBtn.addEventListener('click', function (e) {
			e.preventDefault();
			e.stopPropagation();
			toggleAsideFeed();
		});

		bar.appendChild(menuBtn);
		document.body.appendChild(bar);
	}

	/**
	 * Toggle the FreshRSS sidebar.
	 * Delegates to FreshRSS's own toggle_aside_click() when available,
	 * which adds/removes the .visible class on .aside and toggles
	 * display style. Falls back to clicking the native toggle button.
	 */
	function toggleAsideFeed() {
		// Preferred: call FreshRSS's own toggle function
		if (typeof window.toggle_aside_click === 'function') {
			window.toggle_aside_click(true);
			return;
		}

		// Fallback: programmatically click FreshRSS's toggle button
		const toggleBtn = document.querySelector('#nav_menu_toggle_aside button');
		if (toggleBtn) {
			toggleBtn.click();
			return;
		}

		// Last resort: toggle .visible on .aside directly
		const aside = document.querySelector('.aside');
		if (aside) {
			const isVisible = aside.classList.contains('visible');
			if (isVisible) {
				aside.classList.remove('visible');
				aside.style.display = 'none';
			} else {
				aside.classList.add('visible');
				aside.style.display = '';
			}
		}
	}

	/**
	 * Update toggle button visual state
	 * @param {HTMLElement} btn
	 */
	function updateToggleButtonState(btn) {
		if (!btn) return;

		if (gridEnabled) {
			btn.classList.add('active');
			btn.style.backgroundColor = 'var(--frss-accent-color, #4a90d9)';
			btn.style.color = '#fff';
		} else {
			btn.classList.remove('active');
			btn.style.backgroundColor = '';
			btn.style.color = '';
		}
	}

	/**
	 * Toggle grid view on/off
	 */
	function toggleGridView() {
		gridEnabled = !gridEnabled;
		localStorage.setItem(STORAGE_KEY, gridEnabled);

		if (gridEnabled) {
			enableGridView();
		} else {
			disableGridView();
		}

		// Update toggle button state
		const toggleBtn = document.querySelector('.read_grid, [data-grid-toggle]');
		updateToggleButtonState(toggleBtn);
	}

	/**
	 * Get the current view context name for the grid header.
	 * Returns the category/feed name, or 'Main Stream' for all-feeds views.
	 * @returns {string}
	 */
	function getContextName() {
		const params = new URLSearchParams(location.search);
		const get = params.get('get') || '';

		// All-feeds views
		if (get === '' || get === 'a' || get === 'A' || get === 'Z') {
			return 'Main Stream';
		}

		// Starred
		if (get === 's') {
			return 'Starred';
		}

		// Category view — look up name from sidebar
		if (get.indexOf('c_') === 0) {
			const catEl = document.querySelector('#' + get + ' > a.tree-folder-title .title');
			return catEl ? catEl.textContent.trim() : 'Main Stream';
		}

		// Feed view — show the feed name
		if (get.indexOf('f_') === 0) {
			const feedEl = document.querySelector('#' + get + ' > a .title');
			return feedEl ? feedEl.textContent.trim() : 'Main Stream';
		}

		return 'Main Stream';
	}

	/**
	 * Insert or update the grid category header above the tiles.
	 * @param {HTMLElement} stream
	 */
	function updateGridHeader(stream) {
		const existing = stream.querySelector('.gridview-header');
		const name = getContextName();

		if (existing) {
			const nameEl = existing.querySelector('.gridview-header-name');
			if (nameEl) {
				nameEl.textContent = name;
			} else {
				existing.textContent = name;
			}
		} else {
			const header = document.createElement('div');
			header.className = 'gridview-header';

			const nameSpan = document.createElement('span');
			nameSpan.className = 'gridview-header-name';
			nameSpan.textContent = name;
			header.appendChild(nameSpan);

			const newArticlesSpan = document.createElement('span');
			newArticlesSpan.className = 'gridview-header-new-articles';
			header.appendChild(newArticlesSpan);

			stream.insertBefore(header, stream.firstChild);
		}

		// Sync notification state from native #new-article element
		syncNewArticleNotification();
	}

	/**
	 * Remove the grid header when disabling grid view.
	 * @param {HTMLElement} stream
	 */
	function removeGridHeader(stream) {
		const existing = stream.querySelector('.gridview-header');
		if (existing) {
			existing.remove();
		}
	}

	/**
	 * Wrap .nav_menu and #stream (and siblings like #slider, #close-slider)
	 * inside a scrollable container so that .nav_menu can use position:sticky.
	 *
	 * FreshRSS places .nav_menu, #stream, etc. as direct children of #global
	 * (display:table). position:sticky does not work inside table-cell layout,
	 * so we insert a wrapper div that becomes the scroll container.
	 */
	function wrapContentForStickyNav() {
		// Don't wrap twice
		if (document.querySelector('.gridview-scroll-wrapper')) return;

		const navMenu = document.querySelector('.nav_menu');
		if (!navMenu) return;

		const parent = navMenu.parentElement;
		if (!parent) return;

		const wrapper = document.createElement('div');
		wrapper.className = 'gridview-scroll-wrapper';

		// Collect .nav_menu and all following siblings (everything except .aside)
		// These are: .nav_menu, datalist, template, #stream, #slider, #close-slider, #nav_entries
		let sibling = navMenu;
		const toWrap = [];
		while (sibling) {
			toWrap.push(sibling);
			sibling = sibling.nextElementSibling;
		}

		// Insert wrapper where .nav_menu was
		parent.insertBefore(wrapper, toWrap[0]);

		// Move elements into wrapper
		for (let i = 0; i < toWrap.length; i++) {
			wrapper.appendChild(toWrap[i]);
		}
	}

	/**
	 * Unwrap the scroll container, restoring the original DOM structure.
	 */
	function unwrapContentForStickyNav() {
		const wrapper = document.querySelector('.gridview-scroll-wrapper');
		if (!wrapper) return;

		const parent = wrapper.parentElement;
		if (!parent) return;

		// Move all children back to parent, before the wrapper
		while (wrapper.firstChild) {
			parent.insertBefore(wrapper.firstChild, wrapper);
		}

		wrapper.remove();
	}

	/**
	 * Set up auto-load-more for grid mode.
	 *
	 * FreshRSS's built-in onScroll() monitors document.scrollingElement,
	 * but in grid mode the scroll happens inside .gridview-scroll-wrapper.
	 * We attach our own scroll listener that clicks #load_more when the
	 * user scrolls near the bottom of the stream.
	 */
	function setupAutoLoadMore() {
		// Clean up any previous handler first
		teardownAutoLoadMore();

		const wrapper = document.querySelector('.gridview-scroll-wrapper');
		if (!wrapper) return;

		let trailingTimer = null;

		/**
		 * Check scroll position and click load_more if near bottom.
		 */
		function checkAndLoad() {
			const loadMoreBtn = document.getElementById('load_more');
			if (!loadMoreBtn) return;
			if (loadMoreBtn.classList.contains('loading')) return;

			// Fire when within half a viewport height of the bottom of scrollable content
			const distanceFromBottom = wrapper.scrollHeight - wrapper.scrollTop - wrapper.clientHeight;
			if (distanceFromBottom <= wrapper.clientHeight * 0.5) {
				loadMoreBtn.click();
			}
		}

		autoLoadScrollHandler = function () {
			// Always schedule a trailing check so the final scroll position is evaluated
			if (trailingTimer) {
				clearTimeout(trailingTimer);
			}
			trailingTimer = setTimeout(function () {
				trailingTimer = null;
				checkAndLoad();
			}, 200);
		};

		wrapper.addEventListener('scroll', autoLoadScrollHandler, { passive: true });
	}

	/**
	 * Remove the auto-load-more scroll listener.
	 */
	function teardownAutoLoadMore() {
		if (!autoLoadScrollHandler) return;

		const wrapper = document.querySelector('.gridview-scroll-wrapper');
		if (wrapper) {
			wrapper.removeEventListener('scroll', autoLoadScrollHandler);
		}
		autoLoadScrollHandler = null;
	}

	/** @type {MutationObserver|null} */
	let newArticleObserver = null;

	/**
	 * Sync the native #new-article notification into the grid header bar.
	 * If #new-article is visible, show its text in the header; otherwise hide it.
	 */
	function syncNewArticleNotification() {
		const newArticleEl = document.getElementById('new-article');
		const headerBadge = document.querySelector('.gridview-header-new-articles');
		if (!headerBadge) return;

		if (newArticleEl && !newArticleEl.hidden && newArticleEl.style.display !== 'none') {
			const linkEl = newArticleEl.querySelector('a');
			const text = linkEl ? linkEl.textContent.trim() : newArticleEl.textContent.trim();
			headerBadge.textContent = text;
			headerBadge.classList.add('visible');
			headerBadge.onclick = function () {
				if (linkEl) {
					linkEl.click();
				}
			};
		} else {
			headerBadge.classList.remove('visible');
			headerBadge.textContent = '';
			headerBadge.onclick = null;
		}
	}

	/**
	 * Observe the #new-article element for visibility changes so we can
	 * mirror the notification into the grid header bar.
	 */
	function observeNewArticleNotification() {
		if (newArticleObserver) {
			newArticleObserver.disconnect();
			newArticleObserver = null;
		}

		const newArticleEl = document.getElementById('new-article');
		if (!newArticleEl) return;

		newArticleObserver = new MutationObserver(function () {
			if (gridEnabled) {
				syncNewArticleNotification();
			}
		});

		newArticleObserver.observe(newArticleEl, {
			attributes: true,
			attributeFilter: ['hidden', 'style']
		});

		// Initial sync
		syncNewArticleNotification();
	}

	/**
	 * Enable grid view
	 */
	function enableGridView() {
		const stream = document.getElementById('stream');
		if (!stream) return;

		stream.classList.add('grid');
		stream.style.setProperty('--gridview-columns', columns);
		document.body.classList.add('gridview-active');

		// Wrap main content in a scroll container so .nav_menu can be sticky
		if (stickyNavEnabled) {
			wrapContentForStickyNav();
			document.body.classList.add('gridview-sticky-nav');
		}

		// Insert category header
		updateGridHeader(stream);

		// Show loading skeletons while entries are being transformed.
		// The CSS rule	 .flux:not(.gridview-transformed) { visibility:hidden }
		// hides the raw list items so the default list never flashes.
		showLoadingSkeleton(stream);

		// Transform existing entries
		transformFluxToCards();

		// Remove the skeleton once all current entries are transformed
		removeLoadingSkeleton(stream);

		// Asynchronously fetch OG images for entries that need them
		asyncFetchOgImages();

		// Set up observer for new entries
		observeNewEntries();

		// Set up observer for state changes (read/unread/favorite)
		observeFluxStateChanges();

		// Observe #new-article notification
		observeNewArticleNotification();

		// Auto-load more articles when scrolling near the bottom
		if (stickyNavEnabled) {
			setupAutoLoadMore();
		}
	}

	/**
	 * Disable grid view
	 */
	function disableGridView() {
		const stream = document.getElementById('stream');
		if (!stream) return;

		// Disconnect observers to prevent accumulation
		if (streamObserver) {
			streamObserver.disconnect();
			streamObserver = null;
		}
		if (classObserver) {
			classObserver.disconnect();
			classObserver = null;
		}
		teardownAutoLoadMore();
		if (newArticleObserver) {
			newArticleObserver.disconnect();
			newArticleObserver = null;
		}

		stream.classList.remove('grid');
		document.body.classList.remove('gridview-active');
		document.body.classList.remove('gridview-sticky-nav');
		removeGridHeader(stream);

		// Unwrap the scroll container used for sticky nav
		unwrapContentForStickyNav();

		// Restore original content
		const transformedFlux = stream.querySelectorAll('.flux.gridview-transformed');
		transformedFlux.forEach(flux => {
			const originalWrapper = flux.querySelector('.gridview-original-content');
			const cardWrapper = flux.querySelector('.gridview-card-content');

			if (originalWrapper) {
				// Remove card wrapper
				if (cardWrapper) cardWrapper.remove();

				// Move original content back
				originalWrapper.style.display = '';
				while (originalWrapper.firstChild) {
					flux.appendChild(originalWrapper.firstChild);
				}
				originalWrapper.remove();
				flux.classList.remove('gridview-transformed');
				// Clear any inline border style set by syncFluxVisualState
				flux.style.borderLeft = '';
			}
		});
	}

	/**
	 * Transform all .flux elements into card format
	 */
	function transformFluxToCards() {
		const stream = document.getElementById('stream');
		if (!stream || !stream.classList.contains('grid')) return;

		const fluxElements = stream.querySelectorAll('.flux:not(.gridview-transformed)');

		fluxElements.forEach(flux => {
			transformSingleFlux(flux);
		});
	}

	/**
	 * Transform a single flux element into a card
	 * @param {HTMLElement} flux
	 */
	function transformSingleFlux(flux) {
		if (flux.classList.contains('gridview-transformed')) return;

		// Skip date separators / day dividers - don't transform them
		if (flux.classList.contains('day') ||
			flux.classList.contains('date-separator') ||
			flux.classList.contains('flux_date') ||
			flux.tagName === 'H2' ||
			flux.tagName === 'H3') {
			flux.classList.add('gridview-transformed');
			return;
		}

		// Skip if this doesn't look like an actual article entry
		// (no data-id attribute typically means it's not an article)
		if (!flux.dataset.id && !flux.querySelector('.flux_content')) {
			flux.classList.add('gridview-transformed');
			return;
		}

		// Mark as transformed
		flux.classList.add('gridview-transformed');

		// Get the article link from data attribute first (most reliable)
		let link = flux.dataset.link || '';

		// Fallback: try to find link in the header
		if (!link) {
			const linkEl = flux.querySelector('.flux_header a[href^="http"], .item.title a[href^="http"], a.item.title[href^="http"]');
			if (linkEl) {
				link = linkEl.href;
			}
		}

		// Another fallback: look for any external link in content
		if (!link) {
			const contentLink = flux.querySelector('.flux_content a[href^="http"]');
			if (contentLink) {
				link = contentLink.href;
			}
		}

		// Get title - try multiple selectors
		let title = '';
		const titleEl = flux.querySelector('.item.title a, .item.title .title, .title a, a.item.title');
		if (titleEl) {
			title = titleEl.textContent.trim();
		} else {
			// Try getting from the title span directly
			const titleSpan = flux.querySelector('.item.title, .title');
			if (titleSpan) {
				title = titleSpan.textContent.trim();
			}
		}

		// Feed name: Normal view has .flux_header with .item.website;
		// Reader view (article.phtml) has .website inside .article-header-topline or .subtitle.
		// When the element is not rendered (user config), fall back to data attributes
		// or the sidebar feed list using the data-feed attribute.
		const feedNameEl = flux.querySelector('.flux_header .item.website a, .item.website a, .article-header-topline .website a, .subtitle .website a, .website a');
		const fluxHeader = flux.querySelector('.flux_header');
		let feedName = feedNameEl ? feedNameEl.textContent.trim() : '';
		if (!feedName && fluxHeader && fluxHeader.dataset.websiteName) {
			feedName = fluxHeader.dataset.websiteName;
		}
		if (!feedName) {
			// Last resort: look up from sidebar using data-feed attribute
			const feedId = flux.dataset.feed;
			if (feedId) {
				const sidebarFeedEl = document.querySelector('#f_' + feedId + ' > a .title');
				if (sidebarFeedEl) {
					feedName = sidebarFeedEl.textContent.trim();
				}
			}
		}

		// Favicon: Normal view has it in .flux_header; Reader view may have it
		// in .article-header-topline. Fall back to sidebar feed favicon.
		const faviconEl = flux.querySelector(
			'.flux_header .favicon img, .flux_header img.favicon, .item.favicon img, ' +
			'.article-header-topline .favicon img, .favicon img');
		let faviconSrc = faviconEl ? faviconEl.src : '';
		if (!faviconSrc) {
			const feedId = flux.dataset.feed;
			if (feedId) {
				const sidebarFavicon = document.querySelector('#f_' + feedId + ' > a img');
				if (sidebarFavicon) {
					faviconSrc = sidebarFavicon.src;
				}
			}
		}

		const dateEl = flux.querySelector('.flux_header .item.date, .flux_header .date, .item.date, .subtitle .date, time');
		const date = dateEl ? dateEl.textContent.trim() : '';

		// Author: Normal view has .item.author; Reader view renders via a helper
		const authorEl = flux.querySelector('.flux_header .item.author, .item.author, .subtitle .authors, .authors');
		const author = authorEl
			? authorEl.textContent.trim()
			: (fluxHeader && fluxHeader.dataset.articleAuthors ? fluxHeader.dataset.articleAuthors : '');

		// Get thumbnail - check for existing thumbnail or extract from content
		const thumbnail = getThumbnail(flux);

		// Get summary/description from content
		// Prefer .text (just the description body) over .content (which may
		// include FreshRSS content_header/content_footer with title/author/date)
		const contentEl = flux.querySelector('.flux_content .text') ||
			flux.querySelector('.flux_content .content');
		const summary = contentEl ? extractSummary(contentEl.innerHTML, 150) : '';
		const fullSummary = contentEl ? extractSummary(contentEl.innerHTML, 0) : '';

		// Get reading time if available
		const readingTimeEl = flux.querySelector('.reading-time');
		const readingTime = readingTimeEl ? readingTimeEl.textContent.trim() : '';

		// Create card HTML structure
		const cardHTML = createCardHTML({
			thumbnail,
			title,
			link,
			feedName,
			faviconSrc,
			summary,
			fullSummary,
			date,
			author,
			readingTime,
			entryId: flux.dataset.id
		});

		// Store the article link for click handling
		flux.dataset.articleLink = link;

		// Wrap original content in a hidden container (preserve for FreshRSS functionality like share)
		const originalWrapper = document.createElement('div');
		originalWrapper.className = 'gridview-original-content';
		originalWrapper.style.display = 'none';
		while (flux.firstChild) {
			originalWrapper.appendChild(flux.firstChild);
		}
		flux.appendChild(originalWrapper);

		// Add card view
		const cardWrapper = document.createElement('div');
		cardWrapper.className = 'gridview-card-content';
		cardWrapper.innerHTML = cardHTML;
		flux.appendChild(cardWrapper);

		// Add click handler for opening in new tab
		setupCardClickHandler(flux, link, originalWrapper);
	}

	/**
	 * Get thumbnail from flux element.
	 * Caches the result on the element so that view switches always
	 * return the same image.
	 * @param {HTMLElement} flux
	 * @returns {string|null}
	 */
	function getThumbnail(flux) {
		// 1. Return previously cached thumbnail (survives DOM changes)
		if (flux.dataset.gridviewThumb) {
			return flux.dataset.gridviewThumb;
		}

		let thumb = null;

		// 2. Check for a dedicated thumbnail element set by FreshRSS
		const thumbnailEl = flux.querySelector('.item.thumbnail img, .thumbnail img');
		if (thumbnailEl) {
			thumb = thumbnailEl.getAttribute('src') || thumbnailEl.dataset.src || null;
		}

		// 2b. Check for injected thumbnail marker (for Reading View)
		if (!thumb) {
			const marker = flux.querySelector('.gridview-thumbnail-marker[data-thumbnail]');
			if (marker) {
				thumb = marker.dataset.thumbnail;
			}
		}

		// 3. Check data attribute on the flux element itself
		if (!thumb && flux.dataset.thumbnail) {
			thumb = flux.dataset.thumbnail;
		}

		// 4. Extract from content – grab raw innerHTML BEFORE any view
		//	  switch can modify it
		if (!thumb) {
			const contentEl = flux.querySelector('.flux_content .text, .flux_content .content');
			if (contentEl) {
				thumb = extractFirstImage(contentEl.innerHTML);
			}
		}

		// 5. Persist on the element so subsequent calls (after view
		//	  switches) always return the same image
		if (thumb) {
			flux.dataset.gridviewThumb = thumb;
		}

		return thumb;
	}

	/**
	 * Extract first suitable image URL from raw HTML.
	 *
	 * Uses a temporary div to parse img elements but reads the original
	 * attribute values (getAttribute) instead of the resolved `.src`
	 * property, so relative URLs are preserved exactly as written by
	 * the feed and won't be re-resolved against the current page.
	 *
	 * @param {string} html
	 * @returns {string|null}
	 */
	function extractFirstImage(html) {
		if (!html) return null;

		const tempDiv = document.createElement('div');
		tempDiv.innerHTML = html;

		const images = tempDiv.querySelectorAll('img');

		for (const img of images) {
			// Read the *original* attribute values so the browser doesn't
			// resolve them against the current page URL.
			const src = img.getAttribute('src') ||
				img.getAttribute('data-src') ||
				img.getAttribute('data-lazy-src') ||
				img.getAttribute('data-original') ||
				img.getAttribute('data-lazy') ||
				img.getAttribute('data-srcset')?.split(',')[0]?.trim()?.split(' ')[0] ||
				img.getAttribute('srcset')?.split(',')[0]?.trim()?.split(' ')[0] ||
				null;
			if (!src) continue;

			// Only consider absolute URLs (feed images should be absolute)
			if (!src.startsWith('http')) continue;

			// Skip tracking pixels and known small image patterns
			if (isTrackingPixel(src)) continue;

			// Skip SVG images (usually icons/logos)
			if (src.toLowerCase().endsWith('.svg')) continue;

			// Skip images from theme/static asset paths (usually logos/icons)
			const srcLower = src.toLowerCase();
			if (/\/(themes?|static|assets|icons?|logos?|branding|ui|images\/ic_)\//i.test(srcLower)) {
				continue;
			}

			// Skip images with logo/icon/badge indicators in URL, alt, or class
			const alt = (img.getAttribute('alt') || '').toLowerCase();
			const className = (img.getAttribute('class') || '').toLowerCase();
			const combined = srcLower + ' ' + alt + ' ' + className;

			if (/\b(logo|icon|avatar|badge|emoji|button|brand|sprite|banner-ad)\b/.test(combined)) {
				continue;
			}

			// Check if image has explicit width/height attributes
			const width = parseInt(img.getAttribute('width') || '0', 10);
			const height = parseInt(img.getAttribute('height') || '0', 10);

			// If BOTH dimensions are explicitly specified and too small, skip.
			// (Only skip when both are known — many feeds omit one or both.)
			if (width > 0 && height > 0 && (width < 200 || height < 200)) {
				continue;
			}

			// Check for size hints in URL (e.g., ?w=800&h=533 or /800x533/)
			const urlSizeMatch = src.match(/[?&]w=(\d+)/) || src.match(/\/(\d+)x\d+[/.]/);
			const urlHeightMatch = src.match(/[?&]h=(\d+)/) || src.match(/\/\d+x(\d+)[/.]/);
			if (urlSizeMatch && urlHeightMatch) {
				const urlWidth = parseInt(urlSizeMatch[1], 10);
				const urlHeight = parseInt(urlHeightMatch[1], 10);
				if (urlWidth < 200 || urlHeight < 200) {
					continue;
				}
			}

			// Skip images with small size patterns in URL
			if (/(\d{1,2}x\d{1,2}|[_-](xs|s|sm|tiny|small|thumb|icon)\.|[_-]\d{2,3}w?\.)/.test(srcLower)) {
				continue;
			}

			return src;
		}

		return null;
	}

	/**
	 * Check if URL is likely a tracking pixel
	 * @param {string} url
	 * @returns {boolean}
	 */
	function isTrackingPixel(url) {
		const trackingPatterns = [
			/1x1/i,
			/pixel[.\-_/]/i,
			/beacon/i,
			/[.\-_/]track(er|ing)[.\-_/?]/i,
			/analytics/i,
			/\.gif$/i,
			/spacer/i,
			/blank\./i,
			/transparent/i,
			/feeds\.feedburner/i,
			/feedsportal/i
		];

		return trackingPatterns.some(pattern => pattern.test(url));
	}

	/**
	 * Extract plain text summary from HTML content.
	 * @param {string} html
	 * @param {number} maxLength - 0 means no truncation
	 * @returns {string}
	 */
	function extractSummary(html, maxLength = 150) {
		if (!html) return '';

		const tempDiv = document.createElement('div');
		tempDiv.innerHTML = html;

		// Remove script and style tags
		const scripts = tempDiv.querySelectorAll('script, style, noscript');
		scripts.forEach(el => el.remove());

		// Get text content
		let text = tempDiv.textContent || tempDiv.innerText || '';

		// Clean up whitespace
		text = text.replace(/\s+/g, ' ').trim();

		// Truncate (maxLength of 0 means no truncation)
		if (maxLength > 0 && text.length > maxLength) {
			text = text.substring(0, maxLength).trim() + '…';
		}

		return text;
	}

	/**
	 * Create card HTML structure
	 * @param {Object} data
	 * @returns {string}
	 */
	function createCardHTML(data) {
		// Use placeholder image when no thumbnail is available
		const placeholderFallback = placeholderUrl
			? `<img src="${escapeHtml(placeholderUrl)}" alt="" class="placeholder-image">`
			: '<div class="no-image">📰</div>';

		const onerrorFallback = placeholderUrl
			? `this.src='${escapeHtml(placeholderUrl)}'; this.classList.add('placeholder-image');`
			: `this.parentElement.innerHTML='<div class=\\'no-image\\'>📰</div>';`;
		const thumbnailHTML = data.thumbnail
			? `<img src="${escapeHtml(data.thumbnail)}" alt="" loading="lazy" ` +
				`onerror="this.onerror=null; ${onerrorFallback}">`
			: placeholderFallback;

		const faviconHTML = data.faviconSrc
			? `<img class="favicon" src="${escapeHtml(data.faviconSrc)}" alt="" onerror="this.style.display='none'">`
			: '';

		const readingTimeHTML = data.readingTime
			? `<span class="reading-time">${escapeHtml(data.readingTime)}</span>`
			: '';

		// Share button - triggers FreshRSS native share
		const shareButtonHTML = `<button class="action-share" title="Share" data-action="share" ` +
			`data-link="${escapeHtml(data.link)}" data-title="${escapeHtml(data.title)}" ` +
			`data-entry-id="${escapeHtml(data.entryId || '')}">🔗 Share</button>`;

		return `
			<div class="flux_header">
				<div class="card-thumbnail">
					${thumbnailHTML}
				</div>
				<div class="card-actions">
					<button class="action-read" title="Mark as read" data-action="read">✓</button>
					<button class="action-star" title="Star" data-action="star">★</button>
					${shareButtonHTML}
					<a href="${escapeHtml(data.link)}" target="_blank" rel="noopener" class="action-open" title="Open original article">↗</a>
				</div>
			</div>
			<div class="card-content">
				<div class="card-meta">
					${faviconHTML}
					<span class="feed-name">${escapeHtml(data.feedName)}</span>
					${readingTimeHTML}
				</div>
				<h3 class="card-title" title="${escapeHtml(data.title)}">
					<a href="${escapeHtml(data.link)}" target="_blank" rel="noopener"
						class="card-title-link">${escapeHtml(data.title)}</a>
				</h3>
				<div class="card-summary-wrapper">
					<p class="card-summary">${escapeHtml(data.summary)}</p>
				</div>
				<div class="card-date">${escapeHtml(data.date)}</div>
				${(data.fullSummary && data.fullSummary.length > data.summary.length)
		? `<div class="card-summary-tooltip"><p>${escapeHtml(data.fullSummary)}</p></div>`
		: ''}
			</div>
		`;
	}

	/**
	 * Escape HTML special characters
	 * @param {string} text
	 * @returns {string}
	 */
	function escapeHtml(text) {
		if (!text) return '';
		const div = document.createElement('div');
		div.textContent = text;
		return div.innerHTML;
	}

	/**
	 * Set up click handler for card
	 * @param {HTMLElement} flux
	 * @param {string} link
	 * @param {HTMLElement} originalWrapper - hidden wrapper containing original FreshRSS content
	 */
	function setupCardClickHandler(flux, link, originalWrapper) {
		flux.addEventListener('click', function (e) {
			// Only handle clicks when grid view is active
			if (!gridEnabled) return;

			// Don't handle if clicking on action buttons
			if (e.target.closest('.card-actions')) {
				return;
			}

			// Clicking on thumbnail toggles the action bar overlay
			if (e.target.closest('.card-thumbnail')) {
				e.preventDefault();
				e.stopPropagation();
				const selected = document.querySelector('.flux.card-selected');
				if (selected && selected !== flux) {
					selected.classList.remove('card-selected');
				}
				flux.classList.toggle('card-selected');
				return;
			}

			// Clicking on title link opens the article
			if (e.target.closest('.card-title')) {
				e.preventDefault();
				const articleLink = flux.dataset.articleLink || flux.dataset.link || link;
				if (articleLink && articleLink.startsWith('http')) {
					openInReaderMode(articleLink);
					markAsRead(flux);
				}
				return;
			}

			// Clicking on summary area or tooltip toggles the expanded description
			if (e.target.closest('.card-summary-wrapper') || e.target.closest('.card-summary-tooltip') || e.target.closest('.card-content')) {
				const tooltip = flux.querySelector('.card-summary-tooltip');
				if (tooltip) {
					tooltip.classList.toggle('expanded');
				}
			}
		});

		// On mobile, tapping the card thumbnail toggles
		// the action bar overlay via .card-selected class
		let cardTouchMoved = false;
		flux.addEventListener('touchstart', function () {
			cardTouchMoved = false;
		}, { passive: true });
		flux.addEventListener('touchmove', function () {
			cardTouchMoved = true;
		}, { passive: true });
		flux.addEventListener('touchend', function (e) {
			if (cardTouchMoved || !gridEnabled) return;
			// Don't interfere with title link or action button taps
			if (e.target.closest('.card-title') || e.target.closest('.card-actions')) return;

			// Thumbnail tap toggles the action bar overlay
			if (e.target.closest('.card-thumbnail')) {
				e.preventDefault();
				e.stopPropagation();
				const selected = document.querySelector('.flux.card-selected');
				if (selected && selected !== flux) {
					selected.classList.remove('card-selected');
				}
				flux.classList.toggle('card-selected');
			}
		});

		// Handle action buttons
		const readBtn = flux.querySelector('.card-actions .action-read');
		if (readBtn) {
			readBtn.addEventListener('click', function (e) {
				e.stopPropagation();
				toggleRead(flux);
			});
			// Set initial state
			if (flux.classList.contains('not_read')) {
				readBtn.classList.add('unread');
				readBtn.title = 'Mark as read';
			} else {
				readBtn.classList.remove('unread');
				readBtn.title = 'Mark as unread';
			}
		}

		const starBtn = flux.querySelector('.card-actions .action-star');
		if (starBtn) {
			starBtn.addEventListener('click', function (e) {
				e.stopPropagation();
				e.preventDefault();

				// Try to find the original bookmark link and use its href
				if (originalWrapper) {
					const originalBookmarkLink = originalWrapper.querySelector('a.bookmark, .item.manage a.bookmark, a.item-element.bookmark');
					if (originalBookmarkLink && originalBookmarkLink.href) {
						// Make AJAX call like FreshRSS does
						const url = originalBookmarkLink.href;
						const csrfToken = window.context?.csrf || document.querySelector('input[name="_csrf"]')?.value || '';

						fetch(url, {
							method: 'POST',
							credentials: 'same-origin',
							headers: {
								'Content-Type': 'application/json; charset=utf-8'
							},
							body: JSON.stringify({
								ajax: true,
								_csrf: csrfToken
							})
						}).then(response => {
							if (response.ok) {
								return response.json();
							}
							throw new Error('Failed to toggle favorite');
						}).then(data => {
							// Toggle the favorite class
							flux.classList.toggle('favorite');

							// Update the bookmark link href with new toggle URL
							if (data && data.url) {
								originalBookmarkLink.href = data.url;
							}

							// Update the favorites counter in sidebar
							const favourites = document.querySelector('#aside_feed .favorites .title');
							if (favourites) {
								const currentText = favourites.textContent;
								const match = currentText.match(/\((\d+)\)/);
								if (match) {
									const count = parseInt(match[1], 10);
									const newCount = flux.classList.contains('favorite') ? count + 1 : count - 1;
									favourites.textContent = currentText.replace(/\(\d+\)/, '(' + newCount + ')');
								}
							}

							// Update button state
							updateStarButtonState(starBtn, flux);
						}).catch(() => {
							// Silently fail - the fallback will be used
						});

						return;
					}
				}

				// Fallback: toggle manually via AJAX using entry ID
				toggleStar(flux);
				updateStarButtonState(starBtn, flux);
			});

			// Set initial state
			updateStarButtonState(starBtn, flux);
		}

		// Intercept title link click to open in reader mode
		const titleLink = flux.querySelector('.card-title-link');
		if (titleLink) {
			titleLink.addEventListener('click', function (e) {
				if (!gridEnabled) return;
				e.preventDefault();
				e.stopPropagation();
				openInReaderMode(titleLink.href);
				markAsRead(flux);
			});

			// On mobile, tapping the title should open the link directly
			// without requiring the card to be selected first
			let titleTouchMoved = false;
			titleLink.addEventListener('touchstart', function () {
				titleTouchMoved = false;
			}, { passive: true });
			titleLink.addEventListener('touchmove', function () {
				titleTouchMoved = true;
			}, { passive: true });
			titleLink.addEventListener('touchend', function (e) {
				if (titleTouchMoved || !gridEnabled) return;
				e.preventDefault();
				e.stopPropagation();
				openInReaderMode(titleLink.href);
				markAsRead(flux);
			});
		}

		const shareBtn = flux.querySelector('.card-actions .action-share');
		if (shareBtn) {
			shareBtn.addEventListener('click', function (e) {
				e.preventDefault();
				e.stopPropagation();

				// Try to find the share dropdown from the original FreshRSS content
				let dropdownMenu = null;
				if (originalWrapper) {
					const shareItem = originalWrapper.querySelector('li.item.share, .item.share');
					if (shareItem) {
						dropdownMenu = shareItem.querySelector('.dropdown-menu, ul.dropdown-menu');
						if (!dropdownMenu) {
							const dropdownToggle = shareItem.querySelector('.dropdown-toggle');
							if (dropdownToggle) {
								dropdownToggle.click();
								setTimeout(function () {
									const loadedMenu = shareItem.querySelector('.dropdown-menu, ul.dropdown-menu');
									if (loadedMenu) {
										showShareDropdown(shareBtn, loadedMenu, flux);
									} else {
										showShareFromTemplate(shareBtn, flux);
									}
								}, 100);
								return;
							}
						}
					}
				}

				if (dropdownMenu) {
					showShareDropdown(shareBtn, dropdownMenu, flux);
				} else {
					showShareFromTemplate(shareBtn, flux);
				}
			});
		}
	}

	/**
	 * Show share dropdown near the share button
	 * @param {HTMLElement} shareBtn - The share button that was clicked
	 * @param {HTMLElement} dropdownMenu - The original FreshRSS dropdown menu
	 * @param {HTMLElement} flux - The flux element
	 */
	function showShareDropdown(shareBtn, dropdownMenu, flux) {
		// Remove any existing floating dropdowns
		document.querySelectorAll('.gridview-share-dropdown').forEach(el => el.remove());

		// Clone the dropdown menu
		const dropdown = dropdownMenu.cloneNode(true);
		dropdown.className = 'gridview-share-dropdown';

		// Position it near the share button
		const btnRect = shareBtn.getBoundingClientRect();
		dropdown.style.cssText = `
			position: fixed;
			top: ${btnRect.bottom + 5}px;
			left: ${btnRect.left}px;
			z-index: 10000;
			display: block;
			background: var(--frss-background-color, #fff);
			border: 1px solid var(--frss-border-color, #ddd);
			border-radius: 4px;
			box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
			padding: 0.5rem 0;
			min-width: 180px;
			list-style: none;
		`;

		document.body.appendChild(dropdown);

		// Style the menu items
		dropdown.querySelectorAll('li').forEach(li => {
			li.style.listStyle = 'none';
		});
		dropdown.querySelectorAll('a').forEach(link => {
			link.style.cssText = `
				display: block;
				padding: 0.5rem 1rem;
				color: var(--frss-text-color, #333);
				text-decoration: none;
			`;
			link.addEventListener('mouseenter', () => {
				link.style.background = 'var(--frss-background-alt, #f5f5f5)';
			});
			link.addEventListener('mouseleave', () => {
				link.style.background = '';
			});
		});

		// Close dropdown when clicking outside
		const closeDropdown = (event) => {
			if (!dropdown.contains(event.target) && event.target !== shareBtn) {
				dropdown.remove();
				document.removeEventListener('click', closeDropdown);
				document.removeEventListener('scroll', closeOnScroll, true);
			}
		};

		// Close dropdown when scrolling
		const closeOnScroll = () => {
			dropdown.remove();
			document.removeEventListener('click', closeDropdown);
			document.removeEventListener('scroll', closeOnScroll, true);
			document.removeEventListener('keydown', handleEscape);
		};

		// Delay adding the listener so the current click doesn't close it
		setTimeout(() => {
			document.addEventListener('click', closeDropdown);
			document.addEventListener('scroll', closeOnScroll, true);
		}, 10);

		// Close on escape
		const handleEscape = (event) => {
			if (event.key === 'Escape') {
				dropdown.remove();
				document.removeEventListener('keydown', handleEscape);
				document.removeEventListener('click', closeDropdown);
				document.removeEventListener('scroll', closeOnScroll, true);
			}
		};
		document.addEventListener('keydown', handleEscape);
	}

	/**
	 * Show share dropdown built from the server-side template.
	 * Used when the original FreshRSS share dropdown is not available
	 * (e.g. Reader view where entry_share_menu is not rendered).
	 * @param {HTMLElement} shareBtn
	 * @param {HTMLElement} flux
	 */
	function showShareFromTemplate(shareBtn, flux) {
		if (!shareMenuHtml) {
			copyToClipboard(shareBtn.dataset.link, shareBtn.dataset.title);
			return;
		}

		const entryLink = shareBtn.dataset.link || '';
		const entryTitle = shareBtn.dataset.title || '';
		const entryId = shareBtn.dataset.entryId || '';

		// Parse the template into DOM first, then replace placeholders
		// in attribute values (not raw HTML) to avoid encoding issues
		const temp = document.createElement('div');
		temp.innerHTML = shareMenuHtml;
		const menu = temp.firstElementChild;
		if (!menu) {
			copyToClipboard(entryLink, entryTitle);
			return;
		}

		// Replace placeholders in link hrefs (FreshRSS does raw replacement)
		menu.querySelectorAll('a[href]').forEach(function (a) {
			a.href = a.getAttribute('href')
				.replace(/--link--/g, entryLink)
				.replace(/--titleText--/g, entryTitle)
				.replace(/--entryId--/g, entryId);
		});
		// Replace in form actions and hidden input values
		menu.querySelectorAll('form[action]').forEach(function (form) {
			form.action = form.getAttribute('action')
				.replace(/--link--/g, entryLink)
				.replace(/--titleText--/g, entryTitle)
				.replace(/--entryId--/g, entryId);
		});
		menu.querySelectorAll('input[type="hidden"]').forEach(function (input) {
			input.value = (input.getAttribute('value') || '')
				.replace(/--link--/g, entryLink)
				.replace(/--titleText--/g, entryTitle)
				.replace(/--entryId--/g, entryId);
		});
		// Replace in button data attributes
		menu.querySelectorAll('button[data-url]').forEach(function (btn) {
			btn.dataset.url = (btn.getAttribute('data-url') || '')
				.replace(/--link--/g, entryLink)
				.replace(/--titleText--/g, entryTitle)
				.replace(/--entryId--/g, entryId);
		});

		showShareDropdown(shareBtn, menu, flux);
	}

	/**
	 * Copy article URL to clipboard
	 * @param {string} url
	 * @param {string} title
	 */
	function copyToClipboard(url, title) {
		const text = title + '\n' + url;
		navigator.clipboard.writeText(text).then(() => {
			// Show a brief notification
			showToast('Link copied to clipboard!');
		}).catch(() => {
			// Fallback for older browsers
			const textarea = document.createElement('textarea');
			textarea.value = text;
			document.body.appendChild(textarea);
			textarea.select();
			document.execCommand('copy');
			document.body.removeChild(textarea);
			showToast('Link copied to clipboard!');
		});
	}

	/**
	 * Open URL in a new tab.
	 *
	 * Note: Firefox blocks window.open() for about:reader URLs from web
	 * content, so we always open the plain URL. Users can activate their
	 * browser's built-in reader mode manually (e.g. F9 in Firefox).
	 * @param {string} url
	 */
	function openInReaderMode(url) {
		window.open(url, '_blank', 'noopener');
	}

	/**
	 * Show a brief toast notification
	 * @param {string} message
	 */
	function showToast(message) {
		const existing = document.querySelector('.gridview-toast');
		if (existing) existing.remove();

		const toast = document.createElement('div');
		toast.className = 'gridview-toast';
		toast.textContent = message;
		toast.style.cssText = `
			position: fixed;
			bottom: 20px;
			left: 50%;
			transform: translateX(-50%);
			background: #333;
			color: #fff;
			padding: 12px 24px;
			border-radius: 8px;
			font-size: 14px;
			z-index: 10000;
			animation: gridview-toast-fade 2s ease-in-out forwards;
		`;
		document.body.appendChild(toast);

		setTimeout(() => toast.remove(), 2000);
	}

	/**
	 * Mark entry as read
	 * @param {HTMLElement} flux
	 */
	function markAsRead(flux) {
		if (!flux.classList.contains('not_read')) return;

		// Find the original FreshRSS read link in the hidden wrapper
		// and click it so FreshRSS's own mark_read() handles persistence
		const originalWrapper = flux.querySelector('.gridview-original-content');
		if (originalWrapper) {
			const originalReadLink = originalWrapper.querySelector('a.read');
			if (originalReadLink) {
				originalReadLink.click();
				return;
			}
		}

		// Fallback: use FreshRSS native function or AJAX
		flux.classList.remove('not_read');
		if (typeof window.mark_read === 'function') {
			window.mark_read(flux, true);
		} else {
			const entryId = flux.dataset.id;
			if (entryId) {
				const url = './?c=entry&a=read&id=' + encodeURIComponent(entryId) + '&is_read=1';
				fetch(url, { method: 'POST', credentials: 'same-origin' }).catch(function () {});
			}
		}
	}

	/**
	 * Toggle read status
	 * @param {HTMLElement} flux
	 */
	function toggleRead(flux) {
		// Find the original FreshRSS read link in the hidden wrapper
		// and click it so FreshRSS's own mark_read() handles persistence
		const originalWrapper = flux.querySelector('.gridview-original-content');
		if (originalWrapper) {
			const originalReadLink = originalWrapper.querySelector('a.read');
			if (originalReadLink) {
				originalReadLink.click();
				return;
			}
		}

		// Fallback: toggle class and send AJAX request
		const entryId = flux.dataset.id;
		if (!entryId) return;

		flux.classList.toggle('not_read');
		const nowUnread = flux.classList.contains('not_read');
		const url = './?c=entry&a=read&id=' + encodeURIComponent(entryId) + '&is_read=' + (nowUnread ? '0' : '1');
		fetch(url, { method: 'POST', credentials: 'same-origin' }).catch(function () {
			flux.classList.toggle('not_read');
		});
	}

	/**
	 * Toggle star/favorite status
	 * @param {HTMLElement} flux
	 */
	function toggleStar(flux) {
		const isFavorite = flux.classList.contains('favorite');
		flux.classList.toggle('favorite');

		const entryId = flux.dataset.id || flux.id?.replace('flux_', '');
		if (entryId) {
			// Build the bookmark URL similar to FreshRSS
			const url = './?c=entry&a=bookmark&id=' + encodeURIComponent(entryId) + '&is_favorite=' + (isFavorite ? '0' : '1');

			// Get CSRF token from FreshRSS context if available
			const csrfToken = window.context?.csrf || document.querySelector('input[name="_csrf"]')?.value || '';

			fetch(url, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/json; charset=utf-8'
				},
				body: JSON.stringify({
					ajax: true,
					_csrf: csrfToken
				})
			}).then(response => {
				if (response.ok) {
					return response.json();
				}
				throw new Error('Failed to toggle favorite');
			}).then(data => {
				// Update the bookmark link href if present in originalWrapper
				if (data && data.url) {
					const bookmarkLinks = flux.querySelectorAll('a.bookmark');
					bookmarkLinks.forEach(a => { a.href = data.url; });
				}
			}).catch(() => {
				// Revert on error
				flux.classList.toggle('favorite');
			});
		}
	}

	/**
	 * Update star button visual state
	 * @param {HTMLElement} starBtn
	 * @param {HTMLElement} flux
	 */
	function updateStarButtonState(starBtn, flux) {
		if (flux.classList.contains('favorite')) {
			starBtn.classList.add('active');
			starBtn.style.color = '#f5a623';
		} else {
			starBtn.classList.remove('active');
			starBtn.style.color = '';
		}
	}

	/**
	 * Asynchronously fetch OG images for entries that need them.
	 * Entries are identified by .gridview-og-fetch markers injected
	 * server-side. Cards display with placeholders immediately; when
	 * an OG image is retrieved, it replaces the placeholder with a
	 * smooth fade-in transition.
	 */
	function asyncFetchOgImages() {
		const stream = document.getElementById('stream');
		if (!stream || !ogFetchUrl) return;

		const markers = stream.querySelectorAll('.gridview-og-fetch:not(.gridview-og-processing)');
		if (markers.length === 0) return;

		const queue = Array.from(markers);
		const MAX_CONCURRENT = 3;
		let active = 0;

		function processNext() {
			while (queue.length > 0 && active < MAX_CONCURRENT) {
				const marker = queue.shift();
				const url = marker.dataset.ogUrl;

				if (!url) continue;

				marker.classList.add('gridview-og-processing');

				const flux = marker.closest('.flux');
				if (!flux) continue;

				active++;

				const fetchEndpoint = ogFetchUrl + '&ajax=og&url=' + encodeURIComponent(url);

				fetch(fetchEndpoint, { credentials: 'same-origin' })
					.then(function (response) { return response.json(); })
					.then(function (data) {
						if (data && data.image) {
							updateCardThumbnail(flux, data.image);
							// Cache on the element so view-switches keep the image
							flux.dataset.gridviewThumb = data.image;
						}
					})
					.catch(function () { /* keep placeholder on error */ })
					.finally(function () {
						active--;
						processNext();
					});
			}
		}

		processNext();
	}

	/**
	 * Update a card's thumbnail with a newly fetched OG image.
	 * Pre-loads the image before swapping to avoid flicker.
	 * @param {HTMLElement} flux
	 * @param {string} imageUrl
	 */
	function updateCardThumbnail(flux, imageUrl) {
		const thumbnailContainer = flux.querySelector('.card-thumbnail');
		if (!thumbnailContainer) return;

		// Pre-load the image so we only swap when it's ready
		const preload = new Image();
		preload.onload = function () {
			const newImg = document.createElement('img');
			newImg.src = imageUrl;
			newImg.alt = '';
			newImg.loading = 'lazy';
			newImg.onerror = function () {
				this.onerror = null;
				if (placeholderUrl) {
					this.src = placeholderUrl;
					this.classList.add('placeholder-image');
				} else {
					this.parentElement.innerHTML = '<div class="no-image">📰</div>';
				}
			};

			// Fade transition
			newImg.style.opacity = '0';
			newImg.style.transition = 'opacity 0.3s ease';

			thumbnailContainer.innerHTML = '';
			thumbnailContainer.appendChild(newImg);

			// Trigger reflow then fade in
			requestAnimationFrame(function () {
				newImg.style.opacity = '1';
			});
		};
		preload.onerror = function () { /* keep current placeholder */ };
		preload.src = imageUrl;
	}

	/**
	 * Set up mutation observer to handle dynamically loaded entries
	 */
	function observeNewEntries() {
		const stream = document.getElementById('stream');
		if (!stream || !stream.classList.contains('grid')) return;

		// Disconnect any previous observer before creating a new one
		if (streamObserver) {
			streamObserver.disconnect();
			streamObserver = null;
		}

		const observer = new MutationObserver(function (mutations) {
			let hasNewFlux = false;
			mutations.forEach(function (mutation) {
				mutation.addedNodes.forEach(function (node) {
					if (node.nodeType === 1 && node.classList && node.classList.contains('flux')) {
						hasNewFlux = true;
						transformSingleFlux(node);
					}
				});
			});
			// Clean up skeleton after dynamically added items are transformed
			if (hasNewFlux) {
				removeLoadingSkeleton(stream);
				asyncFetchOgImages();
				// Re-observe all flux elements including newly added ones
				observeFluxStateChanges();
			}
		});

		observer.observe(stream, { childList: true, subtree: true });
		streamObserver = observer;
	}

	/**
	 * Set up mutation observer to watch for class changes on flux elements.
	 * This ensures that read/unread state changes made outside of grid view
	 * (or by FreshRSS core) are reflected in the grid view visual state.
	 */
	function observeFluxStateChanges() {
		const stream = document.getElementById('stream');
		if (!stream || !stream.classList.contains('grid')) return;

		// Disconnect any previous observer before creating a new one
		if (classObserver) {
			classObserver.disconnect();
			classObserver = null;
		}

		const observer = new MutationObserver(function (mutations) {
			mutations.forEach(function (mutation) {
				if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
					const flux = mutation.target;
					// Only handle flux elements that are transformed
					if (flux.classList && flux.classList.contains('flux') &&
						flux.classList.contains('gridview-transformed')) {
						syncFluxVisualState(flux);
					}
				}
			});
		});

		// Observe all flux elements for class attribute changes
		const fluxElements = stream.querySelectorAll('.flux.gridview-transformed');
		fluxElements.forEach(function (flux) {
			observer.observe(flux, { attributes: true, attributeFilter: ['class'] });
		});

		classObserver = observer;

		// Sync state for all elements after observer is set up
		// Use requestAnimationFrame to avoid blocking the main thread
		requestAnimationFrame(function () {
			fluxElements.forEach(function (flux) {
				syncFluxVisualState(flux);
			});
		});
	}

	/**
	 * Synchronize the visual state of a flux element based on its classes.
	 * Updates read button and star button states to match the flux element's current classes.
	 * @param {HTMLElement} flux
	 */
	function syncFluxVisualState(flux) {
		const isUnread = flux.classList.contains('not_read');

		// Update read button state
		const readBtn = flux.querySelector('.card-actions .action-read');
		if (readBtn) {
			if (isUnread) {
				readBtn.classList.add('unread');
				readBtn.title = 'Mark as read';
			} else {
				readBtn.classList.remove('unread');
				readBtn.title = 'Mark as unread';
			}
		}

		// Update star button state
		const starBtn = flux.querySelector('.card-actions .action-star');
		if (starBtn) {
			updateStarButtonState(starBtn, flux);
		}

		// Explicitly set border-left as an inline style to guarantee the
		// visual indicator reflects the current state. Pure CSS class
		// selectors sometimes fail to re-evaluate when switching views.
		if (isUnread) {
			flux.style.borderLeft = '3px solid var(--frss-accent-color, #4a90d9)';
		} else {
			flux.style.borderLeft = '';
		}
	}

	/**
	 * Observe the parent of #stream so that when FreshRSS replaces
	 * the stream element entirely (feed navigation, AJAX reload),
	 * we immediately re-apply grid mode before the browser paints
	 * the default list layout.
	 */
	function observeStreamReplacement() {
		if (parentObserver) {
			parentObserver.disconnect();
			parentObserver = null;
		}

		const stream = document.getElementById('stream');
		const parent = stream ? stream.parentElement : document.getElementById('global') || document.body;
		if (!parent) return;

		parentObserver = new MutationObserver(function (mutations) {
			if (!gridEnabled) return;

			for (const mutation of mutations) {
				for (const node of mutation.addedNodes) {
					if (node.nodeType !== 1) continue;

					// Check if the added node IS the new #stream
					let newStream = null;
					if (node.id === 'stream') {
						newStream = node;
					} else if (node.querySelector) {
						newStream = node.querySelector('#stream');
					}

					if (newStream && !newStream.classList.contains('grid')) {
						// Immediately apply grid mode before the browser renders
						newStream.classList.add('grid');
						newStream.style.setProperty('--gridview-columns', columns);
						// Re-wrap content if the scroll wrapper was lost during AJAX nav
						if (stickyNavEnabled) {
							wrapContentForStickyNav();
						}
						updateGridHeader(newStream);
						showLoadingSkeleton(newStream);

						// Transform entries in a microtask so DOM is settled
						Promise.resolve().then(() => {
							transformFluxToCards();
							removeLoadingSkeleton(newStream);
							asyncFetchOgImages();
							observeNewEntries();
							observeFluxStateChanges();
						});
					}
				}

				// Also handle the case where children of #stream are
				// wholesale replaced (innerHTML swap) rather than the
				// stream element itself being replaced.
				if (mutation.target && mutation.target.id === 'stream') {
					const target = mutation.target;
					if (gridEnabled && target.classList.contains('grid')) {
						// New children were added; show skeleton while transforming
						const untransformed = target.querySelectorAll('.flux:not(.gridview-transformed)');
						if (untransformed.length > 0) {
							showLoadingSkeleton(target);
							Promise.resolve().then(() => {
								transformFluxToCards();
								removeLoadingSkeleton(target);
								asyncFetchOgImages();
								observeFluxStateChanges();
							});
						}
					}
				}
			}
		});

		parentObserver.observe(parent, { childList: true, subtree: true });
	}

	/**
	 * Handle FreshRSS AJAX navigation: re-apply grid mode after the
	 * new stream content has been loaded.
	 */
	function handleAjaxNavigation() {
		if (!gridEnabled) return;

		const stream = document.getElementById('stream');
		if (!stream) return;

		// Immediately apply grid class if missing
		if (!stream.classList.contains('grid')) {
			stream.classList.add('grid');
			stream.style.setProperty('--gridview-columns', columns);
		}

		// Re-wrap content if the scroll wrapper was lost during AJAX nav
		if (stickyNavEnabled) {
			wrapContentForStickyNav();
		}

		updateGridHeader(stream);
		showLoadingSkeleton(stream);
		transformFluxToCards();
		removeLoadingSkeleton(stream);
		asyncFetchOgImages();
		observeNewEntries();
		observeFluxStateChanges();
		observeNewArticleNotification();
		if (stickyNavEnabled) {
			setupAutoLoadMore();
		}
	}

	// Initialize when DOM is ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initGridView);
	} else {
		initGridView();
	}

	// Also listen for FreshRSS context loaded event
	document.addEventListener('freshrss:globalContextLoaded', initGridView);

	// Re-apply grid on AJAX navigation events
	if (typeof window.jQuery !== 'undefined') {
		window.jQuery(document).on('freshrss:entries-loaded', handleAjaxNavigation);
	}

	// FreshRSS may also fire a custom event without jQuery
	document.addEventListener('freshrss:entries-loaded', handleAjaxNavigation);
})();
