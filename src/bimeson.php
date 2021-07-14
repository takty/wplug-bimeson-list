<?php
/**
 * Functions and Definitions for Bimeson
 *
 * @author Takuto Yanagida
 * @version 2021-07-14
 */

namespace wplug\bimeson_list;

require_once __DIR__ . '/assets/util.php';
require_once __DIR__ . '/assets/field.php';
require_once __DIR__ . '/inc/admin-dest.php';
require_once __DIR__ . '/inc/admin-src.php';
require_once __DIR__ . '/inc/taxonomy.php';
require_once __DIR__ . '/inc/filter.php';
require_once __DIR__ . '/inc/list.php';

function initialize( string $key_base = '_bimeson_', $taxonomy = false, $sub_tax_base = false, $url_to = false ) {
	$inst = _get_instance();
	$inst->FLD_LIST_ID                 = $key_base . 'list_id';
	$inst->FLD_YEAR_START              = $key_base . 'year_start';
	$inst->FLD_YEAR_END                = $key_base . 'year_end';
	$inst->FLD_COUNT                   = $key_base . 'count';
	$inst->FLD_SHOW_FILTER             = $key_base . 'show_filter';
	$inst->FLD_SORT_BY_DATE_FIRST      = $key_base . 'sort_by_date_first';
	$inst->FLD_OMIT_HEAD_OF_SINGLE_CAT = $key_base . 'omit_head_of_single_cat';
	$inst->FLD_JSON_PARAMS             = $key_base . 'json_params';

	_register_post_type();  // Do before initializing taxonomies
	initialize_taxonomy( $taxonomy, $sub_tax_base );
	initialize_filter();

	if ( is_admin() ) {
		initialize_admin_src( $url_to );
	} else {
		add_shortcode( 'publication', function ( $atts, $content = null ) {
			global $post;
			ob_start();
			$inst->the_filter_list( $post->ID );
			$ret = ob_get_contents();
			ob_end_clean();
			return $ret;
		} );
	}
}

function _register_post_type() {
	$inst = _get_instance();
	register_post_type( $inst::PT, [
		'label'         => _x( 'Publication List', 'admin src', 'bimeson_list' ),
		'labels'        => [],
		'public'        => true,
		'show_ui'       => true,
		'menu_position' => 5,
		'menu_icon'     => 'dashicons-analytics',
		'has_archive'   => false,
		'rewrite'       => false,
		'supports'      => [ 'title' ],
	] );
}

function set_heading_level( $level ) {
	$inst = _get_instance();
	$inst->head_level = $level;
}

function set_year_format( $format ) {
	$inst = _get_instance();
	$inst->year_format = $format;
}

function set_term_name_getter( $func ) {
	$inst = _get_instance();
	$inst->term_name_getter = $func;
}


// -------------------------------------------------------------------------


function get_taxonomy() {
	$inst = _get_instance();
	return $inst->root_tax;
}

function get_sub_taxonomies() {
	$inst = _get_instance();
	return $inst->sub_taxes;
}

function enqueue_script( $url_to = false ) {
	if ( $url_to === false ) $url_to = get_file_uri( __DIR__ );
	$url_to = untrailingslashit( $url_to );

	if ( is_admin() ) {
		enqueue_script_admin_dest( $url_to );
	} else {
		wp_enqueue_style(  'bimeson_list_filter', $url_to . '/assets/css/filter.min.css' );
		wp_enqueue_script( 'bimeson_list_filter', $url_to . '/assets/js/filter.min.js' );
	}
}

function add_meta_box( $label, $screen ) {
	add_meta_box_admin_dest( $label, $screen );
}

function save_meta_box( $post_id ) {
	save_meta_box_admin_dest( $post_id );
}


// -------------------------------------------------------------------------


function the_filter( $post_id = null, $lang = '' ) {
	$post = get_post( $post_id );
	$post_id = $post->ID;

	$d = _get_data( $post_id, $lang );
	if ( $d['show_filter'] ) {
		echo_filter( $d['filter_state'], $d['years_exist'] );
	}
}

