<?php
/**
 * Post Type
 *
 * @package Wplug Bimeson List
 * @author Takuto Yanagida
 * @version 2023-11-13
 */

declare(strict_types=1);

namespace wplug\bimeson_list;

require_once __DIR__ . '/../bimeson.php';
require_once __DIR__ . '/../assets/admin-current-post.php';
require_once __DIR__ . '/../assets/date-field.php';
require_once __DIR__ . '/class-mediapicker.php';
require_once __DIR__ . '/inst.php';
require_once __DIR__ . '/taxonomy.php';

/**
 * Initializes the post type.
 *
 * @param string $url_to Base URL.
 */
function initialize_post_type( string $url_to ): void {
	$inst = _get_instance();
	register_post_type(
		$inst::PT,  // @phpstan-ignore-line
		array(
			'label'         => __( 'Publication List', 'wplug_bimeson_list' ),
			'labels'        => array(
				'add_new'      => __( 'Add New Publication List', 'wplug_bimeson_item' ),
				'add_new_item' => __( 'Add New Publication List', 'wplug_bimeson_item' ),
			),
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
				wp_enqueue_script( 'xlsx', $url_to . '/assets/js/sheetjs/xlsx.full.min.js', array(), '1.0', false );

				wp_localize_script(
					'wplug-bimeson-list-post-type',
					'translation',
					array(
						'alert_msg' => __( '“Update list” before clicking “Publish/Update” button.', 'wplug_bimeson_list' ),
					)
				);

				$pid = \wplug\get_admin_post_id();
				wp_enqueue_media( 0 === $pid ? array() : array( 'post' => $pid ) );
				MediaPicker::enqueue_script( $url_to . '/assets/' );
			}
		);
		add_action( 'admin_menu', '\wplug\bimeson_list\_cb_admin_menu_post_type', 10, 0 );
		add_filter( 'wp_insert_post_data', '\wplug\bimeson_list\_cb_insert_post_data', 99, 2 );
		add_action( 'save_post_' . $inst::PT, '\wplug\bimeson_list\_cb_save_post_post_type', 10, 1 );  // @phpstan-ignore-line
		$inst->media_picker = new MediaPicker( $inst::FLD_MEDIA );  // @phpstan-ignore-line
	}
}


// -----------------------------------------------------------------------------


/**
 * Callback function for 'admin_menu' action.
 *
 * @access private
 */
function _cb_admin_menu_post_type(): void {
	$inst = _get_instance();
	if ( ! \wplug\is_admin_post_type( $inst::PT ) ) {  // @phpstan-ignore-line
		return;
	}
	\add_meta_box( 'wplug_bimeson_list_mb', __( 'Publication List', 'wplug_bimeson_list' ), '\wplug\bimeson_list\_cb_output_html_post_type', $inst::PT, 'normal', 'high' );  // @phpstan-ignore-line
}

/**
 * Callback function for the meta box.
 *
 * @access private
 */
function _cb_output_html_post_type(): void {
	$inst = _get_instance();
	wp_nonce_field( 'wplug_bimeson_list', 'wplug_bimeson_list_nonce' );
	if ( $inst->media_picker instanceof MediaPicker ) {
		$inst->media_picker->set_title_editable( false );
		$inst->media_picker->output_html();
	}
	?>
	<div>
		<div class="wplug-bimeson-list-edit-row">
			<label>
				<input type="checkbox" name="<?php echo esc_attr( $inst::FLD_ADD_TAX );  // @phpstan-ignore-line ?>" value="true">
				<?php esc_html_e( 'Add category groups themselves', 'wplug_bimeson_list' ); ?>
			</label>
			<label>
				<input type="checkbox" name="<?php echo esc_attr( $inst::FLD_ADD_TERM );  // @phpstan-ignore-line ?>" value="true">
				<?php esc_html_e( 'Add categories to the category group', 'wplug_bimeson_list' ); ?>
			</label>
			<div>
				<span class="wplug-bimeson-list-loading-spin"><span></span></span>
				<button class="wplug-bimeson-list-update-button button button-primary button-large">
					<?php esc_html_e( 'Update List', 'wplug_bimeson_list' ); ?>
				</button>
			</div>
		</div>
		<input type="hidden" id="<?php echo esc_attr( $inst::FLD_ITEMS );  // @phpstan-ignore-line ?>" name="<?php echo esc_attr( $inst::FLD_ITEMS );  // @phpstan-ignore-line ?>" value="<?php echo esc_attr( $inst::NOT_MODIFIED );  // @phpstan-ignore-line ?>">
	</div>
	<?php
}

