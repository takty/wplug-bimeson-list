<?php
/**
 * Media Picker
 *
 * @package Wplug Bimeson List
 * @author Takuto Yanagida
 * @version 2024-03-22
 */

declare(strict_types=1);

namespace wplug\bimeson_list;

require_once __DIR__ . '/../assets/multiple.php';

/**
 * Media picker.
 */
class MediaPicker {

	const NS = 'wplug-bimeson-list-media-picker';

	// Admin.
	const CLS_BODY         = self::NS . '-body';
	const CLS_TABLE        = self::NS . '-table';
	const CLS_ITEM         = self::NS . '-item';
	const CLS_ITEM_TEMP    = self::NS . '-item-template';
	const CLS_ITEM_IR      = self::NS . '-item-inside-row';
	const CLS_HANDLE       = self::NS . '-handle';
	const CLS_ADD_ROW      = self::NS . '-add-row';
	const CLS_ADD          = self::NS . '-add';
	const CLS_DEL_LAB      = self::NS . '-delete-label';
	const CLS_DEL          = self::NS . '-delete';
	const CLS_SEL          = self::NS . '-select';
	const CLS_MEDIA_OPENER = self::NS . '-media-opener';

	const CLS_MEDIA      = self::NS . '-media';
	const CLS_URL        = self::NS . '-url';
	const CLS_TITLE      = self::NS . '-title';
	const CLS_FILENAME   = self::NS . '-filename';
	const CLS_H_FILENAME = self::NS . '-h-filename';

	/**
	 * Instances.
	 *
	 * @var MediaPicker[]
	 */
	private static $instance = array();

	/**
	 * Retrieves an instance.
	 *
	 * @psalm-suppress PossiblyUnusedMethod
	 *
	 * @param string|null $key Key of instance.
	 * @return MediaPicker|null The instance.
	 */
	public static function get_instance( ?string $key ): ?MediaPicker {
		if ( is_null( $key ) ) {
			if ( ! empty( self::$instance ) ) {
				return reset( self::$instance );
			}
			return null;
		}
		if ( isset( self::$instance[ $key ] ) ) {
			return self::$instance[ $key ];
		}
		return new MediaPicker( $key );
	}

	/**
	 * Registers the scripts.
	 *
	 * @psalm-suppress PossiblyUnusedMethod
	 *
	 * @param string $url_to Base URL.
	 */
	public static function enqueue_script( string $url_to ): void {
		if ( is_admin() ) {
			$url_to = untrailingslashit( $url_to );
			wp_enqueue_script( 'picker-media', $url_to . '/js/picker-media.min.js', array(), '1.0', true );
			wp_enqueue_script( self::NS, $url_to . '/js/media-picker.min.js', array( 'picker-media', 'jquery-ui-sortable' ), '1.0', false );
			wp_enqueue_style( self::NS, $url_to . '/css/media-picker.min.css', array(), '1.0' );
		}
	}

	/**
	 * The base key of input.
	 *
	 * @var string
	 */
	private $key;

	/**
	 * The ID of the output markup.
	 *
	 * @var string
	 */
	private $id;

	/**
	 * Whether titles are editable.
	 *
	 * @var bool
	 */
	private $is_title_editable = true;

	/**
	 * Constructor.
	 *
	 * @param string $key The base key of input.
	 */
	public function __construct( string $key ) {
		$this->key = $key;
		$this->id  = $key;

		self::$instance[ $key ] = $this;
	}

	/**
	 * Sets whether titles are editable.
	 *
	 * @psalm-suppress PossiblyUnusedMethod
	 *
	 * @param bool $flag Whether titles are editable.
	 * @return MediaPicker This instance.
	 */
	public function set_title_editable( bool $flag ): MediaPicker {
		$this->is_title_editable = $flag;
		return $this;
	}

	/**
	 * Retrieves selected media items.
	 *
	 * @param int|null $post_id Post ID.
	 * @return array<string, mixed>[] Media items.
	 */
	public function get_items( ?int $post_id = null ): array {
		if ( is_null( $post_id ) ) {
			$post_id = get_the_ID();
			if ( ! is_int( $post_id ) ) {
				return array();
			}
		}
		$keys = array( 'media', 'url', 'title', 'filename', 'id' );
		$its  = \wplug\get_multiple_post_meta( $post_id, $this->key, $keys );
		return $its;
	}


	// -------------------------------------------------------------------------


	/**
	 * Adds the meta box.
	 *
	 * @psalm-suppress PossiblyUnusedMethod
	 *
	 * @param string                     $title   Title of the meta box.
	 * @param string|null                $screen  (Optional) The screen or screens on which to show the box.
	 * @param 'advanced'|'normal'|'side' $context (Optional) The context within the screen where the box should display.
	 */
	public function add_meta_box( string $title, ?string $screen, string $context = 'advanced' ): void {
		\add_meta_box( "{$this->key}_mb", $title, array( $this, 'cb_output_html' ), $screen, $context );
	}

	/**
	 * Stores the data of the meta box.
	 *
	 * @psalm-suppress PossiblyUnusedMethod
	 *
	 * @param int $post_id Post ID.
	 */
	public function save_meta_box( int $post_id ): void {
		$nonce = $_POST[ "{$this->key}_nonce" ] ?? null;  // phpcs:ignore
		if ( ! is_string( $nonce ) ) {
			return;
		}
		if ( false === wp_verify_nonce( sanitize_key( $nonce ), $this->key ) ) {
			return;
		}
		if ( ! isset( $_POST[ $this->key ] ) ) {  // phpcs:ignore
			return;  // Do not save before JS is executed.
		}
		$this->save_items( $post_id );
	}


