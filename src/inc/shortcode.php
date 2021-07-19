<?php
/**
 * Bimeson (Shortcode)
 *
 * @author Takuto Yanagida
 * @version 2021-07-19
 *
 * [publication list="<slug or post ID>" count="10" date-sort omit-single dup-item date="2020-2021" taxonomy="slug1, slug2, ..."]
 *
 */

namespace wplug\bimeson_list;

function register_shortcode( $lang ) {
	static $serial = 0;

	\add_shortcode( 'publication', function ( $atts, ?string $content = null ) use ( $lang, $serial ): string {
		if ( is_string( $atts ) ) $atts = [];

		$list_id = _extract_list_id_atts( $atts );
		if ( is_null( $list_id ) ) return '';

		[ $date_bgn, $date_end ] = _extract_date_atts( $atts );

		$count              = isset( $atts['count'] ) ? ( (int) $atts['count'] ) : null;
		$sort_by_date_first = in_array( 'date-sort', $atts, true ) || (bool) ( $atts['date-sort'] ?? false );
		$dup_multi_cat      = in_array( 'dup-item', $atts, true ) || (bool) ( $atts['dup-item'] ?? false );
		$omit_single_cat    = in_array( 'omit-single', $atts, true ) || (bool) ( $atts['omit-single'] ?? false );

		$filter_state = _extract_filter_state( $atts );
		[ $items, $years_exist ] = retrieve_items( $list_id, $lang, $date_bgn, $date_end, $count, $sort_by_date_first, $dup_multi_cat, $filter_state );

		$serial += 1;
		$id = "bml-sc$serial";

		ob_start();
		if ( isset( $atts['show-filter'] ) ) {
			enqueue_script_filter();
			printf( '<div class="bimeson-filter"%s>', " for=\"$id\"" );
			echo_filter( $filter_state, $years_exist );
			echo '</div>';
		}
		if ( ! is_null( $content ) ) {
			$content = str_replace( '<p></p>', '', balanceTags( $content, true ) );
			echo $content;
		}
		printf( '<div class="bimeson-list"%s>', " id=\"$id\"" );
		if ( ! $count ) {
			echo_heading_list_element( [
				'items'              => $items,
				'lang'               => $lang,
				'sort_by_date_first' => $sort_by_date_first,
				'filter_state'       => $omit_single_cat ? $filter_state : null,
			] );
		} else {
			echo_list_element( $items, $lang );
		}
		echo '</div>';

		$ret = ob_get_contents();
		ob_end_clean();
		return $ret;
	} );
}

function _extract_list_id_atts( array $atts ): ?int {
	$slug = $atts['list'] ?? null;
	if ( empty( $slug ) ) return null;

	$inst = _get_instance();
	$list = get_page_by_path( $slug, 'OBJECT', $inst::PT );
	if ( $list ) return $list->ID;

	if ( is_numeric( $slug ) ) {
		$list = get_post( (int) $slug );
		if ( $list ) return $list->ID;
	}
	return null;
}

function _extract_date_atts( array $atts ): array {
	$ds = [];
	foreach ( [ 'date', 'date-start', 'date-end' ] as $key ) {
		if ( ! empty( $atts[ $key ] ) ) {
			if ( is_numeric( $atts[ $key ] ) ) {
				$ds[] = $atts[ $key ];
			} else {
				$vs = array_map( '\trim', explode( '-', $atts[ $key ] ) );
				foreach ( $vs as $v ) {
					if ( is_numeric( $v ) ) $ds[] = $v;
				}
			}
		}
	}
	sort( $ds );  // Sort as strings

	if ( 0 === count( $ds ) ) return [ null, null ];
	return [ $ds[0], end( $ds ) ];
}

function _extract_filter_state( array $atts ): ?array {
	$fs = [];
	foreach ( get_root_slugs() as $rs ) {
		if ( isset( $atts[ $rs ] ) ) {
			$vs = array_map( '\trim', explode( ',', $atts[ $rs ] ) );
			$fs[ $rs ] = $vs;
		}
	}
	return count( $fs ) ? $fs : null;
}
