<?php
/**
 * Bimeson (Post Type)
 *
 * @package Wplug Bimeson List
 * @author Takuto Yanagida
 * @version 2021-07-20
 */

namespace wplug\bimeson_list;

require_once __DIR__ . '/../assets/media-picker.php';

function initialize_post_type( string $url_to ) {
	$inst = _get_instance();
	register_post_type( $inst::PT, [
		'label'         => __( 'Publication List', 'bimeson_list' ),
		'labels'        => [],
		'public'        => true,
		'show_ui'       => true,
		'menu_position' => 5,
		'menu_icon'     => 'dashicons-analytics',
		'has_archive'   => false,
		'rewrite'       => false,
		'supports'      => [ 'title' ],
	] );

	if ( is_admin() && _is_the_post_type() ) {
		add_action( 'admin_enqueue_scripts', function () use ( $url_to ) {
			wp_enqueue_style(  'bimeson_list_post_type', $url_to . '/assets/css/post-type.min.css' );
			wp_enqueue_script( 'bimeson_list_post_type', $url_to . '/assets/js/post-type.min.js' );
			wp_enqueue_script( 'xlsx',                   $url_to . '/assets/js/xlsx.full.min.js' );

			$pid = get_post_id();
			wp_enqueue_media( [ 'post' => ( $pid === 0 ? null : $pid ) ] );
			MediaPicker::enqueue_script( $url_to . '/assets/' );
		} );
		add_action( 'admin_menu',             '\wplug\bimeson_list\_cb_admin_menu_post_type' );
		add_action( 'save_post_' . $inst::PT, '\wplug\bimeson_list\_cb_save_post_post_type' );
		$inst->media_picker = new MediaPicker( $inst::FLD_MEDIA );
	}
}


// -----------------------------------------------------------------------------


function _cb_admin_menu_post_type() {
	$inst = _get_instance();
	if ( ! is_post_type( $inst::PT ) ) return;
	\add_meta_box( 'bimeson_mb', __( 'Publication List', 'bimeson_list' ), '\wplug\bimeson_list\_cb_output_html_post_type', $inst::PT, 'normal', 'high' );
}

function _cb_output_html_post_type() {
	$inst = _get_instance();
	wp_nonce_field( 'bimeson_list', 'bimeson_list_nonce' );
	$inst->media_picker->set_title_editable( false );
	$inst->media_picker->output_html();
?>
	<div>
		<div class="bimeson-list-edit-row">
			<label>
				<input type="checkbox" name="<?php echo $inst::FLD_ADD_TAX ?>" value="true">
				<?php esc_html_e( 'Add category groups themselves', 'bimeson_list' ); ?>
			</label>
			<label>
				<input type="checkbox" name="<?php echo $inst::FLD_ADD_TERM ?>" value="true">
				<?php esc_html_e( 'Add categories to the category group', 'bimeson_list' ); ?>
			</label>
			<div>
				<span class="bimeson-list-loading-spin"><span></span></span>
				<button class="bimeson-list-filter-button button button-primary button-large">
					<?php esc_html_e( 'Update List', 'bimeson_list' ); ?>
				</button>
			</div>
		</div>
		<input type="hidden" id="<?php echo $inst::FLD_ITEMS ?>" name="<?php echo $inst::FLD_ITEMS ?>" value="<?php echo esc_attr( $inst::NOT_MODIFIED ) ?>" />
	</div>
<?php
}

function _cb_save_post_post_type( int $post_id ) {
	$inst = _get_instance();
	if ( ! isset( $_POST['bimeson_list_nonce'] ) || ! wp_verify_nonce( $_POST['bimeson_list_nonce'], 'bimeson_list' ) ) return;
	if ( ! current_user_can( 'edit_post', $post_id ) ) return;
	$inst->media_picker->save_items( $post_id );

	$json_items = $_POST[ $inst::FLD_ITEMS ];
	if ( $json_items !== $inst::NOT_MODIFIED ) {
		$items = json_decode( stripslashes( $json_items ), true );

		$add_tax  = isset( $_POST[ $inst::FLD_ADD_TAX  ] ) && ( $_POST[ $inst::FLD_ADD_TAX  ] === 'true' );
		$add_term = isset( $_POST[ $inst::FLD_ADD_TERM ] ) && ( $_POST[ $inst::FLD_ADD_TERM ] === 'true' );
		if ( $add_tax || $add_term ) process_terms( $items, $add_tax, $add_term );

		_process_items( $items );
		$json_items = json_encode( $items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		update_post_meta( $post_id, $inst::FLD_ITEMS, addslashes( $json_items ) );  // Because the meta value is passed through the stripslashes() function upon being stored.
	}
}


// -----------------------------------------------------------------------------


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
