'use strict';

window.addEventListener("load", function () {
	// eslint-disable-next-line no-undef
	const i18n = context.extensions.showfeedid_i18n;

	let div = document.querySelector('h1 ~ div');
	if (div.classList.contains('drop-section')) {
		div = document.createElement('div');
		document.querySelector('h1').after(div);
	}

	const button = document.createElement('a');

	button.classList.add('btn');
	button.classList.add('btn-icon-text');
	button.id = 'showFeedId';
	button.innerHTML = '<img class="icon" src="../themes/icons/look.svg" /> <span>' + i18n.show + '</span>';

	div.appendChild(button);

	const parent = button.parentElement;
	parent.style.display = 'inline-flex';
	parent.style.flexWrap = 'wrap';
	parent.style.gap = '0.5rem';

	// Check if only feeds with errors are being shown
	if (document.querySelector('main.post > p.alert.alert-warn')) {
		parent.style.flexDirection = 'column';
		button.style.marginTop = '0.5rem';
	}

	const buttonText = button.querySelector('span');

	button.addEventListener('click', function () {
		if (document.querySelector('.feed-id, .cat-id')) {
			buttonText.innerText = i18n.show;
		} else {
			buttonText.innerText = i18n.hide;
		}

		const feeds = document.querySelectorAll('li.item.feed');

		feeds.forEach(function (feed) {
			const feedId = feed.dataset.feedId;
			const feedname_elem = feed.getElementsByClassName('item-title')[0];
			if (feedname_elem) {
				if (!feedname_elem.querySelector('.feed-id')) {
					feedname_elem.insertAdjacentHTML('beforeend', '<span class="feed-id"> (ID: ' + feedId + ')</span>');
					return;
				}
				feedname_elem.querySelector('.feed-id').remove();
			}
		});

		const cats = document.querySelectorAll('div.box > ul.box-content');

		cats.forEach(function (cat) {
			const catId = cat.dataset.catId;
			const catname_elem = cat.parentElement.querySelectorAll('div.box-title > h2')[0];
			if (catname_elem) {
				if (!catname_elem.querySelector('.cat-id')) {
					catname_elem.insertAdjacentHTML('beforeend', '<span class="cat-id"> (ID: ' + catId + ')</span>');
					return;
				}
				catname_elem.querySelector('.cat-id').remove();
			}
		});
	});
});