function the_list( $post_id = null, $lang = '', $before = '<div class="bimeson-list">', $after = '</div>' ) {
	$inst = _get_instance();
	$post = get_post( $post_id );
	$post_id = $post->ID;

	$d = _get_data( $post_id, $lang );
	if ( is_array( $d['items'] ) ) {
		echo $before;
		if ( $d['count'] === false ) {
			echo_heading_list_element( [
				'items'            => $d['items'],
				'lang'             => $lang,
				'year_format'      => $inst->year_format,
				'term_name_getter' => $inst->term_name_getter,

				'filter_state'       => $d['omit_single_cat'] ? $d['filter_state'] : false,
				'sort_by_year_first' => $d['sort_by_year_first'],
				'head_level'         => $inst->head_level,
			] );
		} else {
			echo_list_element( [
				'items'    => $d['items'],
				'lang'     => $lang,
			] );
		}
		echo $after;
	}
}


// -------------------------------------------------------------------------


function _get_data( $post_id, $lang ) {
	$inst = _get_instance();
	if ( isset( $inst->cache[ "$post_id$lang" ] ) ) return $inst->cache[ "$post_id$lang" ];

	$filter_state       = json_decode( get_post_meta( $post_id, $inst->FLD_JSON_PARAMS, true ), true );
	$sort_by_year_first = get_post_meta( $post_id, $inst->FLD_SORT_BY_DATE_FIRST, true ) === 'true';
	$show_filter        = get_post_meta( $post_id, $inst->FLD_SHOW_FILTER, true ) === 'true';
	$omit_single_cat    = get_post_meta( $post_id, $inst->FLD_OMIT_HEAD_OF_SINGLE_CAT, true ) === 'true';
	$temp               = get_post_meta( $post_id, $inst->FLD_COUNT, true );
	$count              = ( empty( $temp ) || (int) $temp < 1 ) ? false : (int) $temp;
	$temp               = get_post_meta( $post_id, $inst->FLD_YEAR_START, true );
	$year_bgn           = ( empty( $temp ) || (int) $temp < 1970 || (int) $temp > 3000 ) ? false : (int) $temp;
	$temp               = get_post_meta( $post_id, $inst->FLD_YEAR_END, true );
	$year_end           = ( empty( $temp ) || (int) $temp < 1970 || (int) $temp > 3000 ) ? false : (int) $temp;

	$items = _get_list_items( $post_id );
	_sort_list_items( $items, $sort_by_year_first );
	[ $items, $years_exist ] = _filter_list_items( $items, $lang, $filter_state, $year_bgn, $year_end, $count );

	$d = compact(
		'items', 'years_exist',
		'count', 'show_filter', 'sort_by_year_first', 'omit_single_cat',
		'filter_state',
	);
	$inst->cache[ "$post_id$lang" ] = $d;
	return $d;
}

function _get_list_items( $post_id ) {
	$inst = _get_instance();
	$list_id = get_post_meta( $post_id, $inst->FLD_LIST_ID, true );
	if ( empty( $list_id ) ) return [];

	$items_json = get_post_meta( $list_id, $inst::FLD_ITEMS, true );
	if ( empty( $items_json ) ) return [];

	$items = json_decode( $items_json, true );
	if ( ! is_array( $items ) ) return [];

	return $items;
}

function _sort_list_items( array &$items, $sort_by_year_first ) {
	$inst = _get_instance();
	if ( empty( $items ) ) return;
	$inst->sort_by_year_first = $sort_by_year_first;

	$rs_to_slugs       = get_root_slug_to_sub_slugs();
	$rs_to_depths      = get_root_slug_to_sub_depths();
	$slug_to_ancestors = get_sub_slug_to_ancestors();

	foreach ( $items as $idx => &$it ) {
		$it[ $inst::IT_CAT_KEY ] = _make_cat_key( $it, $rs_to_slugs, $rs_to_depths, $slug_to_ancestors );
		$it[ $inst::IT_INDEX ]   = $idx;
	}
	usort( $items, '\wplug\bimeson_list\_cb_compare_item' );
}

function _make_cat_key( $item, $rs_to_slugs, $rs_to_depths, $slug_to_ancestors ) {
	$cats = [];
	foreach ( $rs_to_slugs as $rs => $slugs ) {
		if ( ! isset( $rs_to_depths[ $rs ] ) ) continue;
		$depth = $rs_to_depths[ $rs ];

		list( $idx, $s ) = _get_one_of_ordered_terms( $item, $rs, $slugs );
		if ( $s ) {
			if ( isset( $slug_to_ancestors[ $s ] ) ) {
				$cats = array_merge( $cats, $slug_to_ancestors[ $s ] );
			}
			$cats[] = $s;
			$depth -= count( $cats );
		}
		for ( $i = 0; $i < $depth; $i += 1 ) $cats[] = '';
	}
	return implode( ',', $cats );
}

