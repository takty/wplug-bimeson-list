<?php
/**
 * Functions and Definitions for Bimeson
 *
 * @author Takuto Yanagida
 * @version 2021-07-19
 */

namespace wplug\bimeson_list;

require_once __DIR__ . '/assets/util.php';
require_once __DIR__ . '/assets/field.php';
require_once __DIR__ . '/inc/inst.php';
require_once __DIR__ . '/inc/taxonomy.php';
require_once __DIR__ . '/inc/retriever.php';
require_once __DIR__ . '/inc/admin-dest.php';
require_once __DIR__ . '/inc/admin-src.php';
require_once __DIR__ . '/inc/filter.php';
require_once __DIR__ . '/inc/list.php';
require_once __DIR__ . '/inc/shortcode.php';

function initialize( array $args = [] ) {
	$inst = _get_instance();

	$key_base = $args['key_base'] ?? '_bimeson_';
	$url_to   = $args['url_to']   ?? false;
	$lang     = $args['lang']     ?? '';

	$inst->head_level       = $args['heading_level']    ?? 3;
	$inst->year_format      = $args['year_format']      ?? null;
	$inst->term_name_getter = $args['term_name_getter'] ?? null;

	$inst->root_tax          = $args['taxonomy']          ?? $inst::DEFAULT_TAXONOMY;
	$inst->sub_tax_base      = $args['sub_tax_base']      ?? $inst::DEFAULT_SUB_TAX_BASE;
	$inst->sub_tax_cls_base  = $args['sub_tax_cls_base']  ?? $inst::DEFAULT_SUB_TAX_CLS_BASE;
	$inst->sub_tax_qvar_base = $args['sub_tax_qvar_base'] ?? $inst::DEFAULT_SUB_TAX_QVAR_BASE;

	$inst->year_cls_base = $args['year_cls_base'] ?? $inst::DEFAULT_YEAR_CLS_BASE;
	$inst->year_qvar     = $args['year_qvar']     ?? $inst::DEFAULT_YEAR_QVAR;

	$inst->FLD_LIST_ID                 = $key_base . 'list_id';
	$inst->FLD_YEAR_START              = $key_base . 'year_start';
	$inst->FLD_YEAR_END                = $key_base . 'year_end';
	$inst->FLD_COUNT                   = $key_base . 'count';
	$inst->FLD_SORT_BY_DATE_FIRST      = $key_base . 'sort_by_date_first';
	$inst->FLD_DUP_MULTI_CAT           = $key_base . 'duplicate_multi_category';
	$inst->FLD_SHOW_FILTER             = $key_base . 'show_filter';
	$inst->FLD_OMIT_HEAD_OF_SINGLE_CAT = $key_base . 'omit_head_of_single_cat';
	$inst->FLD_JSON_PARAMS             = $key_base . 'json_params';

	_register_post_type();  // Do before initializing taxonomies
	initialize_taxonomy();
	initialize_filter();

	if ( is_admin() ) {
		if ( empty( $url_to ) ) $url_to = get_file_uri( __DIR__ );
		initialize_admin_src( untrailingslashit( $url_to ) );
	} else {
		add_shortcode( $lang );
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


// -----------------------------------------------------------------------------


function get_taxonomy() {
	$inst = _get_instance();
	return $inst->root_tax;
}

function get_sub_taxonomies() {
	$inst = _get_instance();
	return $inst->sub_taxes;
}

function enqueue_script( ?string $url_to = null ) {
	if ( is_null( $url_to ) ) $url_to = get_file_uri( __DIR__ );
	$url_to = untrailingslashit( $url_to );

	if ( is_admin() ) {
		enqueue_script_admin_dest( $url_to );
	} else {
		wp_enqueue_style(  'bimeson_list_filter', $url_to . '/assets/css/filter.min.css' );
		wp_enqueue_script( 'bimeson_list_filter', $url_to . '/assets/js/filter.min.js' );
	}
}

function add_meta_box( string $label, string $screen ) {
	add_meta_box_admin_dest( $label, $screen );
}

function save_meta_box( int $post_id ) {
	save_meta_box_admin_dest( $post_id );
}


// -----------------------------------------------------------------------------


function the_filter( ?int $post_id = null, string $lang = '', string $before = '<div class="bimeson-filter"%s>', string $after = '</div>', string $for = '' ) {
	$post    = get_post( $post_id );
	$post_id = $post->ID;
	$d       = _get_data( $post_id, $lang );

	if ( ! $d || ! $d['show_filter'] ) return;

	if ( ! empty( $for ) ) $for = " for=\"$for\"";
	$before = sprintf( $before, $for );

	echo $before;
	echo_filter( $d['filter_state'], $d['years_exist'] );
	echo $after;
}

function the_list( ?int $post_id = null, string $lang = '', string $before = '<div class="bimeson-list"%s>', string $after = '</div>', string $id = '' ) {
	$post    = get_post( $post_id );
	$post_id = $post->ID;
	$d       = _get_data( $post_id, $lang );

	if ( ! $d || ! is_array( $d['items'] ) ) return;

	if ( ! empty( $id ) ) $id = " id=\"$id\"";
	$before = sprintf( $before, $id );

	echo $before;
	if ( is_null( $d['count'] ) ) {
		echo_heading_list_element( [
			'items' => $d['items'],
			'lang'  => $lang,

			'sort_by_date_first' => $d['sort_by_date_first'],
			'filter_state'       => $d['omit_single_cat'] ? $d['filter_state'] : null,
		] );
	} else {
		echo_list_element( $d['items'], $lang );
	}
	echo $after;
}

function _get_data( int $post_id, string $lang ): array {
	$inst = _get_instance();
	if ( isset( $inst->cache[ "$post_id$lang" ] ) ) return $inst->cache[ "$post_id$lang" ];

	$year_bgn = _get_post_meta_int( $post_id, $inst->FLD_YEAR_START, 1970, 3000 );
	$year_end = _get_post_meta_int( $post_id, $inst->FLD_YEAR_END, 1970, 3000 );
	if ( is_int( $year_bgn ) && is_int( $year_end ) && $year_end < $year_bgn ) {
		[ $year_bgn, $year_end ] = [ $year_end, $year_bgn ];
	}
	$count    = _get_post_meta_int( $post_id, $inst->FLD_COUNT, 1, 9999 );

	$sort_by_date_first = get_post_meta( $post_id, $inst->FLD_SORT_BY_DATE_FIRST, true ) === 'true';
	$dup_multi_cat      = get_post_meta( $post_id, $inst->FLD_DUP_MULTI_CAT, true ) === 'true';
	$filter_state       = json_decode( get_post_meta( $post_id, $inst->FLD_JSON_PARAMS, true ), true );

	$list_id = get_post_meta( $post_id, $inst->FLD_LIST_ID, true );
	if ( empty( $list_id ) ) return null;

	[ $items, $years_exist ] = retrieve_items( $list_id, $lang, $year_bgn, $year_end, $count, $sort_by_date_first, $dup_multi_cat, $filter_state );

	$show_filter     = get_post_meta( $post_id, $inst->FLD_SHOW_FILTER, true ) === 'true';
	$omit_single_cat = get_post_meta( $post_id, $inst->FLD_OMIT_HEAD_OF_SINGLE_CAT, true ) === 'true';

	$d = compact(
		'items', 'years_exist', 'count',
		'sort_by_date_first',
		'show_filter', 'omit_single_cat',
		'filter_state',
	);
	$inst->cache[ "$post_id$lang" ] = $d;
	return $d;
}

function _get_post_meta_int( int $post_id, string $key, int $min, int $max ): ?int {
	$temp = get_post_meta( $post_id, $key, true );
	if ( empty( $temp ) ) return null;
	$val = (int) $temp;
	return ( $val < $min || $max < $val ) ? false : $val;
}
