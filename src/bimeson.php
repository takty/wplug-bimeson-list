<?php
/**
 * Functions and Definitions for Bimeson
 *
 * @author Takuto Yanagida
 * @version 2021-07-09
 */

namespace wplug\bimeson_list;

require_once __DIR__ . '/asset/misc.php';
require_once __DIR__ . '/asset/field.php';
require_once __DIR__ . '/asset/url.php';
require_once __DIR__ . '/admin.php';
require_once __DIR__ . '/taxonomy.php';
require_once __DIR__ . '/list.php';

class Bimeson {

	const FLD_BODY       = '_body';
	const FLD_DATE       = '_date';
	const FLD_DOI        = '_doi';
	const FLD_LINK_URL   = '_link_url';
	const FLD_LINK_TITLE = '_link_title';

	const FLD_DATE_NUM   = '_date_num';
	const FLD_CAT_KEY    = '_cat_key';
	const FLD_INDEX      = '_index';

	static private $_instance = null;
	static public function get_instance() {
		if ( self::$_instance === null ) self::$_instance = new Bimeson();
		return self::$_instance;
	}

	private $_key;
	private $_tax        = null;
	private $_admin      = null;
	private $_list       = null;
	private $_head_level = 2;

	private function __construct() {}

	public function initialize( $key, $taxonomy = false, $sub_tax_base = false, $is_fiscal_year = false ) {
		$this->_key  = $key;
		$this->_tax  = new Bimeson_Taxonomy( Bimeson_List::PT, [ 'taxonomy' => '分類', 'omit_last_cat' => '一番最後の分類を省略', 'hide_from_view' => '閲覧画面から隠す', 'year_selector' => 'Fiscal Year' ], $taxonomy, $sub_tax_base );
		$this->_list = new Bimeson_List( $this->_tax );

		$this->_add_shortcodes();
		if ( is_admin() ) $this->_admin = new Bimeson_Admin( $this, $this->_tax );

		if ( $is_fiscal_year ) {
			$this->_tax->set_year_formatter( function ( $y ) {
				if ( class_exists( '\st\Multilang' ) ) {
					$sl = \st\Multilang::get_instance()->get_site_lang();
					if ( $sl === 'ja' ) {
						return $y . '年度';
					} else {
						return 'FY ' . $y;
					}
				} else {
					return $y;
				}
			} );
		}
	}

	public function set_heading_level( $level ) {
		$this->_head_level = $level;
	}

	private function _add_shortcodes() {
		add_shortcode( 'publication', function ( $atts, $content = null ) {
			global $post;
			ob_start();
			$this->_echo_filter_list( $post->ID );
			$ret = ob_get_contents();
			ob_end_clean();
			return $ret;
		} );
	}


	// -------------------------------------------------------------------------

	public function enqueue_script( $url_to = false ) {
		if ( $url_to === false ) $url_to = get_file_uri( __DIR__ );
		$url_to = untrailingslashit( $url_to );

		if ( is_admin() ) {
			$this->_admin->enqueue_script( $url_to );
		} else {
			wp_enqueue_style(  'bimeson', $url_to . '/asset/css/filter.min.css' );
			wp_enqueue_script( 'bimeson', $url_to . '/asset/js/filter.min.js' );
		}
	}

	public function get_taxonomy() {
		return $this->_tax->get_taxonomy();
	}

	public function get_sub_taxonomies() {
		return $this->_tax->get_sub_taxonomies();
	}

	public function add_meta_box( $label, $screen ) {
		$this->_admin->add_meta_box( $label, $screen );
	}

	public function save_meta_box( $post_id ) {
		$this->_admin->save_meta_box( $post_id );
	}

	public function the_list_section() {
		global $post;
?>
		<section>
			<div class="section-inner">
				<div class="entry-content">
					<?php $this->_echo_filter_list( $post->ID ); ?>
				</div>
			</div>
		</section>
<?php
	}


	// -------------------------------------------------------------------------

