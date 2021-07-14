<?php
/**
 * Bimeson (Admin Dest)
 *
 * @author Takuto Yanagida
 * @version 2021-07-14
 */

namespace wplug\bimeson_list;

function enqueue_script_admin_dest( $url_to ) {
	wp_enqueue_style(  'bimeson_list_admin_dest', $url_to . '/assets/css/admin-dest.min.css' );
	wp_enqueue_script( 'bimeson_list_admin_dest', $url_to . '/assets/js/admin-dest.min.js' );
}


// -----------------------------------------------------------------------------


function add_meta_box_admin_dest( $label, $screen ) {
	\add_meta_box( 'bimeson_admin_mb', $label, '\wplug\bimeson_list\_cb_output_html_admin_dest', $screen );
}

function save_meta_box_admin_dest( $post_id ) {
	$inst = _get_instance();
	if ( ! isset( $_POST['bimeson_admin_nonce'] ) ) return;
	if ( ! wp_verify_nonce( $_POST['bimeson_admin_nonce'], 'bimeson_admin' ) ) return;

	$state           = get_filter_state_from_post();
	$group           = empty( $_POST[ $inst->FLD_SORT_BY_DATE_FIRST ] ) ? 'false' : 'true';
	$show_filter     = empty( $_POST[ $inst->FLD_SHOW_FILTER ] ) ? 'false' : 'true';
	$omit_single_cat = empty( $_POST[ $inst->FLD_OMIT_HEAD_OF_SINGLE_CAT ] ) ? 'false' : 'true';

	update_post_meta( $post_id, $inst->FLD_JSON_PARAMS, json_encode( $state ) );
	update_post_meta( $post_id, $inst->FLD_SORT_BY_DATE_FIRST, $group );
	update_post_meta( $post_id, $inst->FLD_SHOW_FILTER, $show_filter );
	update_post_meta( $post_id, $inst->FLD_OMIT_HEAD_OF_SINGLE_CAT, $omit_single_cat );

	save_post_meta( $post_id, $inst->FLD_COUNT );
	save_post_meta( $post_id, $inst->FLD_YEAR_START );
	save_post_meta( $post_id, $inst->FLD_YEAR_END );

	save_post_meta( $post_id, $inst->FLD_LIST_ID );
}

function _cb_output_html_admin_dest( $post ) {
	$inst = _get_instance();
	wp_nonce_field( 'bimeson_admin', 'bimeson_admin_nonce' );

	$list_id         = (int) get_post_meta( $post->ID, $inst->FLD_LIST_ID, true );
	$group           = get_post_meta( $post->ID, $inst->FLD_SORT_BY_DATE_FIRST, true );
	$show_filter     = get_post_meta( $post->ID, $inst->FLD_SHOW_FILTER, true );
	$omit_single_cat = get_post_meta( $post->ID, $inst->FLD_OMIT_HEAD_OF_SINGLE_CAT, true );

	$temp       = get_post_meta( $post->ID, $inst->FLD_COUNT, true );
	$count      = ( empty( $temp ) || (int) $temp < 1 ) ? '' : (int) $temp;
	$temp       = get_post_meta( $post->ID, $inst->FLD_YEAR_START, true );
	$year_start = ( empty( $temp ) || (int) $temp < 1970 || (int) $temp > 3000 ) ? '' : (int) $temp;
	$temp       = get_post_meta( $post->ID, $inst->FLD_YEAR_END, true );
	$year_end   = ( empty( $temp ) || (int) $temp < 1970 || (int) $temp > 3000 ) ? '' : (int) $temp;
?>
	<div class="bimeson-admin">
		<div class="bimeson-admin-setting-row">
			<?php _echo_list_select( $list_id ); ?>
		</div>
		<div class="bimeson-admin-setting-row">
			<label>
				<?php echo _x( 'Target period (years):', 'admin dest', 'bimeson_list' ); ?>
				<input type="number" size="4" name="<?php echo $inst->FLD_YEAR_START ?>" value="<?php echo $year_start ?>">
				<span>-</span>
				<input type="number" size="4" name="<?php echo $inst->FLD_YEAR_END ?>" value="<?php echo $year_end ?>">
			</label>
		</div>
		<div class="bimeson-admin-setting-row">
			<label>
				<?php echo _x( 'Specify the number of items to be displayed:', 'admin dest', 'bimeson_list' ); ?>
				<input type="number" size="4" name="<?php echo $inst->FLD_COUNT ?>" value="<?php echo $count ?>">
			</label>
			&nbsp;<span><?php echo _x( '(If specified, the heading will be hidden)', 'admin dest', 'bimeson_list' ); ?></span>
		</div>
		<div class="bimeson-admin-setting-cbs">
			<label>
				<input type="checkbox" name="<?php echo $inst->FLD_SHOW_FILTER ?>" value="true" <?php checked( $show_filter, 'true' ) ?>>
				<?php echo _x( 'Show filter', 'admin dest', 'bimeson_list' ); ?>
			</label>
			<label>
				<input type="checkbox" name="<?php echo $inst->FLD_SORT_BY_DATE_FIRST ?>" value="true" <?php checked( $group, 'true' ) ?>>
				<?php echo _x( 'Sort by date first', 'admin dest', 'bimeson_list' ); ?>
			</label>
			<label>
				<input type="checkbox" name="<?php echo $inst->FLD_OMIT_HEAD_OF_SINGLE_CAT ?>" value="true" <?php checked( $omit_single_cat, 'true' ) ?>>
				<?php echo _x( 'Omit headings for categories with only one item', 'admin dest', 'bimeson_list' ); ?>
			</label>
		</div>
		<div class="bimeson-admin-filter-row">
			<?php _echo_filter(); ?>
		</div>
	</div>
<?php
}

