<?php
/**
 * Bimeson (Template Admin)
 *
 * @package Wplug Bimeson List
 * @author Takuto Yanagida
 * @version 2021-08-31
 */

namespace wplug\bimeson_list;

/**
 * Retrieves the config of a template admin.
 *
 * @param int $post_id Post ID.
 * @return ?array Config.
 */
function get_template_admin_config( int $post_id ): ?array {
	$inst     = _get_instance();
	$cfg_json = get_post_meta( $post_id, $inst->fld_list_cfg, true );
	if ( ! empty( $cfg_json ) ) {
		$cfg = json_decode( $cfg_json, true );
		if ( null !== $cfg ) {
			return $cfg;
		}
	}
	return null;
}


// -----------------------------------------------------------------------------


/**
 * Adds the meta box.
 *
 * @param string  $title  Title of the meta box.
 * @param ?string $screen (Optional) The screen or screens on which to show the box.
 */
function add_meta_box_template_admin( string $title, ?string $screen = null ) {
	\add_meta_box( 'bimeson_admin_mb', $title, '\wplug\bimeson_list\_cb_output_html_template_admin', $screen );
}

/**
 * Stores the data of the meta box.
 *
 * @param int $post_id Post ID.
 */
function save_meta_box_template_admin( int $post_id ) {
	if ( ! isset( $_POST['bimeson_admin_nonce'] ) ) {
		return;
	}
	if ( ! wp_verify_nonce( sanitize_key( $_POST['bimeson_admin_nonce'] ), 'bimeson_admin' ) ) {
		return;
	}
	$inst = _get_instance();
	$cfg  = array();

	// phpcs:disable
	$cfg['year_bgn'] = empty( $_POST['_bimeson_year_bgn'] ) ? null : intval( $_POST['_bimeson_year_bgn'] );
	$cfg['year_end'] = empty( $_POST['_bimeson_year_end'] ) ? null : intval( $_POST['_bimeson_year_end'] );
	$cfg['count']    = empty( $_POST['_bimeson_count'] )    ? null : intval( $_POST['_bimeson_count'] );
	// phpcs:enable

	if ( $cfg['year_bgn'] ) {
		$cfg['year_bgn'] = max( 1970, min( 3000, $cfg['year_bgn'] ) );
	}
	if ( $cfg['year_end'] ) {
		$cfg['year_end'] = max( 1970, min( 3000, $cfg['year_end'] ) );
	}
	if ( $cfg['count'] ) {
		$cfg['count'] = max( 1, min( 9999, $cfg['count'] ) );
	}
	if ( is_int( $cfg['year_bgn'] ) && is_int( $cfg['year_end'] ) && $cfg['year_end'] < $cfg['year_bgn'] ) {
		list( $cfg['year_bgn'], $cfg['year_end'] ) = array( $cfg['year_end'], $cfg['year_bgn'] );
	}
	$cfg['sort_by_date_first'] = ! empty( $_POST['_bimeson_sort_by_date_first'] );
	$cfg['dup_multi_cat']      = ! empty( $_POST['_bimeson_dup_multi_cat'] );
	$cfg['show_filter']        = ! empty( $_POST['_bimeson_show_filter'] );
	$cfg['omit_single_cat']    = ! empty( $_POST['_bimeson_omit_single_cat'] );

	$cfg['filter_state'] = _get_filter_state_from_env();
	$cfg['list_id']      = empty( $_POST['_bimeson_list_id'] ) ? null : intval( $_POST['_bimeson_list_id'] );  // Bimeson List.

	$json = wp_json_encode( $cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	// Because the meta value is passed through the stripslashes() function upon being stored.
	update_post_meta( $post_id, $inst->fld_list_cfg, addslashes( $json ) );

	// For backward compatibility.
	_delete_old_post_meta( $post_id );
}

/**
 * Callback function for 'add_meta_box'.
 *
 * @access private
 *
 * @param \WP_Post $post Current post.
 */
function _cb_output_html_template_admin( \WP_Post $post ) {
	$inst = _get_instance();
	wp_nonce_field( 'bimeson_admin', 'bimeson_admin_nonce' );
	$cfg = get_template_admin_config( $post->ID );

	// phpcs:disable
	$year_bgn = $cfg['year_bgn'] ?? null;
	$year_end = $cfg['year_end'] ?? null;
	$count    = $cfg['count']    ?? null;

	$sort_by_date_first = $cfg['sort_by_date_first'] ?? false;
	$dup_multi_cat      = $cfg['dup_multi_cat']      ?? false;
	$show_filter        = $cfg['show_filter']        ?? false;
	$omit_single_cat    = $cfg['omit_single_cat']    ?? false;

	$state   = $cfg['filter_state'] ?? array();
	$list_id = $cfg['list_id']      ?? null;  // Bimeson List.
	// phpcs:enable
	?>
	<div class="bimeson-admin">
		<div class="bimeson-admin-config-row">
			<?php _echo_list_select( $list_id );  // Bimeson List. ?>
		</div>
		<div class="bimeson-admin-config-row">
			<label>
				<?php esc_html_e( 'Target period (years):', 'bimeson_list' ); ?>
				<input type="number" size="4" name="_bimeson_year_bgn" value="<?php echo esc_attr( $year_bgn ); ?>">
				<span>-</span>
				<input type="number" size="4" name="_bimeson_year_end" value="<?php echo esc_attr( $year_end ); ?>">
			</label>
		</div>
		<div class="bimeson-admin-config-row">
			<label>
				<?php esc_html_e( 'Specify the number of items to be displayed:', 'bimeson_list' ); ?>
				<input type="number" size="4" name="_bimeson_count" value="<?php echo esc_attr( $count ); ?>">
			</label>
			&nbsp;<span><?php esc_html_e( '(If specified, the heading will be hidden)', 'bimeson_list' ); ?></span>
		</div>
		<div class="bimeson-admin-config-cbs">
			<label>
				<input type="checkbox" name="_bimeson_sort_by_date_first" value="true" <?php checked( $sort_by_date_first ); ?>>
				<?php esc_html_e( 'Sort by date first', 'bimeson_list' ); ?>
			</label>
			<label>
				<input type="checkbox" name="_bimeson_dup_multi_cat" value="true" <?php checked( $dup_multi_cat ); ?>>
				<?php esc_html_e( 'Duplicate multi-category items', 'bimeson_list' ); ?>
			</label>
			<label>
				<input type="checkbox" name="_bimeson_show_filter" value="true" <?php checked( $show_filter ); ?>>
				<?php esc_html_e( 'Show filter', 'bimeson_list' ); ?>
			</label>
			<label>
				<input type="checkbox" name="_bimeson_omit_single_cat" value="true" <?php checked( $omit_single_cat ); ?>>
				<?php esc_html_e( 'Omit headings for categories with only one item', 'bimeson_list' ); ?>
			</label>
		</div>
		<div class="bimeson-admin-filter-row">
			<?php _echo_filter( $state ); ?>
		</div>
	</div>
	<?php
}

/**
 * Displays the markup of list select.
 *
 * @access private
 *
 * @param ?int $cur_id Current list ID.
 */
function _echo_list_select( ?int $cur_id ) {
	$inst = _get_instance();
	$its  = get_posts(
		array(
			'post_type'      => $inst::PT,
			'posts_per_page' => -1,
		)
	);
	?>
	<label>
		<?php esc_html_e( 'Used publication list:', 'bimeson_list' ); ?>
		<select name="_bimeson_list_id">
	<?php
	foreach ( $its as $it ) {
		$_id    = esc_attr( $it->ID );
		$_title = esc_html( $it->post_title );
		if ( empty( $_title ) ) {
			$_title = '#' . esc_html( $it->ID );
		}
		echo "<option value=\"$_id\" " . selected( $cur_id, $it->ID, false ) . ">$_title</option>";  // phpcs:ignore
	}
	?>
		</select>
	</label>
	<?php
}

/**
 * Displays the filter.
 *
 * @access private
 *
 * @param array $state Filter states.
 */
function _echo_filter( array $state ) {
	foreach ( get_root_slug_to_sub_terms() as $rs => $terms ) {
		_echo_tax_checkboxes_admin( $rs, $terms, $state );
	}
}

/**
 * Displays the markups of taxonomy checkboxes for admin screen.
 *
 * @access private
 *
 * @param string $root_slug Root slug.
 * @param array  $terms     Sub terms.
 * @param array  $state     Filter states.
 */
function _echo_tax_checkboxes_admin( string $root_slug, array $terms, array $state ) {
	$inst = _get_instance();
	$func = $inst->term_name_getter;
	if ( ! is_callable( $func ) ) {
		$func = function ( $t ) {
			return $t->name;
		};
	}
	$t         = get_term_by( 'slug', $root_slug, $inst->root_tax );
	$cat_label = $func( $t );
	$slug      = $root_slug;
	$sub_tax   = root_term_to_sub_tax( $root_slug );
	$qvs       = $state[ $root_slug ] ?? array();
	$checked   = ( ! empty( $qvs ) ) ? ' checked' : '';

	$visible = empty( $state[ $inst::KEY_VISIBLE ] ) ? true : in_array( $root_slug, $state[ $inst::KEY_VISIBLE ], true );
	$vc      = $visible ? ' checked' : '';
	?>
	<div class="bimeson-admin-filter-key" data-key="<?php echo esc_attr( $slug ); ?>">
		<div class="bimeson-admin-filter-key-inner">
			<div>
				<input type="checkbox" class="bimeson-admin-filter-switch tgl tgl-light" id="<?php echo esc_attr( $slug ); ?>" name="<?php echo esc_attr( $sub_tax ); ?>"<?php echo $checked; // phpcs:ignore ?> value="1">
				<label class="tgl-btn" for="<?php echo esc_attr( $slug ); ?>"></label>
				<span class="bimeson-admin-filter-cat"><label for="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $cat_label ); ?></label></span>
				<label>
					<input type="checkbox" class="bimeson-admin-filter-visible" name="_bimeson_visible[]"<?php echo $vc; // phpcs:ignore ?> value="<?php echo esc_attr( $slug ); ?>">
					<?php esc_html_e( 'Visible' ); ?>
				</label>
			</div>
			<div class="bimeson-admin-filter-cbs">
	<?php
	foreach ( $terms as $t ) :
		$name    = sub_term_to_name( $t );
		$val     = $t->slug;
		$label   = $func( $t );
		$checked = in_array( $t->slug, $qvs, true ) ? ' checked' : '';
		?>
				<label>
					<input type="checkbox" name="<?php echo esc_attr( $sub_tax ) . '_slugs[]'; ?>"<?php echo $checked; // phpcs:ignore ?> value="<?php echo esc_attr( $val ); ?>">
					<?php echo esc_html( $label ); ?>
				</label>
		<?php
	endforeach;
	?>
			</div>
		</div>
	</div>
	<?php
}


// -----------------------------------------------------------------------------


/**
 * Retrieves filter states from the environment variable.
 *
 * @access private
 *
 * @return array Filter states.
 */
function _get_filter_state_from_env(): array {
	$ret = array();

	foreach ( get_root_slug_to_sub_slugs() as $rs => $slugs ) {
		$sub_tax = root_term_to_sub_tax( $rs );
		if ( ! isset( $_POST[ $sub_tax ] ) || ! isset( $_POST[ "{$sub_tax}_slugs" ] ) ) {  // phpcs:ignore
			continue;
		}
		$ret[ $rs ] = array_values( array_intersect( $slugs, wp_unslash( $_POST[ "{$sub_tax}_slugs" ] ) ) );  // phpcs:ignore
	}
	$inst = _get_instance();
	if ( isset( $_POST['_bimeson_visible'] ) ) {  // phpcs:ignore
		$ret[ $inst::KEY_VISIBLE ] = array_values( array_intersect( get_root_slugs(), wp_unslash( $_POST['_bimeson_visible'] ) ) );  // phpcs:ignore
	}
	return $ret;
}


// -----------------------------------------------------------------------------


/**
 * Deletes old post meta values.
 *
 * @access private
 *
 * @param int $post_id Post ID.
 */
function _delete_old_post_meta( int $post_id ) {
	delete_post_meta( $post_id, '_bimeson_list_id' );  // Bimeson List.
	delete_post_meta( $post_id, '_bimeson_year_start' );
	delete_post_meta( $post_id, '_bimeson_year_end' );
	delete_post_meta( $post_id, '_bimeson_count' );
	delete_post_meta( $post_id, '_bimeson_sort_by_date_first' );
	delete_post_meta( $post_id, '_bimeson_duplicate_multi_category' );
	delete_post_meta( $post_id, '_bimeson_show_filter' );
	delete_post_meta( $post_id, '_bimeson_omit_head_of_single_cat' );
	delete_post_meta( $post_id, '_bimeson_json_params' );
}
