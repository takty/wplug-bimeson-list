/**
 * Media Picker
 *
 * @author Takuto Yanagida
 * @version 2023-11-10
 */

window.wplug = window.wpinc ?? {}

const NS = window.wplug;

document.addEventListener('DOMContentLoaded', () => {
	for (const e of [...document.querySelectorAll('*[data-picker="media"]')]) {
		NS.setMediaPicker(e);
	}
});

(NS => {
	NS.setMediaPicker = (elm, cls = null, fn = null, opts = {}) => {
		if (!cls) cls = 'media';
		opts = Object.assign({
			multiple      : false,
			type          : '',
			parentGen     : -1,
			title         : null,
			media_id_input: null
		}, opts);

		const postId = document.getElementById('post_ID')?.value ?? null;
		let cm = null;
		elm.addEventListener('click', e => {
			e.preventDefault();
			if (!cm) {
				wp.media.view.AttachmentsBrowser = AttachmentsBrowserCustom;
				cm = createMedia(postId, opts.title ? opts.title : e.target.innerText, opts.multiple, opts.type);
				cm.on('select', () => {
					const p = (opts.parentGen !== -1) ? getParent(e.target, opts.parentGen) : null;

					if (opts.multiple) {
						const fs        = cm.state().get('selection');
						const fileJsons = fs.map((f) => f.toJSON());
						if (p) setItem(p, cls, fileJsons[0]);
						if (fn) fn(e.target, fileJsons);
					} else {
						const f        = cm.state().get('selection').first();
						const fileJson = f.toJSON();
						if (p) setItem(p, cls, fileJson);
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
				cm.on('close', () => {
					wp.media.view.AttachmentsBrowser = AttachmentsBrowserOrig;
				});
			}
			cm.open();
		});

		function getParent(elm, gen) {
			while (0 < gen-- && elm.parentNode) {
				elm = elm.parentNode;
			}
			return elm;
		}

		function setItem(p, cls, f) {
			setValueToCls(p, cls + '-id',       f.id);
			setValueToCls(p, cls + '-url',      f.url);
			setValueToCls(p, cls + '-title',    f.title);
			setValueToCls(p, cls + '-filename', f.filename);
		}

		function setValueToCls(p, cls, val) {
			for (const e of [...p.getElementsByClassName(cls)]) {
				if (e instanceof HTMLInputElement) {
					e.value = val;
				} else {
					e.innerText = val;
				}
		}
		}

		function createMedia(postId, title, multiple, type) {
			wp.media.model.settings.post.id = postId;
			wp.media.view.settings.post.id  = postId;

			const media = wp.media({
				title   : title,
				library : { type },
				frame   : 'select',
				multiple: multiple,
			});
			// For attaching uploaded file to post
			media.uploader.options.uploader.params.post_id = postId;
			return media;
		}

		/*
		* Tha following enables our media picker selectable 'Uploaded to this post'.
		* https://cobbledco.de/adding-your-own-filter-to-the-media-uploader/
		*/
		const MediaLibraryUploadedFilter = wp.media.view.AttachmentFilters.extend({
			createFilters: function () {
				const filters = {};
				filters.all = {
					text : wp.media.view.l10n.allMediaItems,
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
					text : wp.media.view.l10n.uploadedToThisPost,
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
					text : wp.media.view.l10n.unattached,
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
			createToolbar: function () {
				AttachmentsBrowserOrig.prototype.createToolbar.call( this );
				this.toolbar.set(
					'mediaLibraryUploadedFilter',
					new MediaLibraryUploadedFilter({
						controller: this.controller,
						model     : this.collection.props,
						priority  : -100
					}).render()
				);
			}
		});
	}
})(NS);
