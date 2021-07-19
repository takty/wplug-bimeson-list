<?php
/**
 * Custom Field Utilities
 *
 * @author Takuto Yanagida
 * @version 2021-07-19
 */

namespace wplug\bimeson_list;

function save_post_meta( int $post_id, string $key, $filter = null, $default = null ) {
	$val = isset( $_POST[ $key ] ) ? $_POST[ $key ] : null;
	if ( $filter !== null && $val !== null ) {
		$val = $filter( $val );
	}
	if ( empty( $val ) ) {
		if ( $default === null ) {
			delete_post_meta( $post_id, $key );
			return;
		}
		$val = $default;
	}
	update_post_meta( $post_id, $key, $val );
}

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
	$ret   = [];
	$count = (int) get_post_meta( $post_id, $base_key, true );

	for ( $i = 0; $i < $count; $i += 1 ) {
		$bki = "{$base_key}_{$i}_";
		$set = [];
		foreach ( $keys as $key ) {
			$val         = get_post_meta( $post_id, $bki . $key, true );
			$set[ $key ] = $val;
		}
		$ret[] = $set;
	}
	return $ret;
}

function get_multiple_post_meta_from_post( string $base_key, array $keys ): array {
	$ret   = [];
	$count = (int) ( $_POST[ $base_key ] ?? 0 );

	for ( $i = 0; $i < $count; $i += 1 ) {
		$bki = "{$base_key}_{$i}_";
		$set = [];
		foreach ( $keys as $key ) {
			$k         = $bki . $key;
			$val       = $_POST[ $k ] ?? '';
			$set[$key] = $val;
		}
		$ret[] = $set;
	}
	return $ret;
}

function update_multiple_post_meta( int $post_id, string $base_key, array $metas, ?array $keys = null ) {
	$metas = array_values( $metas );
	$count = count( $metas );

	if ( $keys === null && $count > 0 ) {
		$keys = array_keys( $metas[0] );
	}

	$old_count = (int) get_post_meta( $post_id, $base_key, true );
	for ( $i = 0; $i < $old_count; $i += 1 ) {
		$bki = "{$base_key}_{$i}_";
		foreach ( $keys as $key ) {
			delete_post_meta( $post_id, $bki . $key );
		}
	}
	if ( $count === 0 ) {
		delete_post_meta( $post_id, $base_key );
		return;
	}
	update_post_meta( $post_id, $base_key, $count );
	for ( $i = 0; $i < $count; $i += 1 ) {
		$bki = "{$base_key}_{$i}_";
		$set = $metas[ $i ];
		foreach ( $keys as $key ) {
			update_post_meta( $post_id, $bki . $key, $set[ $key ] );
		}
	}
}
