<?php
/**
 * Functions and Definitions for Bimeson
 *
 * @package Wplug Bimeson List
 * @author Takuto Yanagida
 * @version 2021-08-15
 */

namespace wplug\bimeson_list;

require_once __DIR__ . '/assets/util.php';
require_once __DIR__ . '/assets/field.php';
require_once __DIR__ . '/inc/inst.php';
require_once __DIR__ . '/inc/post-type.php';
require_once __DIR__ . '/inc/taxonomy.php';
require_once __DIR__ . '/inc/retriever.php';
require_once __DIR__ . '/inc/filter.php';
require_once __DIR__ . '/inc/list.php';
require_once __DIR__ . '/inc/template-admin.php';
require_once __DIR__ . '/inc/shortcode.php';

function initialize( array $args = [] ) {
	$inst = _get_instance();

	_set_key( $args['key'] ?? '_bimeson' );

	$url_to = untrailingslashit( $args['url_to'] ?? get_file_uri( __DIR__ ) );
	$lang   = $args['lang'] ?? '';

	$inst->head_level        = $args['heading_level']     ?? 3;
	$inst->year_format       = $args['year_format']       ?? null;
	$inst->term_name_getter  = $args['term_name_getter']  ?? null;
	$inst->year_select_label = $args['year_select_label'] ?? __( 'Select Year' );

	$inst->root_tax          = $args['taxonomy']          ?? $inst::DEFAULT_TAXONOMY;
	$inst->sub_tax_base      = $args['sub_tax_base']      ?? $inst::DEFAULT_SUB_TAX_BASE;
	$inst->sub_tax_cls_base  = $args['sub_tax_cls_base']  ?? $inst::DEFAULT_SUB_TAX_CLS_BASE;
	$inst->sub_tax_qvar_base = $args['sub_tax_qvar_base'] ?? $inst::DEFAULT_SUB_TAX_QVAR_BASE;

	$inst->year_cls_base = $args['year_cls_base'] ?? $inst::DEFAULT_YEAR_CLS_BASE;
	$inst->year_qvar     = $args['year_qvar']     ?? $inst::DEFAULT_YEAR_QVAR;

	initialize_post_type( $url_to );  // Do before initializing taxonomies
	initialize_taxonomy();
	initialize_filter();

	_register_script( $url_to );
	if ( ! is_admin() ) register_shortcode( $lang );
}

function _register_script( string $url_to ) {
	if ( is_admin() ) {
		if ( ! _is_the_post_type() ) {
			add_action( 'admin_enqueue_scripts', function () use ( $url_to ) {
				wp_enqueue_style(  'bimeson_list_template_admin', $url_to . '/assets/css/template-admin.min.css' );
				wp_enqueue_script( 'bimeson_list_template_admin', $url_to . '/assets/js/template-admin.min.js' );
			} );
		}
	} else {
		add_action( 'wp_enqueue_scripts', function () use ( $url_to ) {
			wp_register_style(  'bimeson_list_filter', $url_to . '/assets/css/filter.min.css' );
			wp_register_script( 'bimeson_list_filter', $url_to . '/assets/js/filter.min.js' );
		} );
	}
}

function _is_the_post_type() {
	$inst = _get_instance();
	global $pagenow;
	return in_array( $pagenow, [ 'post.php', 'post-new.php' ], true ) && is_post_type( $inst::PT );
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

function add_meta_box( string $label, string $screen ) {
	add_meta_box_template_admin( $label, $screen );
}

function save_meta_box( int $post_id ) {
	save_meta_box_template_admin( $post_id );
}


// -----------------------------------------------------------------------------


function the_filter( ?int $post_id = null, string $lang = '', string $before = '<div class="bimeson-filter"%s>', string $after = '</div>', string $for = 'bml' ) {
	$post = get_post( $post_id );
	$d    = _get_data( $post->ID, $lang );

	if ( ! $d || ! $d['show_filter'] ) return;
	echo_the_filter( $d['filter_state'], $d['years_exist'], $before, $after, $for );
}

function the_list( ?int $post_id = null, string $lang = '', string $before = '<div class="bimeson-list"%s>', string $after = '</div>', string $id = 'bml' ) {
	$post = get_post( $post_id );
	$d    = _get_data( $post->ID, $lang );

	if ( ! $d ) return;
	echo_the_list( $d, $lang, $before, $after, $id );
}

function _get_data( int $post_id, string $lang ): array {
	$inst = _get_instance();
	if ( isset( $inst->cache["$post_id$lang"] ) ) return $inst->cache["$post_id$lang"];

	$d = get_template_admin_config( $post_id );
	if ( empty( $d['list_id'] ) ) return null;  // Bimeson List

	// Bimeson List
	$items = get_filtered_items( $d['list_id'], $lang, (string) $d['year_bgn'], (string) $d['year_end'], $d['filter_state'] );
	[ $items, $years_exist ] = retrieve_items( $items, $d['count'], $d['sort_by_date_first'], $d['dup_multi_cat'], $d['filter_state'] );

	$d['items']       = $items;
	$d['years_exist'] = $years_exist;

	$inst->cache["$post_id$lang"] = $d;
	return $d;
}


// -----------------------------------------------------------------------------


function get_filtered_items( int $list_id, string $lang, ?string $date_bgn, ?string $date_end, ?array $filter_state ) {
	$inst = _get_instance();
	if ( isset( $filter_state[ $inst::KEY_VISIBLE ] ) ) unset( $filter_state[ $inst::KEY_VISIBLE ] );

	$items = _get_list_items( $list_id );
	$items = _filter_items( $items, $lang, $date_bgn, $date_end, $filter_state );
	return $items;
}

function _get_list_items( int $list_id ): array {
	$inst = _get_instance();

	$items_json = get_post_meta( $list_id, $inst::FLD_ITEMS, true );
	if ( empty( $items_json ) ) return [];

	$items = json_decode( $items_json, true );
	if ( ! is_array( $items ) ) return [];

	foreach ( $items as $idx => &$it ) {
		$it[ $inst::IT_INDEX ] = $idx;
	}
	return $items;
}

function _filter_items( array $items, string $lang, ?string $date_bgn, ?string $date_end, ?array $filter_state ) {
	$inst = _get_instance();
	$ret  = [];

	$by_date = ( ! empty( $date_bgn ) || ! empty( $date_end ) );
	$date_b  = (int) str_pad( empty( $date_bgn ) ? '' : $date_bgn, 8, '0', STR_PAD_RIGHT );
	$date_e  = (int) str_pad( empty( $date_end ) ? '' : $date_end, 8, '9', STR_PAD_RIGHT );

	foreach ( $items as $it ) {
		if ( $by_date ) {
			if ( ! isset( $it[ $inst::IT_DATE_NUM ] ) ) continue;  // next item
			$date = (int) $it[ $inst::IT_DATE_NUM ];
			if ( $date < $date_b || $date_e < $date ) continue;  // next item
		}
		if ( ! _match_filter( $it, $filter_state ) ) continue;
		if ( empty( $it[ $inst::IT_BODY . "_$lang" ] ) && empty( $it[ $inst::IT_BODY ] ) ) continue;

		$ret[] = $it;
	}
	return $ret;
}

function _match_filter( array $it, ?array $filter_state ): bool {
	if ( ! is_array( $filter_state ) ) return true;
	foreach ( $filter_state as $rs => $slugs ) {
		if ( ! isset( $it[ $rs ] ) ) return false;
		$int = array_intersect( $slugs, $it[ $rs ] );
		if ( 0 === count( $int ) ) return false;
	}
	return true;
}