function _echo_list_select( $cur_id ) {
	$inst = _get_instance();
	$its = get_posts( [
		'post_type'      => $inst::PT,
		'posts_per_page' => -1,
	] );
?>
	<label>
		<?php echo _x( 'Used publication list:', 'admin dest', 'bimeson_list' ); ?>
		<select id="<?php echo $inst->FLD_LIST_ID ?>" name="<?php echo $inst->FLD_LIST_ID ?>">
	</label>
<?php
	foreach ( $its as $it ) {
		$id     = $it->ID;
		$_title = esc_html( $it->post_title );
		if ( empty( $_title ) ) $_title = '#' . esc_html( $id );
		echo "<option value=\"$id\" " . selected( $cur_id, $id, false ) . ">$_title</option>";
	}
?>
	</select>
<?php
}

function _echo_filter() {
	global $post;
	$state = get_filter_state_from_meta( $post );

	$slug_to_terms = get_root_slug_to_sub_terms( false, false );
	foreach ( $slug_to_terms as $slug => $terms ) {
		_echo_tax_checkboxes_admin( $slug, $terms, $state );
	}
}

function _echo_tax_checkboxes_admin( $root_slug, $terms, $state ) {
	$inst = _get_instance();
	$t = get_term_by( 'slug', $root_slug, $inst->root_tax );
	if ( is_callable( $inst->term_name_getter ) ) {
		$_cat_name = esc_html( ( $inst->term_name_getter )( $t ) );
	} else {
		$_cat_name = esc_html( $t->name );
	}
	$_slug = esc_attr( $root_slug );
	$qvals = $state[ $root_slug ];
?>
	<div class="bimeson-admin-filter-key" data-key="<?php echo $_slug ?>">
		<div class="bimeson-admin-filter-key-inner">
			<div>
				<input type="checkbox" class="bimeson-admin-filter-switch tgl tgl-light" id="<?php echo $_slug ?>" name="<?php echo $_slug ?>" <?php if ( ! empty( $qvals ) ) echo 'checked' ?> value="1"></input>
				<label class="tgl-btn" for="<?php echo $_slug ?>"></label>
				<span class="bimeson-admin-filter-cat"><?php echo $_cat_name ?></span>
			</div>
			<div class="bimeson-admin-filter-cbs">
<?php
	foreach ( $terms as $t ) :
		$_name = esc_attr( sub_term_to_id( $t ) );
		$_val  = esc_attr( $t->slug );
		if ( is_callable( $inst->term_name_getter ) ) {
			$_label = esc_html( ( $inst->term_name_getter )( $t ) );
		} else {
			$_label = esc_html( $t->name );
		}
		$checked = in_array( $t->slug, $qvals, true ) ? ' checked' : '';
?>
				<label>
					<input type="checkbox" name="<?php echo $_name ?>"<?php echo $checked; ?> value="<?php echo $_val ?>">
					<?php echo $_label ?>
				</label>
<?php
	endforeach;
?>
			</div>
		</div>
	</div>
<?php
}
