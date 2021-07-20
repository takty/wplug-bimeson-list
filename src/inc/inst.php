<?php
/**
 * Bimeson (Instance)
 *
 * @author Takuto Yanagida
 * @version 2021-07-20
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
		public $FLD_SORT_BY_DATE_FIRST;
		public $FLD_DUP_MULTI_CAT;
		public $FLD_SHOW_FILTER;
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

		public $head_level       = 3;
		public $year_format      = null;
		public $term_name_getter = null;

		public $cache  = [];
		public $rs_idx = null;

		// Taxonomy

		const KEY_LAST_CAT_OMITTED = '_bimeson_last_cat_omitted';
		const KEY_IS_HIDDEN        = '_bimeson_is_hidden';

		const DEFAULT_TAXONOMY          = 'bm_cat';
		const DEFAULT_SUB_TAX_BASE      = 'bm_cat_';
		const DEFAULT_SUB_TAX_CLS_BASE  = 'bm-cat-';
		const DEFAULT_SUB_TAX_QVAR_BASE = 'bm_';
		public $root_tax;
		public $sub_tax_base;
		public $sub_tax_cls_base;
		public $sub_tax_qvar_base;

		const DEFAULT_YEAR_CLS_BASE = 'bm-year-';
		const DEFAULT_YEAR_QVAR     = 'bm_year';
		public $year_cls_base;
		public $year_qvar;

		public $sub_taxes        = [];
		public $old_tax          = [];
		public $old_terms        = [];
		public $root_terms       = null;
		public $sub_tax_to_terms = [];

		// Filter

		const KEY_YEAR     = '_year';
		const VAL_YEAR_ALL = 'all';

		public $year_select_label;

	};
	return $values;
}

function _set_key_base( string $key_base ) {
	$inst = _get_instance();

	$inst->FLD_LIST_ID                 = $key_base . 'list_id';
	$inst->FLD_YEAR_START              = $key_base . 'year_start';
	$inst->FLD_YEAR_END                = $key_base . 'year_end';
	$inst->FLD_COUNT                   = $key_base . 'count';
	$inst->FLD_SORT_BY_DATE_FIRST      = $key_base . 'sort_by_date_first';
	$inst->FLD_DUP_MULTI_CAT           = $key_base . 'duplicate_multi_category';
	$inst->FLD_SHOW_FILTER             = $key_base . 'show_filter';
	$inst->FLD_OMIT_HEAD_OF_SINGLE_CAT = $key_base . 'omit_head_of_single_cat';
	$inst->FLD_JSON_PARAMS             = $key_base . 'json_params';
}
