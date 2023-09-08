<?php
/**
 * Bimeson (Shortcode)
 *
 * @package Wplug Bimeson List
 * @author Takuto Yanagida
 * @version 2023-09-08
 *
 * [publication list="<slug or post ID>" count="10" date-sort omit-single dup-item date="2020-2021" taxonomy="slug1, slug2, ..."]
 */

namespace wplug\bimeson_list;

require_once __DIR__ . '/../bimeson.php';
require_once __DIR__ . '/filter.php';
require_once __DIR__ . '/inst.php';
require_once __DIR__ . '/list.php';
require_once __DIR__ . '/retriever.php';
require_once __DIR__ . '/taxonomy.php';

/**
 * Registers the shortcode.
 *
 * @param string $lang Language.
 */
function register_shortcode( string $lang ): void {
	static $serial = 0;

	\add_shortcode(
		'publication',
		function ( $atts, ?string $content = null ) use ( $lang, $serial ): string {
			if ( is_string( $atts ) ) {
				$atts = array();
			}
			$d = _get_data_shortcode( $atts, $lang );
			++$serial;
			$id = "bml-sc$serial";

			ob_start();
			if ( $d && $d['show_filter'] ) {
				echo_the_filter( $d['filter_state'], $d['years_exist'], '<div class="wplug-bimeson-filter"%s>', '</div>', $id );
			}
			if ( ! is_null( $content ) ) {
				echo str_replace( '<p></p>', '', balanceTags( $content, true ) );  // phpcs:ignore
			}
			if ( $d ) {
				echo_the_list( $d, $lang, '<div class="wplug-bimeson-list"%s>', '</div>', $id );
			}
			$ret = (string) ob_get_contents();
			ob_end_clean();
			return $ret;
		}
	);
}

/**
 * Retrieves the data of the shortcode.
 *
 * @access private
 *
 * @param array<string, mixed> $atts Attributes.
 * @param string               $lang Language.
 * @return array<string, mixed>|null Data.
 */
function _get_data_shortcode( array $atts, string $lang ): ?array {
	$list_id = _extract_list_id_atts( $atts );  // Bimeson List.
	if ( is_null( $list_id ) ) {
		return null;
	}
	list( $date_bgn, $date_end ) = _extract_date_atts( $atts );

	$count              = isset( $atts['count'] ) ? ( (int) $atts['count'] ) : null;
	$sort_by_date_first = in_array( 'date-sort', $atts, true ) || (bool) ( $atts['date-sort'] ?? false );
	$dup_multi_cat      = in_array( 'dup-item', $atts, true ) || (bool) ( $atts['dup-item'] ?? false );
	$omit_single_cat    = in_array( 'omit-single', $atts, true ) || (bool) ( $atts['omit-single'] ?? false );

	$filter_state = _extract_filter_state( $atts );

	// Bimeson List.
	$items = get_filtered_items( $list_id, $lang, $date_bgn, $date_end, $filter_state );

	list( $items, $years_exist ) = retrieve_items( $items, $count, $sort_by_date_first, $dup_multi_cat, $filter_state );

	$d = compact( 'count', 'sort_by_date_first', 'omit_single_cat', 'filter_state' );

	$d['items']       = $items;
	$d['years_exist'] = $years_exist;
	$d['show_filter'] = isset( $atts['show-filter'] );
	return $d;
}

/**
 * Extracts date attribute.
 *
 * @access private
 *
 * @param array<string, mixed> $atts Attributes.
 * @return array{string|null, string|null} Date.
 */
function _extract_date_atts( array $atts ): array {
	$ds = array();
	foreach ( array( 'date', 'date-start', 'date-end' ) as $key ) {
		if ( ! empty( $atts[ $key ] ) ) {
			if ( is_numeric( $atts[ $key ] ) ) {
				$ds[] = (string) $atts[ $key ];
			} else {
				$vs = array_map( '\trim', explode( '-', $atts[ $key ] ) );
				foreach ( $vs as $v ) {
					if ( is_numeric( $v ) ) {
						$ds[] = (string) $v;
					}
				}
			}
		}
	}
	sort( $ds );  // Sort as strings.

	if ( 0 === count( $ds ) ) {
		return array( null, null );
	}
	return array( $ds[0], end( $ds ) );
}

/**
 * Extracts filter state attribute.
 *
 * @access private
 *
 * @param array<string, mixed> $atts Attributes.
 * @return array<string, mixed>|null Filter states.
 */
function _extract_filter_state( array $atts ): ?array {
	$fs = array();
	foreach ( get_root_slugs() as $rs ) {
		if ( isset( $atts[ $rs ] ) ) {
			$fs[ $rs ] = array_map( '\trim', explode( ',', $atts[ $rs ] ) );
		}
	}
	return count( $fs ) ? $fs : null;
}


// -----------------------------------------------------------------------------


/**
 * Extracts list id attribute.
 *
 * @access private
 *
 * @param array<string, mixed> $atts Attributes.
 * @return ?int List ID.
 */
function _extract_list_id_atts( array $atts ): ?int {
	// Bimeson List.
	$slug = $atts['list'] ?? null;
	if ( empty( $slug ) ) {
		return null;
	}
	$inst = _get_instance();
	$list = get_page_by_path( $slug, 'OBJECT', $inst::PT );
	if ( $list ) {
		return $list->ID;
	}
	if ( is_numeric( $slug ) ) {
		$list = get_post( (int) $slug );
		if ( $list ) {
			return $list->ID;
		}
	}
	return null;
}
