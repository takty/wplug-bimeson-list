/**
 * Post Type
 *
 * @author Takuto Yanagida
 * @version 2024-01-25
 */

document.addEventListener('DOMContentLoaded', function () {

	// Same as post meta keys
	const FLD_MEDIA    = '_bimeson_media';
	const FLD_ADD_TAX  = '_bimeson_add_tax';
	const FLD_ADD_TERM = '_bimeson_add_term';
	const FLD_ITEMS    = '_bimeson_items';

	const SEL_UPDATE_BUTTON = '.wplug-bimeson-list-update-button';
	const SEL_LOADING_SPIN  = '.wplug-bimeson-list-loading-spin';

	const KEY_BODY = '_body';

	const addTaxCb = document.getElementsByName(FLD_ADD_TAX)[0];
	const addTermCb = document.getElementsByName(FLD_ADD_TERM)[0];
	addTaxCb.disabled = true;
	addTermCb.disabled = true;

	const btn = document.querySelector(SEL_UPDATE_BUTTON);
	if (!btn) return;
	btn.addEventListener('click', function () {
		const count = document.getElementById(FLD_MEDIA).value;
		const urls = [];

		for (var i = 0; i < count; i += 1) {
			const id = FLD_MEDIA + '_' + i;
			const delElm = document.getElementById(id + '_delete');
			if (delElm.checked) continue;
			const url = document.getElementById(id + '_url').value;
			if (url.length !== 0) urls.push(url);
		}
		disableFilterButton();
		loadFiles(urls, FLD_ITEMS, () => {
			enableFilterButton();
			btn.classList.add('updated');
		});
	});


	// -------------------------------------------------------------------------


	const form = document.getElementById('post');
	if (form) {
		form.addEventListener('submit', (e) => {
			if (!btn.classList.contains('updated')) {
				alert(translation.alert_msg);
				e.preventDefault();
			}
		});
	}


	// -------------------------------------------------------------------------


	function disableFilterButton() {
		const spin = document.querySelector(SEL_LOADING_SPIN);
		spin.style.visibility = 'visible';

		const btn = document.querySelector(SEL_UPDATE_BUTTON);
		btn.setAttribute('disabled', true);
		btn.blur();
	}

	function enableFilterButton() {
		setTimeout(function () {
			const spin = document.querySelector(SEL_LOADING_SPIN);
			spin.style.visibility = '';

			const btn = document.querySelector(SEL_UPDATE_BUTTON);
			btn.removeAttribute('disabled');

			const res = document.getElementsByName(FLD_ITEMS)[0];
			if (res && res.value !== '') {
				addTaxCb.disabled = false;
				addTermCb.disabled = false;
			}
		}, 400);
	}


	// -------------------------------------------------------------------------


	function loadFiles(urls, outName, onFinished) {
		let recCount = 0;
		const items = [];

		if (urls.length === 0) {
			const res = document.getElementsByName(outName)[0];
			if (res) res.value = '';
			console.log('Complete filtering (No data)');
			onFinished();
			return;
		}

		urls.forEach(function (url) {
			console.log('Requesting file...');
			const req = new XMLHttpRequest();
			req.open('GET', url, true);
			req.responseType = 'arraybuffer';
			req.onload = makeListener(url, req);
			req.send();
		});

		function makeListener(url, req) {
			return function (e) {
				if (!req.response) {
					console.log('Did not receive file (' + url + ')');
					return;
				}
				console.log('Received file: ' + req.response.byteLength + ' bytes (' + url + ')');
				process(req.response);
				if (++recCount === urls.length) finished();
			};
		}

		function process(response) {
			const data = new Uint8Array(response);
			const arr  = new Array();

			for (let i = 0, I = data.length; i < I; i += 1) arr[i] = String.fromCharCode(data[i]);
			const bstr = arr.join('');

			try {
				const book      = XLSX.read(bstr, { type: 'binary', cellNF: false });
				const sheetName = retrieveSheetName(book);
				const sheet     = book.Sheets[sheetName];
				if (sheet) processSheet(sheet, items);
				console.log('Finish filtering file');
			} catch (e) {
				console.log('Error on filtering file');
				console.log(e);
				onFinished();
			}
		}

		function finished() {
			const res = document.getElementsByName(outName)[0];
			if (res) res.value = JSON.stringify(items);
			console.log('Complete filtering (' + items.length + ' items)');
			onFinished();
		}

		function retrieveSheetName(book) {
			for (const name of book.SheetNames) {
				const nn = name.trim().toLowerCase();
				if ('_list' === nn) {
					return name;
				}
			}
			return book.SheetNames[0];
		}
	}


	// -------------------------------------------------------------------------


	function processSheet(sheet, retItems) {
		const range = XLSX.utils.decode_range(sheet['!ref']);
		const x0 = range.s.c;
		const y0 = range.s.r;
		let x1 = Math.min(range.e.c, 40) + 1;
		let y1 = range.e.r + 1;

		let colCount = 0;
		const colToKey = {};

		for (let x = x0; x < x1; x += 1) {
			const cell = sheet[XLSX.utils.encode_cell({ c: x, r: y0 })];
			if (!cell || cell.w === '') {
				colToKey[x] = null;
			} else {
				colToKey[x] = normalizeKey(cell.w + '', true);
			}
			colCount += 1;
		}
		x1 = x0 + colCount;

		for (let y = y0 + 1; y < y1; y += 1) {  // skip header
			const item = {};
			const bs   = new Map();
			let count = 0;

			for (let x = x0; x < x1; x += 1) {
				const cell = sheet[XLSX.utils.encode_cell({ c: x, r: y })];
				const key  = colToKey[x];

				if (key === null) continue;
				if (key.startsWith(KEY_BODY)) {
					if (cell && cell.h && cell.h.length > 0) {
						count += 1;
						const text = prepareBodyText(cell.h);
						extractBodyText(bs, key, text);
					}
				} else if (key.startsWith('_')) {
					if (cell && cell.w && cell.w.length > 0) {
						count += 1;
						item[key] = cell.w;
					}
				} else {
					if (cell && cell.w && cell.w.length > 0) {
						count += 1;
						const vals = cell.w.split(/\s*,\s*/);
						item[key] = vals.map( x => normalizeKey(x, false) );
					}
				}
			}
			if (0 < count) {
				storeBodyText(bs, item);
				retItems.push(item);
			}
		}
	}

	function extractBodyText(bs, key, text) {
		const k = key.replace(/\[[0-9]\]$/, '');
		if (!bs.has(k)) {
			bs.set(k, { s: '', a: {} });
		}
		const b = bs.get(k);
		const m = key.match(/\[([0-9])\]$/);
		if (m) {
			const i = parseInt(m[1], 10);
			if (!isNaN(i)) b.a[i] = text;
		} else {
			b.s = text;
		}
	}

	function storeBodyText(bs, item) {
		for (const k of bs.keys()) {
			const b = bs.get(k);
			if (b.s) {
				item[k] = b.s;
			}
			if (b.a) {
				let text = '';
				for (let i = 0; i < 10; ++i) {
					text += b.a?.[i] ?? '';
				}
				item[k] = text;
			}
		}
	}

	function normalizeKey(str, isKey) {
		str = str.replace(/[Ａ-Ｚａ-ｚ０-９]/g, s => String.fromCharCode(s.charCodeAt(0) - 0xFEE0));
		str = str.replace(/[_＿]/g, '_');
		str = str.replace(/[\-‐―ー]/g, '-');
		if (isKey) {
			str = str.replace(/[^A-Za-z0-9_\- \[\]]/g, '');
		} else {
			str = str.replace(/[^A-Za-z0-9_\- ]/g, '');
		}
		str = str.toLowerCase().trim();
		if (0 < str.length) {
			if (isKey && str[0] === '_') {  // Underscore separation
				str = str.replace(/[_\- ]+/g, '_');
			} else {  // Hyphen separation
				str = str.replace(/[_\- ]+/g, '-');
				str = str.replace(/^[_\-]+/, '');
			}
		}
		return str;
	}


	// -------------------------------------------------------------------------


	function prepareBodyText(str) {
		str = stripUnnecessarySpan(str);  // remove automatically inserted 'span' tag.
		str = str.replace(/<br\/>/g, '<br>');
		str = str.replace(/&#x000d;&#x000a;/g, '<br>');
		str = restoreEscapedTag(str);
		str = stripEmptyTag(str);
		return str;
	}

	function stripUnnecessarySpan(str) {
		str = str.replace(/<span +([^>]*)>/gi, (m, p1) => {
			const ms = p1.trim().toLowerCase().match(/style *= *"([^"]*)"/);
			if (ms && ms.length === 2) {
				const style = ms[1].trim();
				if (style.search(/text-decoration *: *underline/gi) !== -1) {
					return '<span style="text-decoration:underline;">';
				}
			}
			return '<span>';
		});
		str = str.replace(/< +\/ +span +>/gi, '</span>');
		str = str.replace(/<span>(.+?)<\/span>/gi, (m, p1) => { return p1; });
		return str;
	}

	function restoreEscapedTag(str) {
		for (const t of ['b', 'i', 'sub', 'sup']) {
			str = str.replace(new RegExp(`&lt;${t}&gt;`, 'g'), `<${t}>`);
			str = str.replace(new RegExp(`&lt;\/${t}&gt;`, 'g'), `</${t}>`);
		}
		str = str.replace(/&lt;u&gt;/g, '<span style="text-decoration:underline;">');
		str = str.replace(/&lt;\/u&gt;/g, '</span>');
		str = str.replace(/&lt;br&gt;/g, '<br>');

		str = str.replace(/&lt;span +((?!&gt;).*?)&gt;/gi, (m, p1) => {
			const mc = p1.trim().toLowerCase().replaceAll('&quot;', '"').match(/class *= *"([^"]*)"/);
			if (mc && mc.length === 2) {
				const cls = mc[1].trim();
				return `<span class="${cls}">`;
			}
			return '<span>';
		});
		str = str.replace(/&lt;\/span&gt;/g, '</span>');
		return str;
	}

	function stripEmptyTag(str) {
		for (const t of ['b', 'i', 'sub', 'sup', 'span']) {
			str = str.replace(new RegExp(`<${t}><\/${t}>`, 'g'), '');  // Remove empty tag
		}
		str = str.replace(/<span +[^>]*><\/span>/gi, '');
		str = str.replace(/, , /g, ', ');
		return str.trim();
	}

});
