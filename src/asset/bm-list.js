/**
 *
 * Bimeson List Post Type Admin
 *
 * @author Takuto Yanagida
 * @version 2020-06-09
 *
 */


document.addEventListener('DOMContentLoaded', function () {

	var ID_FILE_PICKER    = '_bimeson_media';

	var SEL_FILTER_BUTTON = '.bimeson_list_filter_button';
	var SEL_LOADING_SPIN  = '.bimeson_list_loading_spin';

	var NAME_ITEMS        = '_bimeson_items';
	var NAME_ADD_TAX      = '_bimeson_add_tax';
	var NAME_ADD_TERM     = '_bimeson_add_term';

	var KEY_BODY = '_body';

	var addTaxCb = document.getElementsByName(NAME_ADD_TAX)[0];
	var addTermCb = document.getElementsByName(NAME_ADD_TERM)[0];
	addTaxCb.disabled = true;
	addTermCb.disabled = true;

	var btn = document.querySelector(SEL_FILTER_BUTTON);
	if (!btn) return;
	btn.addEventListener('click', function () {
		var count = document.getElementById(ID_FILE_PICKER).value;
		var urls = [];

		for (var i = 0; i < count; i += 1) {
			var id = ID_FILE_PICKER + '_' + i;
			var delElm = document.getElementById(id + '_delete');
			if (delElm.checked) continue;
			var url = document.getElementById(id + '_url').value;
			if (url.length !== 0) urls.push(url);
		}
		disableFilterButton();
		loadFiles(urls, '', NAME_ITEMS, enableFilterButton);
	});


	// -------------------------------------------------------------------------

	function disableFilterButton() {
		var spin = document.querySelector(SEL_LOADING_SPIN);
		spin.style.visibility = 'visible';

		var btn = document.querySelector(SEL_FILTER_BUTTON);
		btn.style.pointerEvents = 'none';
		btn.style.opacity = '0.5';
		btn.blur();
	}

	function enableFilterButton() {
		setTimeout(function () {
			var spin = document.querySelector(SEL_LOADING_SPIN);
			spin.style.visibility = '';

			var btn = document.querySelector(SEL_FILTER_BUTTON);
			btn.style.pointerEvents = '';
			btn.style.opacity = '';

			var res = document.getElementsByName(NAME_ITEMS)[0];
			if (res && res.value !== '') {
				addTaxCb.disabled = false;
				addTermCb.disabled = false;
			}
		}, 400);
	}


	// -------------------------------------------------------------------------

	function loadFiles(urls, lang, outName, onFinished) {
		var recCount = 0;
		var items = [];

		if (urls.length === 0) {
			var res = document.getElementsByName(outName)[0];
			if (res) res.value = '';
			console.log('Complete filtering (No data)');
			onFinished();
			return;
		}

		urls.forEach(function (url) {
			console.log('Requesting file...');
			var req = new XMLHttpRequest();
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
			var data = new Uint8Array(response);
			var arr = new Array();
			for (var i = 0, I = data.length; i < I; i += 1) arr[i] = String.fromCharCode(data[i]);
			var bstr = arr.join('');

			try {
				var book = XLSX.read(bstr, {type:'binary'});
				var sheetName = book.SheetNames[0];
				var sheet = book.Sheets[sheetName];
				if (sheet) processSheet(sheet, lang, items);
				console.log('Finish filtering file');
			} catch (e) {
				console.log('Error on filtering file');
				console.log(e);
			}
		}

		function finished() {
			var res = document.getElementsByName(outName)[0];
			if (res) res.value = JSON.stringify(items);
			console.log('Complete filtering (' + items.length + ' items)');
			onFinished();
		}
	}


	// -------------------------------------------------------------------------

	function processSheet(sheet, lang, retItems) {
		var range = XLSX.utils.decode_range(sheet['!ref']);
		var x0 = range.s.c, x1 = Math.min(range.e.c, 40) + 1;
		var y0 = range.s.r, y1 = range.e.r + 1;

		var colCount = 0, colToKey = {};
		for (var x = x0; x < x1; x += 1) {
			var cell = sheet[XLSX.utils.encode_cell({c: x, r: y0})];
			if (!cell || cell.w === '') {
				colToKey[x] = false;
			} else {
				colToKey[x] = normalizeKey(cell.w + '', true);
			}
			colCount += 1;
		}
		x1 = x0 + colCount;

		for (var y = y0 + 1; y < y1; y += 1) {  // skip header
			var item = {};
			var count = 0;
			for (var x = x0; x < x1; x += 1) {
				var cell = sheet[XLSX.utils.encode_cell({c: x, r: y})];
				var key = colToKey[x];
				if (key === false) continue;
				if (key === KEY_BODY || key.indexOf(KEY_BODY + '_') === 0) {
					if (cell && cell.h && cell.h.length > 0) {
						var text = stripUnnecessarySpan(cell.h);  // remove automatically inserted 'span' tag.
						text = text.replace(/<br\/>/g, '<br />');
						text = text.replace(/&#x000d;&#x000a;/g, '<br />');
						item[key] = text;
						count += 1;
					}
				} else if (key[0] === '_') {
					if (cell && cell.w && cell.w.length > 0) {
						item[key] = cell.w;
						count += 1;
					}
				} else {
					if (cell && cell.w && cell.w.length > 0) {
						var vals = cell.w.split(/\s*,\s*/);
						item[key] = vals.map(function (x) {return normalizeKey(x, false);});
						count += 1;
					}
				}
			}
			if (0 < count) retItems.push(item);
		}
	}

	function normalizeKey(str, isKey) {
		str = str.replace(/[Ａ-Ｚａ-ｚ０-９]/g, function (s) {return String.fromCharCode(s.charCodeAt(0) - 0xFEE0);});
		str = str.replace(/[_＿]/g, '_');
		str = str.replace(/[\-‐―ー]/g, '-');
		str = str.replace(/[^A-Za-z0-9_\-]/g, '');
		str = str.toLowerCase();
		str = str.trim();
		if (0 < str.length) {
			if (!isKey && (str[0] === '_' || str[0] === '-')) str = str.replace(/^[_\-]+/, '');
			if (str[0] !== '_') str = str.replace('_', '-');
			if (str[0] === '_') str = str.replace('-', '_');
		}
		return str;
	}

	function stripUnnecessarySpan(str) {
		str = str.replace(/<span +([^>]*)>/gi, (m, p1) => {
			const as = p1.trim().toLowerCase().match(/style *= *"([^"]*)"/);
			if (as && as.length === 2) {
				const style = as[1].trim();
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

});