	private function _echo_filter_list( $post_id ) {
		$filter_state       = json_decode( get_post_meta( $post_id, Bimeson_Admin::FLD_JSON_PARAMS, true ), true );
		$sort_by_year_first = get_post_meta( $post_id, Bimeson_Admin::FLD_SORT_BY_DATE_FIRST, true ) === 'true';
		$show_filter        = get_post_meta( $post_id, Bimeson_Admin::FLD_SHOW_FILTER, true ) === 'true';
		$omit_single_cat    = get_post_meta( $post_id, Bimeson_Admin::FLD_OMIT_HEAD_OF_SINGLE_CAT, true ) === 'true';
		$temp               = get_post_meta( $post_id, Bimeson_Admin::FLD_COUNT, true );
		$count              = ( empty( $temp ) || (int) $temp < 1 ) ? false : (int) $temp;
		$temp               = get_post_meta( $post_id, Bimeson_Admin::FLD_YEAR_START, true );
		$year_start         = ( empty( $temp ) || (int) $temp < 1970 || (int) $temp > 3000 ) ? false : (int) $temp;
		$temp               = get_post_meta( $post_id, Bimeson_Admin::FLD_YEAR_END, true );
		$year_end           = ( empty( $temp ) || (int) $temp < 1970 || (int) $temp > 3000 ) ? false : (int) $temp;

		$items = $this->_get_list_items( $post_id );
		$years_exist = [];
		if ( is_array( $items ) ) {
			$this->_sort_list_items( $items, $sort_by_year_first );
			if ( $filter_state !== false ) {
				$items = $this->_filter_list_items( $items, $filter_state, $year_start, $year_end, $count, $years_exist );
			}
		}
		if ( $show_filter ) {
			echo '<div class="bimeson-filter">';
			$this->_tax->the_filter( $filter_state, $years_exist );
			echo '</div>';
		}
		echo '<div class="bimeson-content stile">';
		if ( is_array( $items ) ) {
			if ( $count === false ) {
				$omitted_heading = $omit_single_cat ? $this->_make_omitted_heading( $filter_state ) : false;
				$this->_the_list( $items, $sort_by_year_first, $omitted_heading );
			} else {
				$this->_echo_list( $items, \st\Multilang::get_instance()->get_site_lang() );
			}
		}
		echo '</div>';
	}


	// -------------------------------------------------------------------------

	private function _get_list_items( $post_id ) {
		$list_id = get_post_meta( $post_id, Bimeson_Admin::FLD_LIST_ID, true );
		$items_json = get_post_meta( $list_id, Bimeson_List::FLD_ITEMS, true );
		return json_decode( $items_json, true );
	}

	private function _sort_list_items( array &$items, $sort_by_year_first ) {
		$this->_sort_by_year_first = $sort_by_year_first;

		$rs_to_slugs       = $this->_tax->get_root_slugs_to_sub_slugs();
		$rs_to_depths      = $this->_tax->get_root_slugs_to_sub_depths();
		$slug_to_ancestors = $this->_tax->get_sub_slug_to_ancestors();

		foreach ( $items as $idx => &$it ) {
			$it[ self::FLD_CAT_KEY ] = $this->_make_cat_key( $it, $rs_to_slugs, $rs_to_depths, $slug_to_ancestors );
			$it[ self::FLD_INDEX ]   = $idx;
		}
		usort( $items, [ $this, '_cb_compare_item' ] );
	}

	private function _make_cat_key( $item, $rs_to_slugs, $rs_to_depths, $slug_to_ancestors ) {
		$cats = [];
		foreach ( $rs_to_slugs as $rs => $slugs ) {
			if ( ! isset( $rs_to_depths[ $rs ] ) ) continue;
			$depth = $rs_to_depths[ $rs ];

			list( $idx, $s ) = $this->_get_one_of_ordered_terms( $item, $rs, $slugs );
			if ( $s ) {
				if ( isset( $slug_to_ancestors[ $s ] ) ) {
					$cats = array_merge( $cats, $slug_to_ancestors[ $s ] );
				}
				$cats[] = $s;
				$depth -= count( $cats );
			}
			for ( $i = 0; $i < $depth; $i += 1 ) $cats[] = '';
		}
		return implode( ',', $cats );
	}

