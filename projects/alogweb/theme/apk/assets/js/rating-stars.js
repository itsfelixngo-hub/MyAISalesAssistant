/*
 * Draws the five-star rating into each <canvas class="stars">.
 *
 * The score itself is already in the HTML next to the canvas, so a crawler,
 * a screen reader and a visitor whose JavaScript has not run yet all still get
 * the rating - the stars are the decoration, not the data.
 */
(function () {
	'use strict';

	var STARS = 5;

	function starPath(ctx, cx, cy, outer, inner) {
		ctx.beginPath();
		for (var i = 0; i < 10; i++) {
			var radius = i % 2 ? inner : outer;
			var angle = -Math.PI / 2 + (i * Math.PI) / STARS;
			var x = cx + Math.cos(angle) * radius;
			var y = cy + Math.sin(angle) * radius;
			if (i === 0) { ctx.moveTo(x, y); } else { ctx.lineTo(x, y); }
		}
		ctx.closePath();
	}

	function palette() {
		var css = getComputedStyle(document.documentElement);
		return {
			fill:  (css.getPropertyValue('--gold') || '').trim() || '#A8720F',
			track: (css.getPropertyValue('--surface-3') || '').trim() || '#E8EFED'
		};
	}

	function drawRow(ctx, size, gap, colour) {
		var outer = size / 2;
		var inner = outer * 0.4;
		ctx.fillStyle = colour;
		for (var i = 0; i < STARS; i++) {
			starPath(ctx, i * (size + gap) + outer, outer, outer, inner);
			ctx.fill();
		}
	}

	function render(canvas, colours) {
		var value = parseFloat(canvas.getAttribute('data-rating'));
		if (!isFinite(value)) { return; }
		value = Math.max(0, Math.min(STARS, value));

		var size = parseFloat(canvas.getAttribute('data-size')) || 12;
		var gap = Math.max(1, Math.round(size * 0.18));
		var width = STARS * size + (STARS - 1) * gap;
		var ratio = window.devicePixelRatio || 1;

		canvas.width = Math.round(width * ratio);
		canvas.height = Math.round(size * ratio);
		canvas.style.width = width + 'px';
		canvas.style.height = size + 'px';

		var ctx = canvas.getContext('2d');
		if (!ctx) { return; }
		ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
		ctx.clearRect(0, 0, width, size);

		drawRow(ctx, size, gap, colours.track);

		// The filled row is the same row, clipped to the score. A half star is
		// then genuinely half a star rather than a rounded-off whole one.
		var filled = (value / STARS) * width;
		if (filled <= 0) { return; }
		ctx.save();
		ctx.beginPath();
		ctx.rect(0, 0, filled, size);
		ctx.clip();
		drawRow(ctx, size, gap, colours.fill);
		ctx.restore();
	}

	function renderAll() {
		var colours = palette();
		var list = document.querySelectorAll('canvas.stars');
		for (var i = 0; i < list.length; i++) { render(list[i], colours); }
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', renderAll);
	} else {
		renderAll();
	}

	// The palette flips with the viewer's colour scheme, so the stars have to
	// be redrawn - canvas keeps no link to the CSS custom property it read.
	if (window.matchMedia) {
		var scheme = window.matchMedia('(prefers-color-scheme: dark)');
		if (scheme.addEventListener) { scheme.addEventListener('change', renderAll); }
		else if (scheme.addListener) { scheme.addListener(renderAll); }
	}
})();
