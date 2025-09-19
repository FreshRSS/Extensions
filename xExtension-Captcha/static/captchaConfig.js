'use strict';

/* globals slider, providerConfig, captchaReset, clearFields, init_password_observers, data_auto_leave_validation */

function initCaptchaConfig() {
	const captchaProvider = document.querySelector('select#captchaProvider');
	if (!captchaProvider) {
		return;
	}

	function onChange() {
		const provider = captchaProvider.value;
		const commonTmpl = document.querySelector('.captchaTmpl#common');
		const tmpl = document.querySelector(`.captchaTmpl#${provider}`);
		providerConfig.innerHTML = provider !== 'none' ? commonTmpl.innerHTML + tmpl.innerHTML : '';
		init_password_observers(document.body);
		data_auto_leave_validation(document.body);
	}

	captchaProvider.onchange = onChange;
	captchaReset.onclick = function (e) {
		e.preventDefault();
		captchaReset.form.reset();
		onChange();
	};

	onChange();

	clearFields.onclick = function (e) {
		e.preventDefault();
		document.querySelectorAll('input[type="text"], input[type="password"]').forEach(el => {
			el.value = '';
		});
	};
}

window.addEventListener('load', function () {
	if (typeof slider !== 'undefined') {
		slider.addEventListener('freshrss:slider-load', initCaptchaConfig);
	}
	initCaptchaConfig();
});