	private function _cb_compare_item( $a, $b ) {
		if ( $this->_sort_by_year_first !== false ) {
			if ( isset( $a[ self::FLD_DATE_NUM ] ) && isset( $b[ self::FLD_DATE_NUM ] ) && $a[ self::FLD_DATE_NUM ] !== $b[ self::FLD_DATE_NUM ] ) {
				return $a[ self::FLD_DATE_NUM ] < $b[ self::FLD_DATE_NUM ] ? 1 : -1;
			}
		}
		$rs_to_slugs = $this->_tax->get_root_slugs_to_sub_slugs();

		$idx = 0;
		foreach ( $rs_to_slugs as $rs => $slugs ) {
			list( $ai, $av ) = $this->_get_one_of_ordered_terms( $a, $rs, $slugs );
			list( $bi, $bv ) = $this->_get_one_of_ordered_terms( $b, $rs, $slugs );
			if ( $av === null && $bv === null) continue;

			if ( $ai === -1 && $bi === -1) {
				$c = strcmp( $av, $bv );
				if ( $c !== 0 ) return $c;
			} else {
				if ( $ai < $bi ) return -1;
				if ( $ai > $bi ) return 1;
			}
		}
		if ( isset( $a[ self::FLD_DATE_NUM ] ) && isset( $b[ self::FLD_DATE_NUM ] ) && $a[ self::FLD_DATE_NUM ] !== $b[ self::FLD_DATE_NUM ] ) {
			return $a[ self::FLD_DATE_NUM ] < $b[ self::FLD_DATE_NUM ] ? 1 : -1;
		}
		return $a[ self::FLD_INDEX ] < $b[ self::FLD_INDEX ] ? -1 : 1;
	}

	private function _get_one_of_ordered_terms( $item, $rs, $sub_slugs ) {
		if ( ! isset( $item[ $rs ] ) ) return [ -1, null ];
		$vs = $item[ $rs ];
		foreach ( $sub_slugs as $idx => $s ) {
			if ( in_array( $s, $vs, true ) ) return [ $idx, $s ];
		}
		return [ -1, null ];
	}


	// -------------------------------------------------------------------------

	private function _filter_list_items( $items, $filter_state, $year_start, $year_end, $count, &$years_exist ) {
		$ret = [];
		$years = [];
		$sl = \st\Multilang::get_instance()->get_site_lang();

		$do_year_filter = ( $year_start !== false || $year_end !== false );
		if ( $year_start === false ) $year_start = 0;
		else $year_start = (int) str_pad( $year_start . '', 8, '0', STR_PAD_RIGHT );
		if ( $year_end === false ) $year_end = intval( '99999999' );
		else $year_end = (int) str_pad( $year_end . '', 8, '9', STR_PAD_RIGHT );

		foreach ( $items as $item ) {
			if ( $do_year_filter ) {
				if ( ! isset( $item[ self::FLD_DATE_NUM ] ) ) continue;  // next item
				$date = (int) $item[ self::FLD_DATE_NUM ];
				if ( $date < $year_start || $year_end < $date ) continue;  // next item
			}
			foreach ( $filter_state as $rs => $slugs ) {
				if ( ! isset( $item[ $rs ] ) ) continue 2;  // next item
				$vs = $item[ $rs ];
				foreach ( $slugs as $s ) {
					if ( in_array( $s, $vs, true ) ) continue 2;  // next condition
				}
				continue 2;  // next item
			}

			if ( isset( $item[ self::FLD_BODY . "_$sl" ] ) && ! empty( $item[ self::FLD_BODY . "_$sl" ] ) ) {
			} else if ( isset( $item[ self::FLD_BODY ] ) && ! empty( $item[ self::FLD_BODY ] ) ) {
			} else {
				continue;  // next item
			}
			$ret[] = $item;
			if ( isset( $item[ self::FLD_DATE_NUM ] ) ) {
				$years[ substr( $item[ self::FLD_DATE_NUM ], 0, 4 ) ] = 1;
			}
			if ( $count !== false && count( $ret ) === $count ) break;
		}
		foreach ( $years as $idx => $val ) $years_exist[] = $idx;
		sort( $years_exist );
		return $ret;
	}


	// -------------------------------------------------------------------------

