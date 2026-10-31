/**
 * Theme-Backend-Erweiterungen fuer "backendSuiteFonts" (Schriftarten-Picker,
 * wie auf gwfh.mranftl.com/fonts) und "backendSuiteColors" (Uebersicht der
 * aktuell in _vars_colors.scss gebundenen Farben).
 *
 * Laeuft rein client-seitig: haengt sich an die nativen Contao-Textareas
 * (#ctrl_backendSuiteFonts / #ctrl_backendSuiteColors) und erweitert sie um
 * zusaetzliche UI, ohne deren Formular-Verhalten zu aendern - der
 * PHP-seitige Import (FontImporter/ColorImporter) bleibt dadurch
 * unveraendert und erwartet weiterhin dasselbe Zeilenformat. Faellt die
 * Erweiterung aus irgendeinem Grund aus (z.B. JS deaktiviert), bleibt die
 * normale Textarea als Fallback voll nutzbar.
 *
 * Der Projekt-Ordnername (fuer _fontface.scss / _vars_colors.scss) wird
 * zur Laufzeit aus dem bereits im selben Formular vorhandenen Feld
 * "templates" (tl_theme) gelesen, nicht fest einprogrammiert - dadurch
 * funktioniert dieses Skript unveraendert in jedem Projekt, das die
 * "files/public/<project>/theme/..." Ordnerkonvention verwendet.
 *
 * @author Dinko Skopljak <ds@futura-web.de>
 */
