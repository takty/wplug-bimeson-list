<?php
/**
 * Custom Field Utilities
 *
 * @package Wplug Bimeson List
 * @author Takuto Yanagida
 * @version 2022-01-17
 */

namespace wplug\bimeson_list;

/**
 * Echos name and id attributes.
 *
 * @param string $key The key.
 */
function name_id( string $key ) {
	$_key = esc_attr( $key );
	echo "name=\"$_key\" id=\"$_key\"";  // phpcs:ignore
}

/**
 * Normalizes date string.
 *
 * @param string $str A string.
 * @return string Normalized string.
 */
function normalize_date( string $str ): string {
	$str  = mb_convert_kana( $str, 'n', 'utf-8' );
	$nums = preg_split( '/\D/', $str );
	$vals = array();
	foreach ( $nums as $num ) {
		$v = (int) trim( $num );
		if ( 0 !== $v && is_numeric( $v ) ) {
			$vals[] = $v;
		}
	}
	if ( 3 <= count( $vals ) ) {
		$str = sprintf( '%04d-%02d-%02d', $vals[0], $vals[1], $vals[2] );
	} elseif ( count( $vals ) === 2 ) {
		$str = sprintf( '%04d-%02d', $vals[0], $vals[1] );
	} elseif ( count( $vals ) === 1 ) {
		$str = sprintf( '%04d', $vals[0] );
	}
	return $str;
}
