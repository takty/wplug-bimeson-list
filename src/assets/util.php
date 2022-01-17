<?php
/**
 * Utilities for Bimeson List
 *
 * @package Wplug Bimeson List
 * @author Takuto Yanagida
 * @version 2022-01-17
 */

namespace wplug\bimeson_list;

/**
 * Check the current post type.
 *
 * @param string $post_type Post type.
 * @return bool True if the current is the given post type.
 */
function is_post_type( string $post_type ): bool {
	$post_id = get_post_id();
	$pt      = get_post_type_in_admin( $post_id );
	return $post_type === $pt;
}

/**
 * Retrieves the post ID.
 *
 * @return int Post ID.
 */
function get_post_id(): int {
	$post_id = '';
	if ( isset( $_GET['post'] ) || isset( $_POST['post_ID'] ) ) {  // phpcs:ignore
		$post_id = isset( $_GET['post'] ) ? $_GET['post'] : $_POST['post_ID'];  // phpcs:ignore
	}
	return (int) $post_id;
}

/**
 * Check the current post type in admin screen.
 *
 * @param int $post_id Post ID.
 * @return string Post type.
 */
function get_post_type_in_admin( int $post_id ): string {
	$p = get_post( $post_id );
	if ( null === $p ) {
		return $_GET['post_type'] ?? '';  // phpcs:ignore
	}
	return $p->post_type;
}
