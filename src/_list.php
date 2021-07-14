<?php
/**
 * Bimeson (List)
 *
 * @author Takuto Yanagida
 * @version 2021-07-14
 */

namespace wplug\bimeson_list;

function echo_heading_list_element( $args ) {
	$inst = _get_instance();

	$its              = $args['items']       ?? [];
	$lang             = $args['lang']        ?? '';
	$year_format      = $args['year_format'] ?? null;
	$term_name_getter = $args['term_name_getter'] ?? null;

	$root_slug_to_depth    = get_root_slug_to_sub_depths();
	$sub_slug_to_last_omit = get_sub_slug_to_last_omit();

	$filter_state       = $args['filter_state']       ?? false;
	$sort_by_year_first = $args['sort_by_year_first'] ?? false;
	$head_level         = $args['head_level']         ?? false;

	$last_cat_depth  = $root_slug_to_depth[ array_key_last( $root_slug_to_depth ) ];
	$omitted_heading = _make_omitted_heading( $filter_state );

	$hr_size_orig = 0;
	$hr_to_sub_tax = [];
	foreach ( $root_slug_to_depth as $rs => $d ) {
		$hr_size_orig += $d;
		for ( $i = 0; $i < $d; $i++ ) $hr_to_sub_tax[] = $inst->sub_taxes[ $rs ];
	}
	$prev_cat_slug = array_pad( [], $hr_size_orig, '' );

	$buf = new ItemBuffer( $lang );
	$cur_year = '';
	foreach ( $its as $it ) {
		if ( $sort_by_year_first && isset( $it[ $inst::IT_DATE_NUM ] ) ) {
			$year = substr( $it[ $inst::IT_DATE_NUM ], 0, 4 );
			if ( $cur_year !== $year ) {
				$buf->echo();
				_echo_heading_year( 2, $it[ $inst::IT_DATE_NUM ], $year_format );
				$cur_year = $year;
			}
		}
		$cat_slug = _get_cat_slug( $it, $hr_size_orig );
		$hr = -1;
		$hr_size = $hr_size_orig;

		for ( $h = 0; $h < $hr_size_orig; $h++ ) {
			$key = $cat_slug[ $h ];
			if ( isset( $sub_slug_to_last_omit[ $key ] ) ) {
				$hr_size = $hr_size_orig - $last_cat_depth;
			}
			if ( $key !== $prev_cat_slug[ $h ] ) {
				$hr = $h;
				break;
			}
		}
		if ( $hr !== -1 && $hr < $hr_size ) {
			$is_cat_exist = false;
			for ( $h = $hr; $h < $hr_size; $h++ ) {
				if ( ! empty( $cat_slug[ $h ] ) ) {
					$is_cat_exist = _is_cat_exist( $hr_to_sub_tax[ $h ], $cat_slug[ $h ] );
					if ( $is_cat_exist ) break;
				}
			}
			if ( $is_cat_exist ) $buf->echo();
			for ( $h = $hr; $h < $hr_size; $h++ ) {
				if ( ! empty( $cat_slug[ $h ] ) ) {
					if ( $omitted_heading === false || ! $omitted_heading[ $hr_to_sub_tax[ $h ] ] ) {
						_echo_heading( $h, $head_level, $sort_by_year_first ? 1 : 0, $hr_to_sub_tax[ $h ], $cat_slug[ $h ], $term_name_getter );
					}
				}
			}
		}
		$prev_cat_slug = $cat_slug;  // Clone!
		$it['__cats__'] = implode( ',', $cat_slug );
		$buf->add( $it );
	}
	$buf->echo();
}


// -----------------------------------------------------------------------------


function _make_omitted_heading( $filter_state ) {
	$inst = _get_instance();
	if ( $filter_state === false ) return false;
	$ret = [];
	foreach ( $inst->sub_taxes as $rs => $sub_tax ) {
		$ret[ $sub_tax ] = false;
		$s = $filter_state[ $rs ] ?? [];
		if ( count( $s ) === 1 ) $ret[ $sub_tax ] = true;
	}
	return $ret;
}

function _get_cat_slug( $it, $hr_size ) {
	$inst = _get_instance();
	$cat_slug = explode( ',', $it[ $inst::IT_CAT_KEY ] );
	if ( count( $cat_slug ) !== $hr_size ) {  // for invalid 'sortkey'
		$cat_slug = array_pad( $cat_slug, $hr_size, '' );
	}
	return $cat_slug;
}

