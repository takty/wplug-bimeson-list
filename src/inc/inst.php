<?php
/**
 * Bimeson (Instance)
 *
 * @package Wplug Bimeson List
 * @author Takuto Yanagida
 * @version 2021-08-31
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
		 * @var 1.0
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
		 * @var 1.0
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
		 * @var 1.0
		 */
		public $head_level = 3;

		/**
		 * Year heading format.
		 *
		 * @var 1.0
		 */
		public $year_format = null;

		/**
		 * Callable for getting term names.
		 *
		 * @var 1.0
		 */
		public $term_name_getter = null;

		/**
		 * Data cache.
		 *
		 * @var 1.0
		 */
		public $cache = array();

		/**
		 * The array of root slug to sub slug indices.
		 *
		 * @var 1.0
		 */
		public $rs_idx = null;

		// Taxonomy.

		const KEY_LAST_CAT_OMITTED = '_bimeson_last_cat_omitted';
		const KEY_IS_HIDDEN        = '_bimeson_is_hidden';

		const DEFAULT_TAXONOMY          = 'bm_cat';
		const DEFAULT_SUB_TAX_BASE      = 'bm_cat_';
		const DEFAULT_SUB_TAX_CLS_BASE  = 'bm-cat-';
		const DEFAULT_SUB_TAX_QVAR_BASE = 'bm_';

		/**
		 * Root taxonomy slug.
		 *
		 * @var 1.0
		 */
		public $root_tax;

		/**
		 * Slug base of sub taxonomies.
		 *
		 * @var 1.0
		 */
		public $sub_tax_base;

		/**
		 * Class base of sub taxonomies.
		 *
		 * @var 1.0
		 */
		public $sub_tax_cls_base;

		/**
		 * Query variable name base of sub taxonomies.
		 *
		 * @var 1.0
		 */
		public $sub_tax_qvar_base;

		const DEFAULT_YEAR_CLS_BASE = 'bm-year-';
		const DEFAULT_YEAR_QVAR     = 'bm_year';

		/**
		 * Class base of year.
		 *
		 * @var 1.0
		 */
		public $year_cls_base;

		/**
		 * Query variable name of year.
		 *
		 * @var 1.0
		 */
		public $year_qvar;

		/**
		 * The sub taxonomy slugs.
		 *
		 * @var 1.0
		 */
		public $sub_taxes = array();

		/**
		 * Previously edited taxonomy.
		 *
		 * @var 1.0
		 */
		public $old_tax = array();

		/**
		 * Previously edited terms.
		 *
		 * @var 1.0
		 */
		public $old_terms = array();

		/**
		 * The root terms.
		 *
		 * @var 1.0
		 */
		public $root_terms = null;

		/**
		 * The sub terms.
		 *
		 * @var 1.0
		 */
		public $sub_tax_to_terms = array();

		// Filter.

		const KEY_YEAR     = '_year';
		const VAL_YEAR_ALL = 'all';

		/**
		 * Label of year select markup.
		 *
		 * @var 1.0
		 */
		public $year_select_label;

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
function _set_key( string $key ) {
	$inst = _get_instance();

	$inst->fld_list_cfg = $key;
}
