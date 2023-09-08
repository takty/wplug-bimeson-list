<?php
/**
 * Multiple Post Meta
 *
 * @package Wplug
 * @author Takuto Yanagida
 * @version 2023-09-08
 */

namespace wplug;

if ( ! function_exists( '\wplug\get_multiple_post_meta_from_env' ) ) {
	/**
	 * Retrieve multiple post meta from environ variable $_POST.
	 *
	 * @param string   $base_key Base key of variable names.
	 * @param string[] $keys     Keys of variable names.
	 * @return array<string, mixed>[] The meta values.
	 */
	function get_multiple_post_meta_from_env( string $base_key, array $keys ): array {
		$ret = array();

		// #### For backward compatibility: Input variable structure is {$base_key}_{n}_{$key}.
		if ( isset( $_POST[ $base_key ] ) && is_numeric( $_POST[ $base_key ] ) ) {  // phpcs:ignore
			$count = (int) sanitize_text_field( (string) wp_unslash( $_POST[ $base_key ] ) );  // phpcs:ignore

			for ( $i = 0; $i < $count; ++$i ) {
				$it = array();
				foreach ( $keys as $key ) {
					$k = "{$base_key}_{$i}_{$key}";
					$v = null;
					if ( isset( $_POST[ $k ] ) ) {  // phpcs:ignore
						$v = wp_unslash( $_POST[ $k ] );  // phpcs:ignore
					}
					$it[ $key ] = $v;
				}
				$ret[] = $it;
			}
			return $ret;
		}
		// #### Next best plan: Input variable structure is {$base_key}_{$key}[n].
		if ( ! isset( $_POST[ $base_key ] ) ) {  // phpcs:ignore
			$count = 0;
			if ( isset( $_POST[ "{$base_key}_{$keys[0]}" ] ) ) {  // phpcs:ignore
				$count = count( $_POST[ "{$base_key}_{$keys[0]}" ] );  // phpcs:ignore
			}

			for ( $i = 0; $i < $count; ++$i ) {
				$it = array();
				foreach ( $keys as $key ) {
					$k          = "{$base_key}_{$key}";
					$it[ $key ] = null;
					if ( isset( $_POST[ $k ][ $i ] ) ) {  // phpcs:ignore
						$it[ $key ] = wp_unslash( $_POST[ $k ][ $i ] );  // phpcs:ignore
					}
				}
				$ret[] = $it;
			}
			return $ret;
		}
		// #### Best plan: Input variable structure is {$base_key}[n][{$key}].
		if ( isset( $_POST[ $base_key ] ) && is_array( $_POST[ $base_key ] ) ) {  // phpcs:ignore
			$vals = wp_unslash( $_POST[ $base_key ] );  // phpcs:ignore
			foreach ( $vals as $val ) {
				$it = array();
				foreach ( $keys as $key ) {
					$it[ $key ] = $val[ $key ] ?? null;
				}
				$ret[] = $it;
			}
			return $ret;
		}
		return array();
	}
}


// -----------------------------------------------------------------------------


if ( ! function_exists( '\wplug\get_multiple_post_meta' ) ) {
	/**
	 * Retrieve multiple post meta values.
	 *
	 * @param int         $post_id     Post ID.
	 * @param string      $base_key    Base key of variable names.
	 * @param string[]    $keys        Keys of variable names.
	 * @param string|null $special_key (Optional) Special key.
	 * @return array<string, mixed>[] The meta values.
	 */
	function get_multiple_post_meta( int $post_id, string $base_key, array $keys, ?string $special_key = null ): array {
		$ret = array();
		$val = get_post_meta( $post_id, $base_key, true );

		if ( is_numeric( $val ) ) {  // For backward compatibility.
			$count = (int) $val;
			for ( $i = 0; $i < $count; ++$i ) {
				$it = array();
				foreach ( $keys as $key ) {
					$it[ $key ] = get_post_meta( $post_id, "{$base_key}_{$i}_{$key}", true );
				}
				$ret[] = $it;
			}
		} elseif ( $special_key ) {
			$skv = null;
			$ret = json_decode( $val, true );
			if ( is_array( $ret ) ) {
				if ( isset( $ret['#'] ) ) {
					$skv = $ret[ $special_key ] ?? null;
					$ret = $ret['#'];
				}
			} else {
				$ret = array();
			}
			$ret[ $special_key ] = $skv;
		} else {
			$ret = json_decode( $val, true );
			if ( ! is_array( $ret ) ) {
				$ret = array();
			}
		}
		return $ret;
	}
}

if ( ! function_exists( '\wplug\set_multiple_post_meta' ) ) {
	/**
	 * Stores multiple post meta values.
	 *
	 * @param int                    $post_id     Post ID.
	 * @param string                 $base_key    Base key of variable names.
	 * @param array<string, mixed>[] $vals        Values.
	 * @param string[]|null          $keys        Keys of variable names.
	 * @param string|null            $special_key (Optional) Special key.
	 */
	function set_multiple_post_meta( int $post_id, string $base_key, array $vals, ?array $keys = null, ?string $special_key = null ): void {
		$val = get_post_meta( $post_id, $base_key, true );

		// Remove old style data.
		if ( is_numeric( $val ) ) {
			$count = count( $vals );
			if ( null === $keys ) {
				if ( 0 < $count ) {
					$keys = array_keys( reset( $vals ) );
				}
				if ( empty( $keys ) ) {
					return;
				}
			}
			$old_count = (int) $val;
			for ( $i = 0; $i < $old_count; ++$i ) {
				foreach ( $keys as $key ) {
					delete_post_meta( $post_id, "{$base_key}_{$i}_{$key}" );
				}
			}
			if ( 0 === $count ) {
				delete_post_meta( $post_id, $base_key );
			}
		}
		if ( empty( $keys ) ) {
			return;
		}
		// Update data.
		if ( $special_key ) {
			$skv = $vals[ $special_key ] ?? null;
			unset( $vals[ $special_key ] );

			if ( 0 === count( $vals ) && null === $skv ) {
				delete_post_meta( $post_id, $base_key );
			} else {
				foreach ( $vals as &$val ) {
					$it = array();
					foreach ( $keys as $key ) {
						$it[ $key ] = $val[ $key ];
					}
					$val = $it;
				}
				$vals = array(
					'#'          => $vals,
					$special_key => $skv,
				);
			}
		} else {
			if ( 0 === count( $vals ) ) {
				delete_post_meta( $post_id, $base_key );
			} else {
				foreach ( $vals as &$val ) {
					$it = array();
					foreach ( $keys as $key ) {
						$it[ $key ] = $val[ $key ];
					}
					$val = $it;
				}
			}
		}
		$json = wp_json_encode( $vals, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		if ( false !== $json ) {
			update_post_meta( $post_id, $base_key, addslashes( $json ) );  // Because the meta value is passed through the stripslashes() function upon being stored.
		}
	}
}