/**
 * Callback function for 'wp_insert_post_data' filter.
 *
 * @access private
 *
 * @param array<string, mixed> $data   An array of slashed, sanitized, and processed post data.
 * @param array<string, mixed> $post_a An array of sanitized (and slashed) but otherwise unmodified post data.
 * @return array<string, mixed> Filtered post data.
 */
function _cb_insert_post_data( array $data, array $post_a ): array {
	$inst = _get_instance();
	if ( $post_a['post_type'] === $inst::PT ) {  // @phpstan-ignore-line
		if ( empty( $post_a['post_title'] ) && ! empty( $post_a[ $inst::FLD_MEDIA . '_0_title' ] ) ) {  // @phpstan-ignore-line
			$data['post_title'] = $post_a[ $inst::FLD_MEDIA . '_0_title' ];  // @phpstan-ignore-line
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
function _cb_save_post_post_type( int $post_id ): void {
	$nonce = $_POST['wplug_bimeson_list_nonce'] ?? null;  // phpcs:ignore
	if ( ! is_string( $nonce ) ) {
		return;
	}
	if ( ! wp_verify_nonce( sanitize_key( $nonce ), 'wplug_bimeson_list' ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$inst = _get_instance();
	if ( $inst->media_picker instanceof MediaPicker ) {
		$inst->media_picker->save_items( $post_id );
	}

	$json_items = $inst::NOT_MODIFIED;  // @phpstan-ignore-line
	if ( is_string( $_POST[ $inst::FLD_ITEMS ] ?? null ) ) {  // @phpstan-ignore-line
		// @phpstan-ignore-next-line
		$json_items = wp_unslash( $_POST[ $inst::FLD_ITEMS ] );  // phpcs:ignore
	}
	if ( $json_items !== $inst::NOT_MODIFIED && is_string( $json_items ) ) {  // @phpstan-ignore-line
		$items = json_decode( $json_items, true );
		if ( ! is_array( $items ) ) {
			delete_post_meta( $post_id, $inst::FLD_ITEMS );  // @phpstan-ignore-line
			return;
		}
		$add_tax  = isset( $_POST[ $inst::FLD_ADD_TAX ] ) && ( 'true' === $_POST[ $inst::FLD_ADD_TAX ] );  // @phpstan-ignore-line
		$add_term = isset( $_POST[ $inst::FLD_ADD_TERM ] ) && ( 'true' === $_POST[ $inst::FLD_ADD_TERM ] );  // @phpstan-ignore-line
		if ( $add_tax || $add_term ) {
			process_terms( $items, $add_tax, $add_term );
		}
		_process_items( $items );
		$json_items = wp_json_encode( $items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		if ( false !== $json_items ) {
			// Because the meta value is passed through the stripslashes() function upon being stored.
			update_post_meta( $post_id, $inst::FLD_ITEMS, addslashes( $json_items ) );  // @phpstan-ignore-line
		}
	}
}


// -----------------------------------------------------------------------------


/**
 * Processes an array of items.
 *
 * @access private
 * @psalm-suppress UndefinedFunction
 *
 * @param array<string, mixed>[] $items Items.
 */
function _process_items( array &$items ): void {
	$inst = _get_instance();
	foreach ( $items as &$it ) {
		$date = ( ! empty( $it[ $inst::IT_DATE ] ) ) ? \wplug\normalize_date( $it[ $inst::IT_DATE ] ) : '';  // @phpstan-ignore-line
		if ( $date ) {
			$it[ $inst::IT_DATE_NUM ] = \wplug\create_date_number( $date );  // @phpstan-ignore-line
		}
		unset( $it[ $inst::IT_DATE ] );  // @phpstan-ignore-line
	}
}