function _cb_compare_item( $a, $b ) {
	$inst = _get_instance();
	if ( $inst->sort_by_year_first !== false ) {
		if ( isset( $a[ $inst::IT_DATE_NUM ] ) && isset( $b[ $inst::IT_DATE_NUM ] ) && $a[ $inst::IT_DATE_NUM ] !== $b[ $inst::IT_DATE_NUM ] ) {
			return $a[ $inst::IT_DATE_NUM ] < $b[ $inst::IT_DATE_NUM ] ? 1 : -1;
		}
	}
	$rs_to_slugs = get_root_slug_to_sub_slugs();

	$idx = 0;
	foreach ( $rs_to_slugs as $rs => $slugs ) {
		list( $ai, $av ) = _get_one_of_ordered_terms( $a, $rs, $slugs );
		list( $bi, $bv ) = _get_one_of_ordered_terms( $b, $rs, $slugs );
		if ( $av === null && $bv === null) continue;

		if ( $ai === -1 && $bi === -1) {
			$c = strcmp( $av, $bv );
			if ( $c !== 0 ) return $c;
		} else {
			if ( $ai < $bi ) return -1;
			if ( $ai > $bi ) return 1;
		}
	}
	if ( isset( $a[ $inst::IT_DATE_NUM ] ) && isset( $b[ $inst::IT_DATE_NUM ] ) && $a[ $inst::IT_DATE_NUM ] !== $b[ $inst::IT_DATE_NUM ] ) {
		return $a[ $inst::IT_DATE_NUM ] < $b[ $inst::IT_DATE_NUM ] ? 1 : -1;
	}
	return $a[ $inst::IT_INDEX ] < $b[ $inst::IT_INDEX ] ? -1 : 1;
}

function _get_one_of_ordered_terms( $item, $rs, $sub_slugs ) {
	if ( ! isset( $item[ $rs ] ) ) return [ -1, null ];
	$vs = $item[ $rs ];
	foreach ( $sub_slugs as $idx => $s ) {
		if ( in_array( $s, $vs, true ) ) return [ $idx, $s ];
	}
	return [ -1, null ];
}


// -------------------------------------------------------------------------


function _filter_list_items( $items, $lang, $filter_state, $year_bgn, $year_end, $count ) {
	$inst = _get_instance();
	if ( empty( $items ) ) return [ [], [] ];
	$ret   = [];
	$years = [];

	$do_year_filter = ( $year_bgn !== false || $year_end !== false );
	$year_bgn = ( $year_bgn === false ) ? 0        : (int) str_pad( $year_bgn . '', 8, '0', STR_PAD_RIGHT );
	$year_end = ( $year_end === false ) ? 99999999 : (int) str_pad( $year_end . '', 8, '9', STR_PAD_RIGHT );

	foreach ( $items as $item ) {
		if ( $do_year_filter ) {
			if ( ! isset( $item[ $inst::IT_DATE_NUM ] ) ) continue;  // next item
			$date = (int) $item[ $inst::IT_DATE_NUM ];
			if ( $date < $year_bgn || $year_end < $date ) continue;  // next item
		}
		if ( ! _match_filter( $item, $filter_state ) ) continue;
		if ( empty( $item[ $inst::IT_BODY . "_$lang" ] ) && empty( $item[ $inst::IT_BODY ] ) ) continue;

		$ret[] = $item;
		if ( isset( $item[ $inst::IT_DATE_NUM ] ) ) {
			$years[ substr( $item[ $inst::IT_DATE_NUM ], 0, 4 ) ] = 1;
		}
		if ( $count !== false && count( $ret ) === $count ) break;
	}
	$years = array_keys( $years );
	rsort( $years );
	return [ $ret, $years ];
}

function _match_filter( $item, $filter_state ) {
	if ( ! is_array( $filter_state ) ) return true;
	foreach ( $filter_state as $rs => $slugs ) {
		if ( ! isset( $item[ $rs ] ) ) return false;
		$int = array_intersect( $slugs, $item[ $rs ] );
		if ( 0 === count( $int ) ) return false;
	}
	return true;
}


// -----------------------------------------------------------------------------


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
