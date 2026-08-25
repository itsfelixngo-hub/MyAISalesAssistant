/*
 * Turns the slider into live rotation feedback for the captcha image.
 *
 * Progressive enhancement only: the range input already submits a value in
 * degrees without any of this, so the form works with the script blocked - it
 * is just harder to aim.
 */
(function () {
	'use strict';

	var slider = document.getElementById('cf-rotation');
	var image = document.getElementById('cf-captcha');
	var out = document.getElementById('cf-rotation-out');
	if (!slider || !image) { return; }

	function apply() {
		var deg = parseInt(slider.value, 10) || 0;
		image.style.transform = 'rotate(' + deg + 'deg)';
		if (out) { out.textContent = deg + '°'; }
	}

	slider.addEventListener('input', apply);
	slider.addEventListener('change', apply);
	apply();
})();