function _is_cat_exist( $sub_tax, $slug ) {
	$t = get_term_by( 'slug', $slug, $sub_tax );
	if ( ! $t ) return false;
	return true;
}


// -----------------------------------------------------------------------------


function _echo_heading( $hr, $head_level, $sort_by_year_first, $sub_tax, $slug, $term_name_getter ) {
	$t = get_term_by( 'slug', $slug, $sub_tax );
	if ( $t ) {
		if ( is_callable( $term_name_getter ) ) {
			$_label = esc_html( $term_name_getter( $t ) );
		} else {
			$_label = esc_html( $t->name );
		}
	} else {
		$_label = esc_html( is_numeric( $slug ) ? $slug : "[$slug]" );
	}
	$level = $hr + $head_level + ( $sort_by_year_first ? 1 : 0 );
	$tag   = ( $level <= 6 ) ? "h$level" : 'div';
	$depth = $level - 1;
	echo "<$tag class=\"$slug\" data-depth=\"$depth\">$_label</$tag>\n";
}

function _echo_heading_year( $level, $date_num, $format ) {
	$year = substr( $date_num, 0, 4 );
	if ( is_string( $format ) ) {
		$_label = esc_html( sprintf( $format, (int) $year ) );
	} else {
		$_label = esc_html( $year );
	}
	$tag   = ( $level <= 6 ) ? "h$level" : 'div';
	$depth = $level - 1;
	echo "<$tag class=\"year\" data-depth=\"$depth\">$_label</$tag>\n";
}


// -----------------------------------------------------------------------------


class ItemBuffer {

	private $_items = [];
	private $_lang;

	public function __construct( $lang ) {
		$this->_lang = $lang;
	}

	public function add( $item ) {
		$this->_items[] = $item;
	}

	public function echo() {
		if ( ! empty( $this->_items ) ) {
			echo_list_element( [ 'items' => $this->_items, 'lang' => $this->_lang ] );
			$this->_items = [];
		}
	}

	public function has_item() {
		return ! empty( $this->_items );
	}

}

function echo_list_element( $args ) {
	$its  = $args['items'] ?? [];
	$lang = $args['lang']  ?? '';

	$tag = ( count( $its ) === 1 ) ? 'ul' : 'ol';
	echo "<$tag>\n";
	foreach ( $its as $it ) _echo_list_item( $it, $lang );
	echo "</$tag>\n";
}

function _echo_list_item( $it, $lang ) {
	$inst = _get_instance();

	$body = '';
	if ( ! empty( $lang ) && ! empty( $it[ $inst::IT_BODY . "_$lang" ] ) ) {
		$body = $it[ $inst::IT_BODY . "_$lang" ];
	} else if ( ! empty( $it[ $inst::IT_BODY ] ) ) {
		$body = $it[ $inst::IT_BODY ];
	} else {
		return;
	}
	$_year = '';
	if ( isset( $it[ $inst::IT_DATE_NUM ] ) ) {
		$_year = esc_attr( substr( '' . $it[ $inst::IT_DATE_NUM ], 0, 4 ) );
	}
	$_cls = esc_attr( _make_cls( $it ) );
	$_key = esc_attr( $it['__cats__'] ?? '' );

	$doi   = $it[ $inst::IT_DOI ]        ?? '';
	$url   = $it[ $inst::IT_LINK_URL ]   ?? '';
	$title = $it[ $inst::IT_LINK_TITLE ] ?? '';

	$_doi = '';
	if ( ! empty( $doi ) ) {
		$_url   = esc_url( "https://doi.org/$doi" );
		$_title = esc_html( $doi );
		$_doi   = "<span class=\"doi\">DOI: <a href=\"$_url\">$_title</a></span>";
	}
	$_link = '';
	if ( ! empty( $url ) ) {
		$_url   = esc_url( $url );
		$_title = empty( $title ) ? $_url : esc_html( $title );
		$_link  = "<span class=\"link\"><a href=\"$_url\">$_title</a></span>";
	}

	echo "<li class=\"$_cls\" data-year=\"$_year\" data-catkey=\"$_key\"><div>\n";
	echo "<span class=\"body\">$body</span>$_link$_doi\n";
	echo "</div></li>\n";
}

function _make_cls( $it ) {
	$inst = _get_instance();
	$cs  = [];
	$tax = str_replace( '_', '-', $inst->root_tax );
	foreach ( $it as $key => $val ) {
		if ( $key[0] === '_' ) continue;
		foreach ( $val as $v ) $cs[] = "$tax-$key-$v";
	}
	return implode( ' ', $cs );
}
