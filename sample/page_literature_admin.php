<?php
/**
 *
 * Admin for the Template for Literature Static Pages
 *
 * @author Takuto Yanagida
 * @version 2021-07-08
 *
 */


function setup_template_admin() {
	\wplug\bimeson_list\Bimeson::get_instance()->enqueue_script();
	\wplug\bimeson_list\Bimeson::get_instance()->add_meta_box( __( 'Literatures' ), 'page' );

	add_action( 'save_post_page', function ( $post_id ) {
		if ( ! current_user_can( 'edit_post', $post_id ) ) return;
		\wplug\bimeson_list\Bimeson::get_instance()->save_meta_box( $post_id );
	} );
}
