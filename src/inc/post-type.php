<?php
/**
 * Bimeson (Post Type)
 *
 * @package Wplug Bimeson List
 * @author Takuto Yanagida
 * @version 2022-05-10
 */

namespace wplug\bimeson_list;

require_once __DIR__ . '/../assets/class-mediapicker.php';

/**
 * Initializes the post type.
 *
 * @param string $url_to Base URL.
 */
function initialize_post_type( string $url_to ) {
	$inst = _get_instance();
	register_post_type(
		$inst::PT,
		array(
			'label'         => __( 'Publication List', 'wplug_bimeson_list' ),
			'labels'        => array(),
			'public'        => true,
			'show_ui'       => true,
			'menu_position' => 5,
			'menu_icon'     => 'dashicons-analytics',
			'has_archive'   => false,
			'rewrite'       => false,
			'supports'      => array( 'title' ),
		)
	);

	if ( is_admin() && _is_the_post_type() ) {
		add_action(
			'admin_enqueue_scripts',
			function () use ( $url_to ) {
				wp_enqueue_style( 'wplug-bimeson-list-post-type', $url_to . '/assets/css/post-type.min.css', array(), '1.0' );
				wp_enqueue_script( 'wplug-bimeson-list-post-type', $url_to . '/assets/js/post-type.min.js', array(), '1.0', false );
				wp_enqueue_script( 'xlsx', $url_to . '/assets/js/xlsx.full.min.js', array(), '1.0', false );

				$pid = get_post_id();
				wp_enqueue_media( array( 'post' => ( 0 === $pid ? null : $pid ) ) );
				MediaPicker::enqueue_script( $url_to . '/assets/' );
			}
		);
		add_action( 'admin_menu', '\wplug\bimeson_list\_cb_admin_menu_post_type' );
		add_filter( 'wp_insert_post_data', '\wplug\bimeson_list\_cb_insert_post_data', 99, 2 );
		add_action( 'save_post_' . $inst::PT, '\wplug\bimeson_list\_cb_save_post_post_type' );
		$inst->media_picker = new MediaPicker( $inst::FLD_MEDIA );
	}
}


// -----------------------------------------------------------------------------


/**
 * Callback function for 'admin_menu' action.
 *
 * @access private
 */
function _cb_admin_menu_post_type() {
	$inst = _get_instance();
	if ( ! is_post_type( $inst::PT ) ) {
		return;
	}
	\add_meta_box( 'wplug_bimeson_list_mb', __( 'Publication List', 'wplug_bimeson_list' ), '\wplug\bimeson_list\_cb_output_html_post_type', $inst::PT, 'normal', 'high' );
}

/**
 * Callback function for the meta box.
 *
 * @access private
 */
function _cb_output_html_post_type() {
	$inst = _get_instance();
	wp_nonce_field( 'wplug_bimeson_list', 'wplug_bimeson_list_nonce' );
	$inst->media_picker->set_title_editable( false );
	$inst->media_picker->output_html();
	?>
	<div>
		<div class="wplug-bimeson-list-edit-row">
			<label>
				<input type="checkbox" name="<?php echo esc_attr( $inst::FLD_ADD_TAX ); ?>" value="true">
				<?php esc_html_e( 'Add category groups themselves', 'wplug_bimeson_list' ); ?>
			</label>
			<label>
				<input type="checkbox" name="<?php echo esc_attr( $inst::FLD_ADD_TERM ); ?>" value="true">
				<?php esc_html_e( 'Add categories to the category group', 'wplug_bimeson_list' ); ?>
			</label>
			<div>
				<span class="wplug-bimeson-list-loading-spin"><span></span></span>
				<button class="wplug-bimeson-list-filter-button button button-primary button-large">
					<?php esc_html_e( 'Update List', 'wplug_bimeson_list' ); ?>
				</button>
			</div>
		</div>
		<input type="hidden" id="<?php echo esc_attr( $inst::FLD_ITEMS ); ?>" name="<?php echo esc_attr( $inst::FLD_ITEMS ); ?>" value="<?php echo esc_attr( $inst::NOT_MODIFIED ); ?>">
	</div>
	<?php
}

/**
 * Callback function for 'wp_insert_post_data' filter.
 *
 * @access private
 *
 * @param array $data   An array of slashed, sanitized, and processed post data.
 * @param array $post_a An array of sanitized (and slashed) but otherwise unmodified post data.
 * @return array Filtered post data.
 */
function _cb_insert_post_data( array $data, array $post_a ): array {
	$inst = _get_instance();
	if ( $post_a['post_type'] === $inst::PT ) {
		if ( empty( $post_a['post_title'] ) && ! empty( $post_a[ $inst::FLD_MEDIA . '_0_title' ] ) ) {
			$data['post_title'] = $post_a[ $inst::FLD_MEDIA . '_0_title' ];
		}
	}
	return $data;
}

/**
 * Callback function for 'save_post_{$post->post_type}' action.
 *
 * @access private
 *
 * @param int $post_id Post ID.
 */
function _cb_save_post_post_type( int $post_id ) {
	$inst = _get_instance();
	if ( ! isset( $_POST['wplug_bimeson_list_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['wplug_bimeson_list_nonce'] ), 'wplug_bimeson_list' ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$inst->media_picker->save_items( $post_id );

	$json_items = $inst::NOT_MODIFIED;
	if ( isset( $_POST[ $inst::FLD_ITEMS ] ) ) {
		$json_items = wp_unslash( $_POST[ $inst::FLD_ITEMS ] );  // phpcs:ignore
	}
	if ( $json_items !== $inst::NOT_MODIFIED ) {
		$items = json_decode( stripslashes( $json_items ), true );

		$add_tax  = isset( $_POST[ $inst::FLD_ADD_TAX ] ) && ( 'true' === $_POST[ $inst::FLD_ADD_TAX ] );
		$add_term = isset( $_POST[ $inst::FLD_ADD_TERM ] ) && ( 'true' === $_POST[ $inst::FLD_ADD_TERM ] );
		if ( $add_tax || $add_term ) {
			process_terms( $items, $add_tax, $add_term );
		}
		_process_items( $items );
		$json_items = wp_json_encode( $items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		update_post_meta( $post_id, $inst::FLD_ITEMS, addslashes( $json_items ) );  // Because the meta value is passed through the stripslashes() function upon being stored.
	}
}


// -----------------------------------------------------------------------------


/**
 * Processes an array of items.
 *
 * @access private
 *
 * @param array $items Items.
 */
function _process_items( array &$items ) {
	$inst = _get_instance();
	foreach ( $items as &$it ) {
		$date = ( ! empty( $it[ $inst::IT_DATE ] ) ) ? normalize_date( $it[ $inst::IT_DATE ] ) : '';
		if ( $date ) {
			$date_num = str_pad( str_replace( '-', '', $date ), 8, '9', STR_PAD_RIGHT );

			$it[ $inst::IT_DATE_NUM ] = $date_num;
		}
		unset( $it[ $inst::IT_DATE ] );
	}
}
