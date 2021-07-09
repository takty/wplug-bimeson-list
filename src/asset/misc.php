<?php
/**
 * Miscellaneous for Admin
 *
 * @author Takuto Yanagida
 * @version 2021-07-08
 */

namespace wplug\bimeson_list;

function get_post_id() {
	$post_id = '';
	if ( isset( $_GET['post'] ) || isset( $_POST['post_ID'] ) ) {
		$post_id = isset( $_GET['post'] ) ? $_GET['post'] : $_POST['post_ID'];
	}
	return intval( $post_id );
}

function get_post_type_in_admin( $post_id ) {
	$p = get_post( $post_id );
	if ( $p === null ) {
		if ( isset( $_GET['post_type'] ) ) return $_GET['post_type'];
		return '';
	}
	return $p->post_type;
}

function is_post_type( $post_type ) {
	$post_id = get_post_id();
	$pt = get_post_type_in_admin( $post_id );
	return $post_type === $pt;
}
