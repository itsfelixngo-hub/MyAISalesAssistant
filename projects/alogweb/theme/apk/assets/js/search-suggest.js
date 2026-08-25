/*
 * Title suggestions under the header search field.
 *
 * Progressive enhancement: the form is a plain GET and submits fine with this
 * script blocked. Nothing here is required to reach the results page.
 */
(function () {
	'use strict';

	var form = document.querySelector('form.site-search');
	if (!form) { return; }

	var input = form.querySelector('input[type="search"]');
	var list = form.querySelector('.suggest');
	if (!input || !list) { return; }

	var endpoint = form.getAttribute('data-suggest');
	if (!endpoint) { return; }

	var items = [];
	var active = -1;
	var controller = null;
	var timer = null;
	var lastQuery = '';

	function close() {
		list.hidden = true;
		list.innerHTML = '';
		items = [];
		active = -1;
		input.setAttribute('aria-expanded', 'false');
		input.removeAttribute('aria-activedescendant');
	}

	function highlight(text, term) {
		var at = text.toLowerCase().indexOf(term.toLowerCase());
		if (at < 0) { return document.createTextNode(text); }
		var frag = document.createDocumentFragment();
		frag.appendChild(document.createTextNode(text.slice(0, at)));
		var mark = document.createElement('mark');
		mark.textContent = text.slice(at, at + term.length);
		frag.appendChild(mark);
		frag.appendChild(document.createTextNode(text.slice(at + term.length)));
		return frag;
	}

	function render(results, term) {
		list.innerHTML = '';
		items = [];
		if (!results.length) { close(); return; }

		results.forEach(function (r, i) {
			var li = document.createElement('li');
			li.id = 'suggest-' + i;
			li.className = 'suggest-item';
			li.setAttribute('role', 'option');
			li.setAttribute('aria-selected', 'false');

			if (r.icon) {
				var img = document.createElement('img');
				img.src = r.icon;
				img.alt = '';
				img.width = 28;
				img.height = 28;
				img.loading = 'lazy';
				li.appendChild(img);
			}

			var body = document.createElement('span');
			var name = document.createElement('span');
			name.className = 'suggest-name';
			name.appendChild(highlight(r.name, term));
			body.appendChild(name);

			var sub = [r.dev, r.cat].filter(Boolean).join(' · ');
			if (sub) {
				var meta = document.createElement('span');
				meta.className = 'suggest-meta';
				meta.textContent = sub;
				body.appendChild(meta);
			}
			li.appendChild(body);

			li.addEventListener('mousedown', function (e) {
				e.preventDefault();          // keep focus; blur would close first
				window.location.href = r.url;
			});
			li.addEventListener('mouseenter', function () { setActive(i); });

			list.appendChild(li);
			items.push({ el: li, url: r.url });
		});

		list.hidden = false;
		input.setAttribute('aria-expanded', 'true');
		active = -1;
	}

	function setActive(i) {
		if (active > -1 && items[active]) {
			items[active].el.classList.remove('is-active');
			items[active].el.setAttribute('aria-selected', 'false');
		}
		active = i;
		if (i > -1 && items[i]) {
			items[i].el.classList.add('is-active');
			items[i].el.setAttribute('aria-selected', 'true');
			input.setAttribute('aria-activedescendant', items[i].el.id);
		} else {
			input.removeAttribute('aria-activedescendant');
		}
	}

	function fetchSuggestions(term) {
		if (controller) { controller.abort(); }   // a stale reply must not win
		controller = new AbortController();

		fetch(endpoint + encodeURIComponent(term), {
			signal: controller.signal,
			headers: { 'Accept': 'application/json' }
		})
			.then(function (r) { return r.ok ? r.json() : { results: [] }; })
			.then(function (d) {
				if (input.value.trim() !== term) { return; }   // typed on since
				render(d.results || [], term);
			})
			.catch(function () { /* aborted or offline: leave the form usable */ });
	}

	input.addEventListener('input', function () {
		var term = input.value.trim();
		if (term === lastQuery) { return; }
		lastQuery = term;

		clearTimeout(timer);
		if (term.length < 2) { close(); return; }
		timer = setTimeout(function () { fetchSuggestions(term); }, 150);
	});

	input.addEventListener('keydown', function (e) {
		if (list.hidden) { return; }
		if (e.key === 'ArrowDown') {
			e.preventDefault();
			setActive(active + 1 >= items.length ? 0 : active + 1);
		} else if (e.key === 'ArrowUp') {
			e.preventDefault();
			setActive(active - 1 < 0 ? items.length - 1 : active - 1);
		} else if (e.key === 'Enter' && active > -1) {
			e.preventDefault();
			window.location.href = items[active].url;
		} else if (e.key === 'Escape') {
			close();
		}
	});

	/*
	 * Belt and braces: the Enter keydown above already prevents the implicit
	 * submission, but a submission can still reach the form by other routes -
	 * an IME commit, or a browser that fires submit without a keydown. If a
	 * suggestion is highlighted, that is what the visitor meant to open.
	 */
	form.addEventListener('submit', function (e) {
		if (!list.hidden && active > -1 && items[active]) {
			e.preventDefault();
			window.location.href = items[active].url;
		}
	});

	input.addEventListener('blur', function () { setTimeout(close, 120); });
	document.addEventListener('click', function (e) {
		if (!form.contains(e.target)) { close(); }
	});
})();
