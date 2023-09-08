<?php
/**
 * Bimeson (Instance)
 *
 * @package Wplug Bimeson List
 * @author Takuto Yanagida
 * @version 2023-09-08
 */

namespace wplug\bimeson_list;

/**
 * Gets instance.
 *
 * @access private
 *
 * @return object Instance.
 */
function _get_instance(): object {
	static $values = null;
	if ( $values ) {
		return $values;
	}
	$values = new class() {

		// Template Admin.

		const KEY_VISIBLE = '_visible';

		/**
		 * The meta key of list config.
		 *
		 * @var string
		 */
		public $fld_list_cfg = '_bimeson';

		// Post Type.

		const PT = 'bimeson_list';  // Each post means publication list (bimeson list).

		const FLD_MEDIA    = '_bimeson_media';
		const FLD_ADD_TAX  = '_bimeson_add_tax';
		const FLD_ADD_TERM = '_bimeson_add_term';
		const FLD_ITEMS    = '_bimeson_items';

		/**
		 * Media picker instance.
		 *
		 * @var MediaPicker
		 */
		public $media_picker;

		// Item.

		const NOT_MODIFIED = '<NOT MODIFIED>';

		const IT_BODY       = '_body';
		const IT_DATE       = '_date';
		const IT_DOI        = '_doi';
		const IT_LINK_URL   = '_link_url';
		const IT_LINK_TITLE = '_link_title';

		const IT_DATE_NUM = '_date_num';
		const IT_CAT_KEY  = '_cat_key';
		const IT_INDEX    = '_index';

		// Common.

		/**
		 * First heading level of publication lists.
		 *
		 * @var int
		 */
		public $head_level = 3;

		/**
		 * Year heading format.
		 *
		 * @var string|null
		 */
		public $year_format = null;

		/**
		 * Callable for getting term names.
		 *
		 * @var callable
		 */
		public $term_name_getter = null;

		/**
		 * Data cache.
		 *
		 * @var array<string, mixed>[]
		 */
		public $cache = array();

		/**
		 * The array of root slug to sub slug indices.
		 *
		 * @var array<string, int[]>|null
		 */
		public $rs_idx = null;

		/**
		 * The array of root slug to options.
		 *
		 * @var array<string, array<string, mixed>>|null
		 */
		public $rs_opts = null;

		// Taxonomy.

		const KEY_IS_HIDDEN           = '_bimeson_is_hidden';
		const KEY_SORT_UNCAT_LAST     = '_bimeson_sort_uncat_last';
		const KEY_OMIT_LAST_CAT_GROUP = '_bimeson_omit_last_cat_group';

		const DEFAULT_TAXONOMY          = 'bm_cat';
		const DEFAULT_SUB_TAX_BASE      = 'bm_cat_';
		const DEFAULT_SUB_TAX_CLS_BASE  = 'bm-cat-';
		const DEFAULT_SUB_TAX_QVAR_BASE = 'bm_';

		/**
		 * Root taxonomy slug.
		 *
		 * @var string
		 */
		public $root_tax;

		/**
		 * Slug base of sub taxonomies.
		 *
		 * @var string
		 */
		public $sub_tax_base;

		/**
		 * Class base of sub taxonomies.
		 *
		 * @var string
		 */
		public $sub_tax_cls_base;

		/**
		 * Query variable name base of sub taxonomies.
		 *
		 * @var string
		 */
		public $sub_tax_qvar_base;

		const DEFAULT_YEAR_CLS_BASE = 'bm-year-';
		const DEFAULT_YEAR_QVAR     = 'bm_year';

		/**
		 * Class base of year.
		 *
		 * @var string
		 */
		public $year_cls_base;

		/**
		 * Query variable name of year.
		 *
		 * @var string
		 */
		public $year_qvar;

		/**
		 * The sub taxonomy slugs.
		 *
		 * @var array<string, string>
		 */
		public $sub_taxes = array();

		/**
		 * Previously edited taxonomy.
		 *
		 * @var string[]|null
		 */
		public $old_tax = null;

		/**
		 * Previously edited terms.
		 *
		 * @var string[]
		 */
		public $old_terms = array();

		/**
		 * The root terms.
		 *
		 * @var string[]|null
		 */
		public $root_terms = null;

		/**
		 * The sub terms.
		 *
		 * @var array<string, \WP_Term[]|null>
		 */
		public $sub_tax_to_terms = array();

		// Filter.

		const KEY_YEAR     = '_year';
		const VAL_YEAR_ALL = 'all';

		/**
		 * Label of year select markup.
		 *
		 * @var string
		 */
		public $year_select_label;

		/**
		 * Label of heading meaning 'uncategorized'.
		 *
		 * @var string
		 */
		public $uncat_label;

	};
	return $values;
}

/**
 * Sets the meta key of list config.
 *
 * @access private
 *
 * @param string $key Key.
 */
function _set_key( string $key ): void {
	$inst = _get_instance();

	$inst->fld_list_cfg = $key;
}