	private function _make_omitted_heading( $filter_state ) {
		if ( $filter_state === false ) return false;
		$ret = [];
		foreach ( $this->_tax->get_root_slugs() as $rs ) {
			$ret[ $rs ] = false;
		}
		if ( $filter_state !== false ) {
			foreach ( $filter_state as $rs => $s ) {
				if ( count( $s ) === 1 ) $ret[ $rs ] = true;
			}
		}
		return $ret;
	}

	private function _the_list( $items, $sort_by_year_first, $omitted_heading ) {
		$sl = \st\Multilang::get_instance()->get_site_lang();
		$rss = $this->_tax->get_root_slugs();
		$rs_to_depth  = $this->_tax->get_root_slugs_to_sub_depths();

		$last_cat_depth = $rs_to_depth[ $rss[ count( $rss ) - 1 ] ];

		$hier_size_orig = 0;
		$hier_to_rs = [];
		foreach ( $rs_to_depth as $rs => $d ) {
			$hier_size_orig += $d;
			for ( $i = 0; $i < $d; $i++ ) $hier_to_rs[] = $rs;
		}

		$slug_to_last_omit = $this->_tax->get_slug_to_last_omit();
		$buf = [];
		$prev_cat_key = array_pad( [], $hier_size_orig, '' );

		$cur_year = '';
		foreach ( $items as $item ) {
			if ( $sort_by_year_first && isset( $item[ self::FLD_DATE_NUM ] ) ) {
				$year = substr( $item[ self::FLD_DATE_NUM ], 0, 4 );
				if ( $cur_year !== $year ) {
					if ( ! empty( $buf ) ) {
						$this->_echo_list( $buf, $sl );
						$buf = [];
					}
					$this->_echo_heading_year( 2, $item[ self::FLD_DATE_NUM ] );
					$cur_year = $year;
				}
			}
			$cat_key = $this->_get_cat_key( $item, $hier_size_orig );
			$hier = -1;
			$hier_size = $hier_size_orig;

			for ( $h = 0; $h < $hier_size_orig; $h++ ) {
				$key = $cat_key[ $h ];
				if ( isset( $slug_to_last_omit[ $key ] ) ) {
					$hier_size = $hier_size_orig - $last_cat_depth;
				}
				if ( $key !== $prev_cat_key[ $h ] ) {
					$hier = $h;
					break;
				}
			}
			if ( $hier !== -1 && $hier < $hier_size ) {
				$is_cat_exist = false;
				for ( $h = $hier; $h < $hier_size; $h++ ) {
					if ( ! empty( $cat_key[ $h ] ) ) {
						$is_cat_exist = $this->_is_cat_exist( $hier_to_rs[ $h ], $cat_key[ $h ] );
						if ( $is_cat_exist ) break;
					}
				}
				if ( $is_cat_exist && ! empty( $buf ) ) {
					$this->_echo_list( $buf, $sl );
					$buf = [];
				}
				for ( $h = $hier; $h < $hier_size; $h++ ) {
					if ( ! empty( $cat_key[ $h ] ) ) {
						if ( $omitted_heading === false || ! $omitted_heading[ $hier_to_rs[ $h ] ] ) {
							$this->_echo_heading( $h, $sort_by_year_first ? 1 : 0, $hier_to_rs[ $h ], $cat_key[ $h ] );
						}
					}
				}
			}
			$prev_cat_key = $cat_key;  // Clone!
			$item['__catkey__'] = implode( ',', $cat_key );
			$buf[] = $item;
		}
		if ( ! empty( $buf ) ) $this->_echo_list( $buf, $sl );
	}

	private function _get_cat_key( $item, $hier_size ) {
		$cat_key = explode( ',', $item[ self::FLD_CAT_KEY ] );
		if ( count( $cat_key ) !== $hier_size ) {  // for invalid 'sortkey'
			$cat_key = array_pad( $cat_key, $hier_size, '' );
		}
		return $cat_key;
	}

	private function _is_cat_exist( $root_slug, $slug ) {
		$t = get_term_by( 'slug', $slug, $this->_tax->term_to_taxonomy( $root_slug ) );
		if ( ! $t ) return false;
		return true;
	}

