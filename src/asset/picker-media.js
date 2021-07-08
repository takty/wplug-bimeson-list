/**
 *
 * Media Picker (JS)
 *
 * @author Takuto Yanagida
 * @version 2019-02-25
 *
 */


document.addEventListener('DOMContentLoaded', function () {
	const elms = document.querySelectorAll('*[data-picker="media"]');
	for (let i = 0; i < elms.length; i += 1) {
		setMediaPicker(elms[i]);
	}
});

function setMediaPicker(elm, cls = false, fn = null, opts = {}) {
	if (cls === false) cls = 'media';
	opts = Object.assign({ multiple: false, type: '', parentGen: 1, title: false }, opts);

	const postId = document.getElementById('post_ID').value;
	let cm = null;
	elm.addEventListener('click', function (e) {
		e.preventDefault();
		if (!cm) {
			wp.media.view.AttachmentsBrowser = AttachmentsBrowserCustom;
			cm = createMedia(postId, opts.title === false ? e.target.innerText : opts.title, opts.multiple, opts.type);
			cm.on('select', function () {
				const parent = getParent(e.target, opts.parentGen);
				if (opts.multiple) {
					const fs = cm.state().get('selection');
					const fileJsons = fs.map((f) => f.toJSON());
					setItem(parent, cls, fileJsons[0]);
					if (fn) fn(e.target, fileJsons);
				} else {
					const f = cm.state().get('selection').first();
					const fileJson = f.toJSON();
					setItem(parent, cls, fileJson);
					if (fn) fn(e.target, fileJson);
				}
			});
			if (opts['media_id_input']) {
				cm.on('open', () => {
					const sel = cm.state().get('selection');
					const mid = document.getElementById(opts['media_id_input']).value;
					const at = wp.media.attachment(mid);
					at.fetch();
					sel.add(at ? [at] : []);
				});
			}
			cm.on('close', function () {
				wp.media.view.AttachmentsBrowser = AttachmentsBrowserOrig;
			});
		}
		cm.open();
	});

	function getParent(elm, gen) {
		while (0 < gen-- && elm.parentNode) elm = elm.parentNode;
		return elm;
	}

	function setItem(parent, cls, f) {
		setValueToCls(parent, cls + '-id',       f.id);
		setValueToCls(parent, cls + '-url',      f.url);
		setValueToCls(parent, cls + '-title',    f.title);
		setValueToCls(parent, cls + '-filename', f.filename);
	}

	function setValueToCls(parent, cls, value) {
		const elms = parent.getElementsByClassName(cls);
		for (let i = 0; i < elms.length; i += 1) {
			if (elms[i] instanceof HTMLInputElement) {
				elms[i].value = value;
			} else {
				elms[i].innerText = value;
			}
		}
	}

	function createMedia(postId, title, multiple, type) {
		wp.media.model.settings.post.id = postId;
		wp.media.view.settings.post.id  = postId;

 		const media = wp.media({
			title   : title,
			library : {type: type},
			frame   : 'select',
			multiple: multiple,
		});
		// For attatching uploaded file to post
		media.uploader.options.uploader.params.post_id = postId;
		return media;
	}

	/*
	 * Tha following enables our media picker selectable 'Uploaded to this post'.
	 * https://cobbledco.de/adding-your-own-filter-to-the-media-uploader/
	 */
	const MediaLibraryUploadedFilter = wp.media.view.AttachmentFilters.extend({
		createFilters: function() {
			const filters = {};
			filters.all = {
				text:  wp.media.view.l10n.allMediaItems,
				props: {
					status    : null,
					type      : null,
					uploadedTo: null,
					orderby   : 'date',
					order     : 'DESC'
				},
				priority: 10
			};
			filters.uploaded = {
				text:  wp.media.view.l10n.uploadedToThisPost,
				props: {
					status    : null,
					type      : null,
					uploadedTo: wp.media.view.settings.post.id,
					orderby   : 'menuOrder',
					order     : 'ASC'
				},
				priority: 20
			};
			filters.unattached = {
				text:  wp.media.view.l10n.unattached,
				props: {
					status    : null,
					type      : null,
					uploadedTo: 0,
					orderby   : 'menuOrder',
					order     : 'ASC'
				},
				priority: 50
			};
			this.filters = filters;
		}
	});

	const AttachmentsBrowserOrig = wp.media.view.AttachmentsBrowser;
	const AttachmentsBrowserCustom = AttachmentsBrowserOrig.extend({
		createToolbar: function() {
			AttachmentsBrowserOrig.prototype.createToolbar.call( this );
			this.toolbar.set(
				'mediaLibraryUploadedFilter',
				new MediaLibraryUploadedFilter({
					controller: this.controller,
					model:      this.collection.props,
					priority:   -100
				}).render()
			);
		}
	});
}