(function () {
	'use strict';

	var FONTS_URL = '/bundles/contaobackendsuite/backend/google-fonts.json';

	function projectSlug() {
		var field = document.getElementById('ctrl_templates');
		var raw = (field && field.value ? field.value : 'project');

		// "templates" speichert den Pfad relativ zum Projekt-Root (z.B.
		// "templates/project"), nicht nur den Ordnernamen.
		var parts = raw.split('/');

		return parts[parts.length - 1] || 'project';
	}

	function fontfaceUrl() {
		return '/files/public/' + projectSlug() + '/theme/css/include/_fontface.scss';
	}

	function varsColorsUrl() {
		return '/files/public/' + projectSlug() + '/theme/css/include/variables/_vars_colors.scss';
	}

	function fetchText(url) {
		return fetch(url, { cache: 'no-store' }).then(function (res) {
			return res.ok ? res.text() : '';
		}).catch(function () {
			return '';
		});
	}

	function fetchJson(url) {
		return fetch(url, { cache: 'no-store' }).then(function (res) {
			return res.ok ? res.json() : [];
		}).catch(function () {
			return [];
		});
	}

	function el(tag, className, html) {
		var node = document.createElement(tag);

		if (className) {
			node.className = className;
		}

		if (undefined !== html) {
			node.innerHTML = html;
		}

		return node;
	}

	/* ------------------------------------------------------------ Fonts */

	function variantLabel(variant) {
		var isItalic = /italic$/.test(variant);
		var weight = variant.replace('italic', '');

		if ('' === weight || 'regular' === weight) {
			weight = '400';
		}

		return isItalic ? weight + ' kursiv' : weight;
	}

	function variantSort(a, b) {
		var wa = parseInt(a.replace('italic', '').replace('regular', '400'), 10);
		var wb = parseInt(b.replace('italic', '').replace('regular', '400'), 10);

		if (wa !== wb) {
			return wa - wb;
		}

		return /italic$/.test(a) ? 1 : -1;
	}

	function parseFontFace(content) {
		var re = /font-family:\s*"([^"]+)";\s*\r?\n\s*font-style:\s*([a-z]+);\s*\r?\n\s*font-weight:\s*(\d+);/g;
		var grouped = {};
		var m;

		while (null !== (m = re.exec(content))) {
			var family = m[1];
			var suffix = 'italic' === m[2] ? ' kursiv' : '';

			grouped[family] = grouped[family] || [];
			grouped[family].push(m[3] + suffix);
		}

		return grouped;
	}

	function buildCurrentFontsSummary(grouped) {
		var box = el('div', 'bs-fontpicker__current');
		box.appendChild(el('strong', null, 'Aktuell eingebunden'));

		var families = Object.keys(grouped);

		if (0 === families.length) {
			box.appendChild(el('p', 'bs-fontpicker__hint', 'Noch keine Schriftart importiert.'));

			return box;
		}

		var list = el('ul');

		families.forEach(function (family) {
			var li = el('li');
			li.appendChild(document.createTextNode(family + ' '));
			li.appendChild(el('span', 'bs-fontpicker__hint', '(' + grouped[family].join(', ') + ')'));
			list.appendChild(li);
		});

		box.appendChild(list);

		return box;
	}

	function FontPicker(textarea) {
		this.textarea = textarea;
		this.blocks = [];
		this.fonts = [];

		this.parseExisting(textarea.value);
		this.buildDom();
		this.render();

		var self = this;

		fetchJson(FONTS_URL).then(function (fonts) {
			self.fonts = fonts;
			self.render();
		});

		fetchText(fontfaceUrl()).then(function (content) {
			self.currentBox.replaceWith(buildCurrentFontsSummary(parseFontFace(content)));
		});
	}

	FontPicker.prototype.parseExisting = function (raw) {
		var self = this;

		(raw || '').split(/\r\n|\r|\n/).forEach(function (line) {
			line = line.trim();

			if (!line) {
				return;
			}

			var parts = line.split('|').map(function (p) { return p.trim(); });

			if (parts.length < 3 || !parts[0]) {
				return;
			}

			self.blocks.push({
				family: parts[0],
				subsets: parts[1].split(',').map(function (s) { return s.trim(); }).filter(Boolean),
				variants: parts[2].split(',').map(function (s) { return s.trim(); }).filter(Boolean),
			});
		});
	};

	FontPicker.prototype.buildDom = function () {
		this.textarea.style.display = 'none';

		this.currentBox = el('div', 'bs-fontpicker__current');
		this.currentBox.appendChild(el('p', 'bs-fontpicker__hint', 'Lade …'));

		this.root = el('div', 'bs-fontpicker');

		var search = el('div', 'bs-fontpicker__search');
		this.searchInput = document.createElement('input');
		this.searchInput.type = 'text';
		this.searchInput.className = 'bs-fontpicker__search-input tl_text';
		this.searchInput.placeholder = 'Schriftart suchen, z.B. Open Sans …';
		this.searchInput.autocomplete = 'off';
		search.appendChild(this.searchInput);

		this.results = el('div', 'bs-fontpicker__results');
		this.results.hidden = true;
		search.appendChild(this.results);

		this.blocksWrap = el('div', 'bs-fontpicker__blocks');

		this.root.appendChild(search);
		this.root.appendChild(this.blocksWrap);

		this.textarea.parentNode.insertBefore(this.currentBox, this.textarea);
		this.textarea.parentNode.insertBefore(this.root, this.textarea);

		var self = this;

		this.searchInput.addEventListener('input', function () {
			self.onSearch();
		});

		document.addEventListener('click', function (e) {
			if (!self.root.contains(e.target)) {
				self.results.hidden = true;
			}
		});
	};

	FontPicker.prototype.onSearch = function () {
		var self = this;
		var term = this.searchInput.value.trim().toLowerCase();

		this.results.innerHTML = '';

		if (!term) {
			this.results.hidden = true;

			return;
		}

		var matches = this.fonts.filter(function (f) {
			return f.family.toLowerCase().indexOf(term) !== -1;
		}).slice(0, 15);

		if (0 === matches.length) {
			this.results.hidden = true;

			return;
		}

		matches.forEach(function (font) {
			var already = self.blocks.some(function (b) { return b.family === font.family; });
			var item = el('div', 'bs-fontpicker__result' + (already ? ' bs-fontpicker__result--added' : ''));
			item.appendChild(document.createTextNode(font.family));

			if (already) {
				item.appendChild(el('span', null, ' (schon hinzugefügt)'));
			}

			item.addEventListener('click', function () {
				if (!already) {
					self.addFont(font);
				}

				self.searchInput.value = '';
				self.results.innerHTML = '';
				self.results.hidden = true;
			});

			self.results.appendChild(item);
		});

		this.results.hidden = false;
	};

	FontPicker.prototype.addFont = function (font) {
		var defaultSubset = font.subsets.indexOf('latin') !== -1 ? 'latin' : font.subsets[0];
		var defaultVariant = font.variants.indexOf('regular') !== -1 ? 'regular' : font.variants[0];

		this.blocks.push({
			family: font.family,
			subsets: defaultSubset ? [defaultSubset] : [],
			variants: defaultVariant ? [defaultVariant] : [],
			meta: font,
		});

		this.render();
		this.serialize();
	};

	FontPicker.prototype.removeFont = function (family) {
		this.blocks = this.blocks.filter(function (b) { return b.family !== family; });

		this.render();
		this.serialize();
	};

	FontPicker.prototype.render = function () {
		var self = this;

		this.blocksWrap.innerHTML = '';

		this.blocks.forEach(function (block) {
			var meta = block.meta || self.fonts.filter(function (f) { return f.family === block.family; })[0];
			var box = el('div', 'bs-fontpicker__block');

			var head = el('div', 'bs-fontpicker__block-head');
			head.appendChild(el('strong', null, block.family));

			var remove = el('button', 'bs-fontpicker__remove', '×');
			remove.type = 'button';
			remove.addEventListener('click', function () {
				self.removeFont(block.family);
			});
			head.appendChild(remove);
			box.appendChild(head);

			if (!meta) {
				box.appendChild(el('p', 'bs-fontpicker__hint', 'Details werden geladen …'));
				self.blocksWrap.appendChild(box);

				return;
			}

			box.appendChild(self.buildCheckboxGroup('Subsets', meta.subsets, block.subsets, function (value, checked) {
				self.toggle(block.subsets, value, checked);
				self.serialize();
			}));

			var variants = meta.variants.slice().sort(variantSort);

			box.appendChild(self.buildCheckboxGroup('Schriftschnitte', variants, block.variants, function (value, checked) {
				self.toggle(block.variants, value, checked);
				self.serialize();
			}, variantLabel));

			self.blocksWrap.appendChild(box);
		});
	};

	FontPicker.prototype.buildCheckboxGroup = function (title, options, selected, onChange, labelFn) {
		var wrap = el('div', 'bs-fontpicker__group');
		wrap.appendChild(el('span', 'bs-fontpicker__group-title', title));

		var list = el('div', 'bs-fontpicker__checkboxes');

		options.forEach(function (option) {
			var label = el('label', 'bs-fontpicker__checkbox');
			var input = document.createElement('input');
			input.type = 'checkbox';
			input.value = option;
			input.checked = selected.indexOf(option) !== -1;

			input.addEventListener('change', function () {
				onChange(option, input.checked);
			});

			label.appendChild(input);
			label.appendChild(document.createTextNode(' ' + (labelFn ? labelFn(option) : option)));
			list.appendChild(label);
		});

		wrap.appendChild(list);

		return wrap;
	};

	FontPicker.prototype.toggle = function (arr, value, checked) {
		var idx = arr.indexOf(value);

		if (checked && -1 === idx) {
			arr.push(value);
		} else if (!checked && idx !== -1) {
			arr.splice(idx, 1);
		}
	};

	FontPicker.prototype.serialize = function () {
		this.textarea.value = this.blocks
			.filter(function (b) { return b.subsets.length && b.variants.length; })
			.map(function (b) {
				return b.family + ' | ' + b.subsets.join(',') + ' | ' + b.variants.join(',');
			})
			.join('\n');
	};

	/* ----------------------------------------------------------- Colors */

	function parseColorsScss(content) {
		var re = /^(\$[a-zA-Z_][a-zA-Z0-9_-]*):\s*([^;]+);\s*(?:\/\/\s*(.*))?$/gm;
		var colors = [];
		var m;

		while (null !== (m = re.exec(content))) {
			var label = (m[3] || '').trim();

			if (!label) {
				continue;
			}

			colors.push({ name: m[1], value: m[2].trim(), label: label });
		}

		return colors;
	}

	function buildCurrentColorsSummary(colors) {
		var box = el('div', 'bs-colors-current');
		box.appendChild(el('strong', null, 'Aktuell eingebunden'));

		if (0 === colors.length) {
			box.appendChild(el('p', 'bs-fontpicker__hint', 'Noch keine Farbe mit Farbton hinterlegt.'));

			return box;
		}

		var list = el('ul');

		colors.forEach(function (c) {
			var li = el('li');
			var isColor = /^#[0-9a-fA-F]{3,8}$/.test(c.value);

			var swatch = el('span', 'bs-color-swatch');
			swatch.style.backgroundColor = isColor ? c.value : 'transparent';
			li.appendChild(swatch);

			li.appendChild(document.createTextNode(' ' + c.name + ' '));
			li.appendChild(el('span', 'bs-fontpicker__hint', '(' + c.label + ', ' + c.value + ')'));

			list.appendChild(li);
		});

		box.appendChild(list);

		return box;
	}

	function initColorsSummary(textarea) {
		var placeholder = el('div', 'bs-colors-current');
		placeholder.appendChild(el('p', 'bs-fontpicker__hint', 'Lade …'));
		textarea.parentNode.insertBefore(placeholder, textarea);

		fetchText(varsColorsUrl()).then(function (content) {
			placeholder.replaceWith(buildCurrentColorsSummary(parseColorsScss(content)));
		});
	}

	/* -------------------------------------------------------------- Init */

	/*
	 * Contao's "Speichern" button submits and redisplays the form without a
	 * real page navigation (no "beforeunload"), so a one-time
	 * "DOMContentLoaded" listener only ever catches the very first load.
	 * A MutationObserver re-runs init whenever a fresh, not-yet-enhanced
	 * textarea shows up after such an in-place update.
	 */
	function initAll() {
		var fontsField = document.getElementById('ctrl_backendSuiteFonts');

		if (fontsField && 'TEXTAREA' === fontsField.tagName && !fontsField.dataset.bsInit) {
			fontsField.dataset.bsInit = '1';
			new FontPicker(fontsField);
		}

		var colorsField = document.getElementById('ctrl_backendSuiteColors');

		if (colorsField && 'TEXTAREA' === colorsField.tagName && !colorsField.dataset.bsInit) {
			colorsField.dataset.bsInit = '1';
			initColorsSummary(colorsField);
		}
	}

	if ('loading' === document.readyState) {
		document.addEventListener('DOMContentLoaded', initAll);
	} else {
		initAll();
	}

	new MutationObserver(initAll).observe(document.body, { childList: true, subtree: true });
})();