	private function _echo_heading( $hier, $sort_by_year_first, $root_slug, $slug ) {
		$t = get_term_by( 'slug', $slug, $this->_tax->term_to_taxonomy( $root_slug ) );
		if ( $t ) {
			if ( class_exists( '\st\Multilang' ) ) {
				$_name = esc_html( \st\Multilang::get_instance()->get_term_name( $t ) );
			} else {
				$_name = esc_html( $t->name );
			}
		} else {
			$_name = esc_html( is_numeric( $slug ) ? $slug : "[$slug]" );
		}
		$level = $hier + $this->_head_level + ( $sort_by_year_first ? 1 : 0 );
		$tag = ( $level <= 6 ) ? "h$level" : 'div';
		$data_depth = $level - 1;
		echo "<$tag class=\"$root_slug\" data-depth=\"$data_depth\">$_name</$tag>\n";
	}

	private function _echo_heading_year( $level, $date_num ) {
		$year = substr( $date_num, 0, 4 );
		if ( class_exists( '\st\Multilang' ) ) {
			$date = date_create_from_format( 'Y', $year );
			$_name = esc_html( date_format( $date, \st\Multilang::get_instance()->get_date_format( 'year' ) ) );
		} else {
			$_name = esc_html( $year );
		}
		$tag = ( $level <= 6 ) ? "h$level" : 'div';
		$data_depth = $level - 1;
		echo "<$tag class=\"year\" data-depth=\"$data_depth\">$_name</$tag>\n";
	}

	private function _echo_list( $items, $sl ) {
		if ( count( $items ) === 1 ) {
			echo "<ul>\n";
			foreach ( $items as $it ) $this->_echo_list_item( $it, $sl );
			echo "</ul>\n";
		} else {
			echo "<ol>\n";
			foreach ( $items as $it ) $this->_echo_list_item( $it, $sl );
			echo "</ol>\n";
		}
	}

	private function _echo_list_item( $item, $sl ) {
		$body = '';
		if ( isset( $item[ self::FLD_BODY . "_$sl" ] ) && ! empty( $item[ self::FLD_BODY . "_$sl" ] ) ) {
			$body = $item[ self::FLD_BODY . "_$sl" ];
		} else if ( isset( $item[ self::FLD_BODY ] ) && ! empty( $item[ self::FLD_BODY ] ) ) {
			$body = $item[ self::FLD_BODY ];
		} else {
			return;
		}
		$doi    = isset( $item[ self::FLD_DOI ] )        ? $item[ self::FLD_DOI ]        : '';
		$lurl   = isset( $item[ self::FLD_LINK_URL ] )   ? $item[ self::FLD_LINK_URL ]   : '';
		$ltitle = isset( $item[ self::FLD_LINK_TITLE ] ) ? $item[ self::FLD_LINK_TITLE ] : '';

		$_link = '';
		if ( ! empty( $lurl ) ) {
			$_url   = esc_url( $lurl );
			$_title = empty( $ltitle ) ? $_url : esc_html( $ltitle );
			$_link  = "<div class=\"link\"><a href=\"$_url\">$_title</a></div>";
		}
		$_doi = '';
		if ( ! empty( $doi ) ) {
			$_url   = esc_url( "https://doi.org/$doi" );
			$_title = esc_html( $doi );
			$_doi   = "<div class=\"doi\">DOI: <a href=\"$_url\">$_title</a></div>";
		}

		$_cls = esc_attr( implode( ' ', $this->_make_cls_array( $item ) ) );
		$_catkey = isset( $item['__catkey__'] ) ? esc_attr( $item['__catkey__'] ) : '';

		if ( isset( $item[ self::FLD_DATE_NUM ] ) ) {
			$_year = esc_attr( substr( '' . $item[ self::FLD_DATE_NUM ], 0, 4 ) );
		} else {
			$_year = '';
		}

		echo "<li class=\"$_cls\" data-year=\"$_year\" data-catkey=\"$_catkey\"><div>";
		echo "$body";
		echo "$_link$_doi</div></li>\n";
	}

	private function _make_cls_array( $item ) {
		$cs = [];
		$tax = str_replace( '_', '-', $this->_tax->get_taxonomy() );
		foreach ( $item as $key => $val ) {
			if ( $key[0] === '_' ) continue;
			foreach ( $val as $v ) $cs[] = "$tax-$key-$v";
		}
		return $cs;
	}

}
