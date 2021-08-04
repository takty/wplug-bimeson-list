<?php
/**
 * Custom Field Utilities
 *
 * @package Wplug Bimeson List
 * @author Takuto Yanagida
 * @version 2021-08-04
 */

namespace wplug\bimeson_list;

function name_id( string $key ) {
	$_key = esc_attr( $key );
	echo "name=\"$_key\" id=\"$_key\"";
}

function normalize_date( string $str ): string {
	$str = mb_convert_kana( $str, 'n', 'utf-8' );
	$nums = preg_split( '/\D/', $str );
	$vals = [];
	foreach ( $nums as $num ) {
		$v = (int) trim( $num );
		if ( $v !== 0 ) $vals[] = $v;
	}
	if ( 3 <= count( $vals ) ) {
		$str = sprintf( '%04d-%02d-%02d', $vals[0], $vals[1], $vals[2] );
	} else if ( count( $vals ) === 2 ) {
		$str = sprintf( '%04d-%02d', $vals[0], $vals[1] );
	} else if ( count( $vals ) === 1 ) {
		$str = sprintf( '%04d', $vals[0] );
	}
	return $str;
}


// Multiple Post Meta ----------------------------------------------------------


function get_multiple_post_meta( int $post_id, string $base_key, array $keys ): array {
	$ret = [];
	$val = get_post_meta( $post_id, $base_key, true );

	if ( is_numeric( $val ) ) {  // for backward compatibility
		$count = (int) $val;
		for ( $i = 0; $i < $count; $i += 1 ) {
			$bki = "{$base_key}_{$i}_";
			$set = [];
			foreach ( $keys as $key ) {
				$val         = get_post_meta( $post_id, $bki . $key, true );
				$set[ $key ] = $val;
			}
			$ret[] = $set;
		}
	} else {
		$ret = json_decode( $val, true );
		if ( ! is_array( $ret ) ) $ret = [];
	}
	return $ret;
}

function get_multiple_post_meta_from_post( string $base_key, array $keys ): array {
	$ret   = [];

	if ( isset( $_POST[ $base_key ] ) ) {  // for backward compatibility
		$count = (int) ( $_POST[ $base_key ] ?? 0 );

		for ( $i = 0; $i < $count; $i += 1 ) {
			$bki = "{$base_key}_{$i}_";
			$set = [];
			foreach ( $keys as $key ) {
				$set[ $key ] = $_POST["$bki$key"] ?? '';
			}
			$ret[] = $set;
		}
	} else {
		$count = count( $_POST[ "{$base_key}_{$keys[0]}" ] );

		for ( $i = 0; $i < $count; $i += 1 ) {
			$set = [];
			foreach ( $keys as $key ) {
				$set[ $key ] = $_POST["{$base_key}_{$key}"][ $i ];
			}
			$ret[] = $set;
		}
	}
	return $ret;
}

function update_multiple_post_meta( int $post_id, string $base_key, array $vals, ?array $keys = null ) {
	$vals  = array_values( $vals );
	$count = count( $vals );

	$val = get_post_meta( $post_id, $base_key, true );
	if ( is_numeric( $val ) ) {  // for backward compatibility
		if ( $keys === null && $count > 0 ) {
			$keys = array_keys( $vals[0] );
		}
		$old_count = (int) $val;
		for ( $i = 0; $i < $old_count; $i += 1 ) {
			$bki = "{$base_key}_{$i}_";
			foreach ( $keys as $key ) {
				delete_post_meta( $post_id, $bki . $key );
			}
		}
	}
	if ( $count === 0 ) {
		delete_post_meta( $post_id, $base_key );
	} else {
		$json = json_encode( $vals, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		update_post_meta( $post_id, $base_key, addslashes( $json ) );  // Because the meta value is passed through the stripslashes() function upon being stored.
	}
}
