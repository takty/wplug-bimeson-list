<?php
/**
 * Template Admin
 *
 * @package Wplug Bimeson List
 * @author Takuto Yanagida
 * @version 2024-05-26
 */

declare(strict_types=1);

namespace wplug\bimeson_list;

require_once __DIR__ . '/inst.php';
require_once __DIR__ . '/taxonomy.php';

/**
 * Retrieves the config of a template admin.
 *
 * @param int $post_id Post ID.
 * @return array{
 *     list_id           : int,
 *     year_bgn          : int,
 *     year_end          : int,
 *     count             : int,
 *     filter_state      : array<string, string[]>,
 *     sort_by_date_first: bool,
 *     dup_multi_cat     : bool,
 *     show_filter       : bool,
 *     omit_single_cat   : bool,
 * } Config.
 */
function get_template_admin_config( int $post_id ): array {
	$inst = _get_instance();
	$cfg  = array();
	$temp = array();

	$val = get_post_meta( $post_id, $inst->fld_list_cfg, true );
	if ( is_string( $val ) && ! empty( $val ) ) {
		$t = json_decode( $val, true );
		if ( is_array( $t ) ) {
			$temp = $t;
		}
	}
	// phpcs:disable
	$cfg['list_id']  = isset( $temp['list_id'] )  && is_numeric( $temp['list_id'] )  ? (int) $temp['list_id']  : 0;  // Bimeson List.
	$cfg['year_bgn'] = isset( $temp['year_bgn'] ) && is_numeric( $temp['year_bgn'] ) ? (int) $temp['year_bgn'] : 0;
	$cfg['year_end'] = isset( $temp['year_end'] ) && is_numeric( $temp['year_end'] ) ? (int) $temp['year_end'] : 0;
	$cfg['count']    = isset( $temp['count'] )    && is_numeric( $temp['count'] )    ? (int) $temp['count']    : 0;
	/**
	 * @var array<string, string[]> $filter_state
	 */
	$filter_state = isset( $temp['filter_state'] ) && is_array( $temp['filter_state'] ) ? $temp['filter_state'] : array();
	$cfg['filter_state'] = $filter_state;

	$cfg['sort_by_date_first'] = isset( $temp['sort_by_date_first'] ) && false !== $temp['sort_by_date_first'];
	$cfg['dup_multi_cat']      = isset( $temp['dup_multi_cat'] )      && false !== $temp['dup_multi_cat'];
	$cfg['show_filter']        = isset( $temp['show_filter'] )        && false !== $temp['show_filter'];
	$cfg['omit_single_cat']    = isset( $temp['omit_single_cat'] )    && false !== $temp['omit_single_cat'];
	// phpcs: enable
	return $cfg;
}


// -----------------------------------------------------------------------------


/**
 * Adds the meta box.
 *
 * @param string      $title  Title of the meta box.
 * @param string|null $screen (Optional) The screen or screens on which to show the box.
 */
function add_meta_box_template_admin( string $title, ?string $screen = null ): void {
	\add_meta_box( 'wplug_bimeson_list_admin_mb', $title, '\wplug\bimeson_list\_cb_output_html_template_admin', $screen );
}

/**
 * Stores the data of the meta box.
 *
 * @param int $post_id Post ID.
 */
