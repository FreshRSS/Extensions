'use strict';

window.addEventListener('load', function() {
	const submitBtn = document.querySelector('[type="submit"]');
	function listener(e) {
		e.preventDefault();
		grecaptcha.ready(function() {
			grecaptcha.execute(document.querySelector('#siteKey').innerHTML, {action: 'submit'}).then(function(token) {
				const form = document.querySelector('form');
				const res = form.querySelector('input[name="g-recaptcha-response"]');
				if (res) {
					res.remove();
				}

				form.insertAdjacentHTML('beforeend', `<input type="hidden" name="g-recaptcha-response" value="${token}" />`);
				submitBtn.removeEventListener('click', listener);
				submitBtn.click();
				submitBtn.addEventListener('click', listener);
			});
		});
	}
	submitBtn.addEventListener('click', listener);
});
