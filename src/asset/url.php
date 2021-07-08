<?php
namespace wplug\bimeson_list;
/**
 *
 * URL Utilities
 *
 * @author Takuto Yanagida
 * @version 2021-07-08
 *
 */


function get_file_uri( $path ) {
	$path = wp_normalize_path( $path );

	if ( is_child_theme() ) {
		$theme_path = wp_normalize_path( defined( 'CHILD_THEME_PATH' ) ? CHILD_THEME_PATH : get_stylesheet_directory() );
		$theme_uri  = get_stylesheet_directory_uri();

		// When child theme is used, and libraries exist in the parent theme
		$tlen = strlen( $theme_path );
		$len  = strlen( $path );
		if ( $tlen >= $len || 0 !== strncmp( $theme_path . $path[ $tlen ], $path, $tlen + 1 ) ) {
			$theme_path = wp_normalize_path( defined( 'THEME_PATH' ) ? THEME_PATH : get_template_directory() );
			$theme_uri  = get_template_directory_uri();
		}
		return str_replace( $theme_path, $theme_uri, $path );
	} else {
		$theme_path = wp_normalize_path( defined( 'THEME_PATH' ) ? THEME_PATH : get_stylesheet_directory() );
		$theme_uri  = get_stylesheet_directory_uri();
		return str_replace( $theme_path, $theme_uri, $path );
	}
}

function abs_url( $base, $rel ) {
	if ( parse_url( $rel, PHP_URL_SCHEME ) != '' ) return $rel;
	$base = trailingslashit( $base );
	if ( $rel[0] === '#' || $rel[0] === '?' ) return $base . $rel;

	$pu = parse_url( $base );
	$scheme = isset( $pu['scheme'] ) ? $pu['scheme'] . '://' : '';
	$host   = isset( $pu['host'] )   ? $pu['host']           : '';
	$port   = isset( $pu['port'] )   ? ':' . $pu['port']     : '';
	$path   = isset( $pu['path'] )   ? $pu['path']           : '';

	$path = preg_replace( '#/[^/]*$#', '', $path );
	if ( $rel[0] === '/' ) $path = '';
	$abs = "$host$port$path/$rel";
	$re = [ '#(/\.?/)#', '#/(?!\.\.)[^/]+/\.\./#' ];
	for ( $n = 1; $n > 0; $abs = preg_replace( $re, '/', $abs, -1, $n ) ) {}
	return $scheme . $abs;
}
