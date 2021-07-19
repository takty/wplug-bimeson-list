<?php
/**
 * Bimeson (Admin Src)
 *
 * @author Takuto Yanagida
 * @version 2021-07-19
 */

namespace wplug\bimeson_list;

require_once __DIR__ . '/../assets/media-picker.php';

function initialize_admin_src( string $url_to ) {
	$inst = _get_instance();
	add_action( 'admin_menu',             '\wplug\bimeson_list\_cb_admin_menu_admin_src' );
	add_action( 'save_post_' . $inst::PT, '\wplug\bimeson_list\_cb_save_post_admin_src' );

	_enqueue_script_admin_src( $url_to );
	$inst->media_picker = new MediaPicker( $inst::FLD_MEDIA );
}

function _enqueue_script_admin_src( string $url_to ) {
	$inst = _get_instance();
	global $pagenow;
	if ( $pagenow !== 'post.php' && $pagenow !== 'post-new.php' ) return;
	if ( ! is_post_type( $inst::PT ) ) return;

	MediaPicker::enqueue_script( $url_to . '/assets/' );
	wp_enqueue_style(  'bimeson_list_admin_src', $url_to . '/assets/css/admin-src.min.css' );
	wp_enqueue_script( 'bimeson_list_admin_src', $url_to . '/assets/js/admin-src.min.js' );
	wp_enqueue_script( 'xlsx',                   $url_to . '/assets/js/xlsx.full.min.js' );

	$pid = get_post_id();
	wp_enqueue_media( [ 'post' => ( $pid === 0 ? null : $pid ) ] );
}


// -----------------------------------------------------------------------------


function _cb_admin_menu_admin_src() {
	$inst = _get_instance();
	if ( ! is_post_type( $inst::PT ) ) return;
	\add_meta_box( 'bimeson_mb', _x( 'Publication List', 'admin src', 'bimeson_list' ), '\wplug\bimeson_list\_cb_output_html_admin_src', $inst::PT, 'normal', 'high' );
}

function _cb_output_html_admin_src() {
	$inst = _get_instance();
	wp_nonce_field( 'bimeson_list', 'bimeson_list_nonce' );
	$inst->media_picker->set_title_editable( false );
	$inst->media_picker->output_html();
?>
	<div>
		<div class="bimeson-list-edit-row">
			<label>
				<input type="checkbox" name="<?php echo $inst::FLD_ADD_TAX ?>" value="true">
				<?php echo _x( 'Add category groups themselves', 'admin src', 'bimeson_list' ); ?>
			</label>
			<label>
				<input type="checkbox" name="<?php echo $inst::FLD_ADD_TERM ?>" value="true">
				<?php echo _x( 'Add categories to the category group', 'admin src', 'bimeson_list' ); ?>
			</label>
			<div>
				<span class="bimeson-list-loading-spin"><span></span></span>
				<button class="bimeson-list-filter-button button button-primary button-large">
					<?php echo _x( 'Update List', 'admin src', 'bimeson_list' ); ?>
				</button>
			</div>
		</div>
		<input type="hidden" id="<?php echo $inst::FLD_ITEMS ?>" name="<?php echo $inst::FLD_ITEMS ?>" value="<?php echo esc_attr( $inst::NOT_MODIFIED ) ?>" />
	</div>
<?php
}

function _cb_save_post_admin_src( int $post_id ) {
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
	foreach ( $items as &$item ) {
		$date = ( ! empty( $item[ $inst::IT_DATE ] ) ) ? normalize_date( $item[ $inst::IT_DATE ] ) : '';
		if ( $date ) {
			$date_num = str_pad( str_replace( '-', '', $date ), 8, '9', STR_PAD_RIGHT );
			$item[ $inst::IT_DATE_NUM ] = $date_num;
		}
		unset( $item[ $inst::IT_DATE ] );
	}
}
