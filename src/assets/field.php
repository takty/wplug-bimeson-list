<?php
/**
 * Custom Field Utilities
 *
 * @package Wplug Bimeson List
 * @author Takuto Yanagida
 * @version 2023-05-18
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

/**
 * Create date number string.
 *
 * @param string $date Date string.
 * @return string Date number string.
 */
function create_date_number( string $date ): string {
	$date = preg_replace( '/[^\p{Nd}|\||-]/u', '', $date );
	if ( '' === $date ) {
		return str_pad( $date, 8, '9', STR_PAD_RIGHT );
	}
	$date = str_replace( array( '-', '/' ), '|', $date );
	$ds   = explode( '|', array_map( 'intval', $ds ) );

	if ( isset( $ds[0] ) && 0 === $ds[0] ) {
		return str_pad( $date, 8, '9', STR_PAD_RIGHT );
	} elseif ( isset( $ds[1] ) && 0 === $ds[1] ) {
		array_splice( $ds, 1 );
	} elseif ( isset( $ds[2] ) && 0 === $ds[2] ) {
		array_splice( $ds, 2 );
	}
	$ds = array_map( 'strval', $ds );

	if ( isset( $ds[0] ) ) {
		$len = strlen( $ds[0] );
		if ( 2 === $len ) {
			$ds[0] = ( ( 40 <= intval( $ds[0] ) ) ? '19' : '20' ) . $ds[0];
		} elseif ( $len < 4 ) {
			$ds[0] = str_pad( $ds[0], 4, '0', STR_PAD_LEFT );
		} elseif ( 4 < $len ) {
			$yl    = substr( $ds[0], 0, 4 );
			$yr    = substr( $ds[0], -4 );
			$ds[0] = ( abs( intval( $yl ) - 2000 ) < abs( intval( $yr ) - 2000 ) ) ? $yl : $yr;
		}
	}
	if ( isset( $ds[1] ) ) {
		$len = strlen( $ds[1] );
		if ( $len < 2 ) {
			$ds[1] = '0' . $ds[1];
		} elseif ( 2 < $len ) {
			$ml    = substr( $ds[1], 0, 2 );
			$mr    = substr( $ds[1], -2 );
			$ds[1] = ( intval( $ml ) < intval( $mr ) ) ? $ml : $mr;
		}
	}
	if ( isset( $ds[2] ) ) {
		$len = strlen( $ds[2] );
		if ( $len < 2 ) {
			$ds[2] = '0' . $ds[2];
		} elseif ( 2 < $len ) {
			$dl    = substr( $ds[2], 0, 2 );
			$dr    = substr( $ds[2], -2 );
			$ds[2] = ( intval( $dl ) < intval( $dr ) ) ? $dl : $dr;
		}
	}
	$date = implode( '', $ds );
	return str_pad( $date, 8, '9', STR_PAD_RIGHT );
}