	// -------------------------------------------------------------------------


	/**
	 * Callback function for 'add_meta_box'.
	 *
	 * @access private
	 *
	 * @param \WP_Post $post Doc.
	 */
	public function cb_output_html( \WP_Post $post ): void {
		wp_nonce_field( $this->key, "{$this->key}_nonce" );
		$this->output_html( $post->ID );
	}

	/**
	 * Display meta box markup.
	 *
	 * @param int|null $post_id Post ID.
	 */
	public function output_html( ?int $post_id = null ): void {
		$its = $this->get_items( $post_id );
		?>
		<input type="hidden" name="<?php echo esc_attr( $this->id ); ?>" id="<?php echo esc_attr( $this->id ); ?>" value="">
		<div class="<?php echo esc_attr( self::CLS_BODY ); ?>">
			<div class="<?php echo esc_attr( self::CLS_TABLE ); ?>">
		<?php
		$this->output_row( array(), self::CLS_ITEM_TEMP );
		foreach ( $its as $it ) {
			$this->output_row( $it, self::CLS_ITEM );
		}
		?>
				<div class="<?php echo esc_attr( self::CLS_ADD_ROW ); ?>"><a href="javascript:void(0);" class="<?php echo esc_attr( self::CLS_ADD ); ?> button"><?php esc_html_e( 'Add Media', 'default' ); ?></a></div>
			</div>
			<script>window.addEventListener('load', function () {
				st_media_picker_initialize_admin('<?php echo esc_attr( $this->id ); ?>');
			});</script>
		</div>
		<?php
	}

	const CLS_ITEM_CTRL = self::NS . '-item-ctrl';
	const CLS_ITEM_CONT = self::NS . '-item-cont';

	/**
	 * Outputs the row of items.
	 *
	 * @access private
	 *
	 * @param array<string, mixed> $it  The item.
	 * @param string               $cls CSS class name.
	 */
	public function output_row( array $it, string $cls ): void {
		// phpcs:disable
		$url        = $it['url']       ?? '';
		$media      = $it['media']     ?? '';
		$title      = $it['title']     ?? '';
		$filename   = $it['filename']  ?? '';
		$h_filename = $it['filename']  ?? '';
		// phpcs:enable

		$ro = $this->is_title_editable ? '' : 'readonly="readonly"';
		?>
		<div class="<?php echo esc_attr( $cls ); ?>">
			<div class="<?php echo esc_attr( self::CLS_ITEM_CTRL ); ?>">
				<div class="<?php echo esc_attr( self::CLS_HANDLE ); ?>">=</div>
				<label class="widget-control-remove <?php echo esc_attr( self::CLS_DEL_LAB ); ?>">
					<span><?php esc_html_e( 'Remove', 'default' ); ?></span>
					<input type="checkbox" class="<?php echo esc_attr( self::CLS_DEL ); ?>">
				</label>
			</div>
			<div class="<?php echo esc_attr( self::CLS_ITEM_CONT ); ?>">
				<div class="<?php echo esc_attr( self::CLS_ITEM_IR ); ?>">
					<span><?php esc_html_e( 'Title', 'default' ); ?>:</span>
					<input <?php echo $ro; // phpcs:ignore ?> type="text" class="<?php echo esc_attr( self::CLS_TITLE );  // @phpstan-ignore-line ?>" value="<?php echo esc_attr( $title ); ?>">
				</div>
				<div class="<?php echo esc_attr( self::CLS_ITEM_IR ); ?>">
					<span><a href="javascript:void(0);" class="<?php echo esc_attr( self::CLS_MEDIA_OPENER ); ?>"><?php esc_html_e( 'File name:', 'default' ); ?></a></span>
					<span>
						<span class="<?php echo esc_attr( self::CLS_H_FILENAME );  // @phpstan-ignore-line ?>"><?php echo esc_html( $h_filename ); ?></span>
						<a href="javascript:void(0);" class="button <?php echo esc_attr( self::CLS_SEL ); ?>"><?php esc_html_e( 'Select', 'default' ); ?></a>
					</span>
				</div>
			</div>
			<input type="hidden" class="<?php echo esc_attr( self::CLS_MEDIA );  // @phpstan-ignore-line ?>" value="<?php echo esc_attr( $media ); ?>">
			<input type="hidden" class="<?php echo esc_attr( self::CLS_URL );  // @phpstan-ignore-line ?>" value="<?php echo esc_attr( $url ); ?>">
			<input type="hidden" class="<?php echo esc_attr( self::CLS_FILENAME );  // @phpstan-ignore-line ?>" value="<?php echo esc_attr( $filename ); ?>">
		</div>
		<?php
	}


	// -------------------------------------------------------------------------


	/**
	 * Stores the data of selected media from a post.
	 *
	 * @param int $post_id Post ID.
	 */
	public function save_items( int $post_id ): void {
		$keys = array( 'media', 'url', 'title', 'filename', 'delete' );

		$its = \wplug\get_multiple_post_meta_from_env( $this->key, $keys );
		$its = array_filter(
			$its,
			function ( $it ) {
				return null === $it['delete'] && '' !== $it['url'];
			}
		);
		$its = array_values( $its );

		$keys = array( 'media', 'url', 'title', 'filename' );
		\wplug\set_multiple_post_meta( $post_id, $this->key, $its, $keys );
	}
}