function save_meta_box_template_admin( int $post_id ): void {
	$nonce = $_POST['wplug_bimeson_admin_nonce'] ?? null;  // phpcs:ignore
	if ( ! is_string( $nonce ) ) {
		return;
	}
	if ( false === wp_verify_nonce( sanitize_key( $nonce ), 'wplug_bimeson_admin' ) ) {
		return;
	}
	$inst = _get_instance();
	$cfg  = array();

	// phpcs:disable
	$cfg['year_bgn'] = ! isset( $_POST['wplug_bimeson_year_bgn'] ) || ! is_string( $_POST['wplug_bimeson_year_bgn'] ) ? null : (int) $_POST['wplug_bimeson_year_bgn'];
	$cfg['year_end'] = ! isset( $_POST['wplug_bimeson_year_end'] ) || ! is_string( $_POST['wplug_bimeson_year_end'] ) ? null : (int) $_POST['wplug_bimeson_year_end'];
	$cfg['count']    = ! isset( $_POST['wplug_bimeson_count'] )    || ! is_string( $_POST['wplug_bimeson_count'] )    ? null : (int) $_POST['wplug_bimeson_count'];
	// phpcs:enable

	if ( is_int( $cfg['year_bgn'] ) ) {
		$cfg['year_bgn'] = ( 1970 <= $cfg['year_bgn'] && $cfg['year_bgn'] <= 3000 ) ? $cfg['year_bgn'] : 0;
	}
	if ( is_int( $cfg['year_end'] ) ) {
		$cfg['year_end'] = ( 1970 <= $cfg['year_end'] && $cfg['year_end'] <= 3000 ) ? $cfg['year_end'] : 0;
	}
	if ( is_int( $cfg['count'] ) ) {
		$cfg['count'] = max( 0, min( 9999, $cfg['count'] ) );
	}
	if ( is_int( $cfg['year_bgn'] ) && is_int( $cfg['year_end'] ) && $cfg['year_end'] < $cfg['year_bgn'] ) {
		list( $cfg['year_bgn'], $cfg['year_end'] ) = array( $cfg['year_end'], $cfg['year_bgn'] );
	}
	// phpcs:disabled
	$cfg['sort_by_date_first'] = is_string( $_POST['wplug_bimeson_sort_by_date_first'] ?? null ) && '' !== $_POST['wplug_bimeson_sort_by_date_first'];
	$cfg['dup_multi_cat']      = is_string( $_POST['wplug_bimeson_dup_multi_cat']      ?? null ) && '' !== $_POST['wplug_bimeson_dup_multi_cat'];
	$cfg['show_filter']        = is_string( $_POST['wplug_bimeson_show_filter']        ?? null ) && '' !== $_POST['wplug_bimeson_show_filter'];
	$cfg['omit_single_cat']    = is_string( $_POST['wplug_bimeson_omit_single_cat']    ?? null ) && '' !== $_POST['wplug_bimeson_omit_single_cat'];
	// phpcs:enabled

	$cfg['filter_state'] = _get_filter_state_from_env();
	$cfg['list_id']      = ( ! is_string( $_POST['wplug_bimeson_list_id'] ?? null ) || '' === $_POST['wplug_bimeson_list_id'] ) ? null : intval( $_POST['wplug_bimeson_list_id'] );  // Bimeson List.

	$json = wp_json_encode( $cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	if ( is_string( $json ) ) {
		// Because the meta value is passed through the stripslashes() function upon being stored.
		update_post_meta( $post_id, $inst->fld_list_cfg, addslashes( $json ) );

		// For backward compatibility.
		_delete_old_post_meta( $post_id );
	}
}

/**
 * Callback function for 'add_meta_box'.
 *
 * @access private
 *
 * @param \WP_Post $post Current post.
 */
function _cb_output_html_template_admin( \WP_Post $post ): void {
	wp_nonce_field( 'wplug_bimeson_admin', 'wplug_bimeson_admin_nonce' );
	$cfg = get_template_admin_config( $post->ID );

	// phpcs:disable
	$list_id            = $cfg['list_id'];  // Bimeson List.
	$year_bgn           = $cfg['year_bgn'] ? (string) $cfg['year_bgn'] : '';
	$year_end           = $cfg['year_end'] ? (string) $cfg['year_end'] : '';
	$count              = $cfg['count']    ? (string) $cfg['count']    : '';
	$sort_by_date_first = $cfg['sort_by_date_first'];
	$dup_multi_cat      = $cfg['dup_multi_cat'];
	$show_filter        = $cfg['show_filter'];
	$omit_single_cat    = $cfg['omit_single_cat'];
	$state              = $cfg['filter_state'];
	// phpcs:enable
	?>
	<div class="wplug-bimeson-admin">
		<div class="wplug-bimeson-admin-config-row">
			<?php _echo_list_select( $list_id );  // Bimeson List. ?>
		</div>
		<div class="wplug-bimeson-admin-config-row">
			<label>
				<?php esc_html_e( 'Target period (years):', 'wplug_bimeson_list' ); ?>
				<input type="number" size="4" name="wplug_bimeson_year_bgn" value="<?php echo esc_attr( $year_bgn ); ?>">
				<span>-</span>
				<input type="number" size="4" name="wplug_bimeson_year_end" value="<?php echo esc_attr( $year_end ); ?>">
			</label>
		</div>
		<div class="wplug-bimeson-admin-config-row">
			<label>
				<?php esc_html_e( 'Specify the number of items to be displayed:', 'wplug_bimeson_list' ); ?>
				<input type="number" size="4" name="wplug_bimeson_count" value="<?php echo esc_attr( $count ); ?>">
			</label>
			&nbsp;<span><?php esc_html_e( '(If specified, the heading will be hidden)', 'wplug_bimeson_list' ); ?></span>
		</div>
		<div class="wplug-bimeson-admin-config-cbs">
			<label>
				<input type="checkbox" name="wplug_bimeson_sort_by_date_first"<?php echo $sort_by_date_first ? ' checked' : ''; // phpcs:ignore ?>>
				<?php esc_html_e( 'Sort by date first', 'wplug_bimeson_list' ); ?>
			</label>
			<label>
				<input type="checkbox" name="wplug_bimeson_dup_multi_cat"<?php echo $dup_multi_cat ? ' checked' : ''; // phpcs:ignore ?>>
				<?php esc_html_e( 'Duplicate multi-category items', 'wplug_bimeson_list' ); ?>
			</label>
			<label>
				<input type="checkbox" name="wplug_bimeson_show_filter"<?php echo $show_filter ? ' checked' : ''; // phpcs:ignore ?>>
				<?php esc_html_e( 'Show filter', 'wplug_bimeson_list' ); ?>
			</label>
			<label>
				<input type="checkbox" name="wplug_bimeson_omit_single_cat"<?php echo $omit_single_cat ? ' checked' : ''; // phpcs:ignore ?>>
				<?php esc_html_e( 'Omit headings for categories with only one item', 'wplug_bimeson_list' ); ?>
			</label>
		</div>
		<div class="wplug-bimeson-admin-filter-row">
			<?php _echo_filter( $state ); ?>
		</div>
	</div>
	<?php
}

/**
 * Displays the markup of list select.
 *
 * @access private
 * @psalm-suppress ArgumentTypeCoercion
 *
 * @param int $cur_id Current list ID.
 */
function _echo_list_select( int $cur_id ): void {
	$inst = _get_instance();
	/**
	 * Posts. This is determined by $args['fields'] being ''.
	 *
	 * @var \WP_Post[] $its
	 */
	$its = get_posts(
		array(
			'post_type'      => $inst::PT,  // @phpstan-ignore-line
			'posts_per_page' => -1,
		)
	);
	?>
	<label>
		<?php esc_html_e( 'Used publication list:', 'wplug_bimeson_list' ); ?>
		<select name="wplug_bimeson_list_id">
	<?php
	foreach ( $its as $it ) {
		$title = $it->post_title;
		if ( empty( $title ) ) {
			$title = '#' . $it->ID;
		}
		echo '<option value="' . esc_attr( (string) $it->ID ) . '" ' . selected( $cur_id, $it->ID, false ) . '>' . esc_html( $title ) . '</option>';
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
 * @param array<string, mixed> $state Filter states.
 */
function _echo_filter( array $state ): void {
	$opts = get_root_slug_to_options();
	foreach ( get_root_slug_to_sub_terms() as $rs => $terms ) {
		_echo_tax_checkboxes_admin( $rs, $terms, $state, $opts[ $rs ]['is_hidden'] );
	}
}

/**
 * Displays the markups of taxonomy checkboxes for admin screen.
 *
 * @access private
 *
 * @param string               $root_slug Root slug.
 * @param \WP_Term[]           $terms     Sub terms.
 * @param array<string, mixed> $state     Filter states.
 * @param bool                 $is_hidden Whether the root term is hidden.
 */
function _echo_tax_checkboxes_admin( string $root_slug, array $terms, array $state, bool $is_hidden ): void {
	$inst = _get_instance();
	$func = $inst->term_name_getter;
	if ( ! is_callable( $func ) ) {
		$func = function ( \WP_Term $t ): string {
			return $t->name;
		};
	}
	$t         = get_term_by( 'slug', $root_slug, $inst->root_tax );
	$cat_label = ( $t instanceof \WP_Term ) ? $func( $t ) : '';
	$slug      = $root_slug;
	$sub_tax   = root_term_to_sub_tax( $root_slug );
	$qvs       = isset( $state[ $root_slug ] ) && is_array( $state[ $root_slug ] ) ? $state[ $root_slug ] : array();
	$checked   = ( ! empty( $qvs ) ) ? ' checked' : '';

	$vc = ' checked';
	if ( isset( $state[ $inst::KEY_VISIBLE ] ) && is_array( $state[ $inst::KEY_VISIBLE ] ) ) {  // @phpstan-ignore-line
		$visible = in_array( $root_slug, $state[ (string) $inst::KEY_VISIBLE ], true );  // @phpstan-ignore-line
		if ( ! $visible ) {
			$vc = '';
		}
	}
	$ih_attr = $is_hidden ? ' disabled' : '';
	?>
	<div class="wplug-bimeson-admin-filter-key" data-key="<?php echo esc_attr( $slug ); ?>">
		<div class="wplug-bimeson-admin-filter-key-inner">
			<div>
				<input type="checkbox" class="wplug-bimeson-admin-filter-switch tgl tgl-light" id="<?php echo esc_attr( $slug ); ?>" name="<?php echo esc_attr( $sub_tax ); ?>"<?php echo $checked; // phpcs:ignore ?>>
				<label class="tgl-btn" for="<?php echo esc_attr( $slug ); ?>"></label>
				<span class="wplug-bimeson-admin-filter-cat"><label for="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $cat_label ); ?></label></span>
				<label<?php echo esc_attr( $ih_attr ); ?>>
					<input type="checkbox" class="wplug-bimeson-admin-filter-visible" name="wplug_bimeson_visible[]"<?php echo $vc; // phpcs:ignore ?> value="<?php echo esc_attr( $slug ); ?>">
					<?php esc_html_e( 'Visible' ); ?>
				</label>
			</div>
			<div class="wplug-bimeson-admin-filter-cbs">
	<?php
	foreach ( $terms as $t ) :
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
 * @return array<string, mixed> Filter states.
 */
function _get_filter_state_from_env(): array {
	$ret = array();

	foreach ( get_root_slug_to_sub_slugs() as $rs => $slugs ) {
		$sub_tax = root_term_to_sub_tax( $rs );
		if ( ! isset( $_POST[ $sub_tax ] ) || ! isset( $_POST[ "{$sub_tax}_slugs" ] ) ) {  // phpcs:ignore
			continue;
		}
		$ss = $_POST[ "{$sub_tax}_slugs" ];  // phpcs:ignore
		if ( is_array( $ss ) ) {
			$ret[ $rs ] = array_values( array_intersect( $slugs, wp_unslash( $ss ) ) );
		}
	}
	$inst = _get_instance();
	$vs   = $_POST['wplug_bimeson_visible'] ?? array();  // phpcs:ignore
	if ( is_array( $vs ) ) {
		$ret[ (string) $inst::KEY_VISIBLE ] = array_values( array_intersect( get_root_slugs(), wp_unslash( $vs ) ) );  // @phpstan-ignore-line
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
function _delete_old_post_meta( int $post_id ): void {
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
