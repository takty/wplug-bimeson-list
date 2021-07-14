<?php
/**
 * Bimeson (Instance)
 *
 * @author Takuto Yanagida
 * @version 2021-07-15
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
		// Admin Dest

		public $FLD_LIST_ID;
		public $FLD_YEAR_START;
		public $FLD_YEAR_END;
		public $FLD_COUNT;
		public $FLD_SHOW_FILTER;
		public $FLD_SORT_BY_DATE_FIRST;
		public $FLD_OMIT_HEAD_OF_SINGLE_CAT;
		public $FLD_JSON_PARAMS;

		// Admin Src

		const PT = 'bimeson_list';  // Each post means publication list (bimeson list)

		const FLD_MEDIA     = '_bimeson_media';
		const FLD_ADD_TAX   = '_bimeson_add_tax';
		const FLD_ADD_TERM  = '_bimeson_add_term';
		const FLD_ITEMS     = '_bimeson_items';

		public $url_to;
		public $media_picker;

		// Item

		const NOT_MODIFIED = '<NOT MODIFIED>';

		const IT_BODY       = '_body';
		const IT_DATE       = '_date';
		const IT_DOI        = '_doi';
		const IT_LINK_URL   = '_link_url';
		const IT_LINK_TITLE = '_link_title';

		const IT_DATE_NUM = '_date_num';
		const IT_CAT_KEY  = '_cat_key';
		const IT_INDEX    = '_index';

		// Common

		public $head_level       = 2;
		public $year_format      = null;
		public $term_name_getter = null;

		public $cache = [];

		// Taxonomy

		const DEFAULT_TAXONOMY     = 'bm_cat';
		const DEFAULT_SUB_TAX_BASE = 'bm_cat_';

		const KEY_LAST_CAT_OMITTED = '_bimeson_last_cat_omitted';
		const KEY_IS_HIDDEN        = '_bimeson_is_hidden';

		public $sub_tax_base;
		public $root_tax;
		public $sub_taxes = [];

		public $old_tax   = [];
		public $old_terms = [];

		public $root_terms       = null;
		public $sub_tax_to_terms = [];

		// Filter

		const KEY_YEAR     = '_year';
		const VAL_YEAR_ALL = 'all';
		const QVAR_YEAR    = 'bm-year';
	};
	return $values;
}