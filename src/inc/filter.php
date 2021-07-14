<?php
/**
 * Bimeson (Filter)
 *
 * @author Takuto Yanagida
 * @version 2021-07-14
 */

namespace wplug\bimeson_list;

function initialize_filter() {
	add_filter( 'query_vars', '\wplug\bimeson_list\_cb_query_vars_filter' );
}

function _cb_query_vars_filter( $query_vars ) {
	$inst = _get_instance();
	$query_vars[] = $inst::QVAR_YEAR;
	return $query_vars;
}


// -------------------------------------------------------------------------


function echo_filter( $filter_state, $years_exist ) {
	$slug_to_terms = get_root_slug_to_sub_terms( false, true );

	if ( is_admin() ) {
		global $post;
		$state = get_filter_state_from_meta( $post );
	} else {
		$state = get_filter_state_from_qvar();
	}
	if ( ! empty( $years_exist ) ) _echo_year_select( $years_exist, $state );

	echo '<div class="bimeson-filter">';
	if ( $filter_state === false ) {
		foreach ( $slug_to_terms as $slug => $terms ) {
			_echo_tax_checkboxes( $slug, $terms, $state, false );
		}
	} else {
		foreach ( $slug_to_terms as $slug => $terms ) {
			$fsset = isset( $filter_state[ $slug ] );
			if ( ! $fsset || 1 < count( $filter_state[ $slug ] ) ) {
				_echo_tax_checkboxes( $slug, $terms, $state, $fsset ? $filter_state[ $slug ] : false );
			}
		}
	}
	echo '</div>';
}

function _echo_year_select( $years, $state ) {
	$inst = _get_instance();
	$val = $state[ $inst::KEY_YEAR ];
?>
	<div class="bimeson-filter-key" data-key="<?php echo $inst::KEY_YEAR ?>">
		<div class="bimeson-filter-key-inner">
			<select name="<?php echo $inst::KEY_YEAR ?>" class="bimeson-filter-select">
				<option value="<?php echo $inst::VAL_YEAR_ALL ?>"><?php echo esc_html_x( 'Select year', 'filter', 'bimeson_list' ) ?></option>
<?php
	foreach ( $years as $y ) {
		if ( is_string( $inst->year_format ) ) {
			$_label = esc_html(sprintf( $inst->year_format, (int) $y ) );
		} else {
			$_label = esc_html( $y );
		}
		echo "<option value=\"$y\"" . ( ( (int) $y === (int) $val ) ? ' selected' : '' ) . ">$_label</option>";
	}
?>
			</select>
		</div>
	</div>
<?php
}

function _echo_tax_checkboxes( $root_slug, $terms, $state, $filtered ) {
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
	<div class="bimeson-filter-key" data-key="<?php echo $_slug ?>">
		<div class="bimeson-filter-key-inner">
			<input type="checkbox" class="bimeson-filter-switch tgl tgl-light" id="<?php echo $_slug ?>" name="<?php echo $_slug ?>" <?php if ( ! empty( $qvals ) ) echo 'checked' ?> value="1"></input>
			<label class="tgl-btn" for="<?php echo $_slug ?>"></label>
			<div class="bimeson-filter-cbs">
				<div class="bimeson-filter-cat"><?php echo $_cat_name ?></div>
<?php
	foreach ( $terms as $t ) :
		$_name = esc_attr( sub_term_to_id( $t ) );
		$_val  = esc_attr( $t->slug );
		if ( $filtered !== false && ! in_array( $t->slug, $filtered, true ) ) continue;
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


// -------------------------------------------------------------------------


function get_filter_state_from_meta( $post ) {
	$inst = _get_instance();
	$slug_to_terms = get_root_slug_to_sub_terms();
	$ret = json_decode( get_post_meta( $post->ID, $inst->FLD_JSON_PARAMS, true ), true );

	foreach ( $slug_to_terms as $slug => $terms ) {
		if ( ! isset( $ret[ $slug ] ) ) $ret[ $slug ] = [];
	}
	return $ret;
}

function get_filter_state_from_qvar() {
	$inst = _get_instance();
	$slug_to_terms = get_root_slug_to_sub_terms();
	$ret = [];

	foreach ( $slug_to_terms as $slug => $terms ) {
		$val = get_query_var( get_query_var_name( $slug ) );
		$temp = empty( $val ) ? [] : explode( ',', $val );
		$ret[ $slug ] = $temp;
	}
	$val = get_query_var( $inst::QVAR_YEAR );
	$ret[ $inst::KEY_YEAR ] = $val;
	return $ret;
}

function get_filter_state_from_post() {
	$slug_to_terms = get_root_slug_to_sub_terms();
	$ret = [];

	foreach ( $slug_to_terms as $slug => $terms ) {
		if ( ! isset( $_POST[ $slug ] ) ) continue;
		$temp = [];

		foreach ( $terms as $t ) {
			$id  = sub_term_to_id( $t );
			$val = isset( $_POST[ $id ] ) ? $_POST[ $id ] : false;
			if ( $val ) $temp[] = $t->slug;
		}
		$ret[ $slug ] = $temp;
	}
	return $ret;
}
