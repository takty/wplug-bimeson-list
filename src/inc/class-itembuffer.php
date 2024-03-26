<?php
/**
 * Item Buffer (Common)
 *
 * @package Wplug Bimeson List
 * @author Takuto Yanagida
 * @version 2024-03-22
 */

declare(strict_types=1);

namespace wplug\bimeson_list;

/**
 * Item buffer
 *
 * @psalm-suppress UnusedClass
 */
class ItemBuffer {

	/**
	 * Stored items.
	 *
	 * @var array<string, mixed>[]
	 */
	private $items = array();

	/**
	 * Language.
	 *
	 * @var string
	 */
	private $lang;

	/**
	 * Constructor.
	 *
	 * @param string $lang Language.
	 */
	public function __construct( string $lang ) {
		$this->lang = $lang;
	}

	/**
	 * Adds an item.
	 *
	 * @param array<string, mixed> $item An item.
	 */
	public function add( array $item ): void {
		$this->items[] = $item;
	}

	/**
	 * Displays stored items.
	 *
	 * @psalm-suppress UndefinedFunction
	 *
	 * @param array<string[]> $blocks Destination.
	 */
	public function echo( array &$blocks ): void {
		if ( ! empty( $this->items ) ) {
			_echo_list_element( $blocks, $this->items, $this->lang );
			$this->items = array();
		}
	}

	/**
	 * Check whether this has any items.
	 *
	 * @psalm-suppress PossiblyUnusedMethod
	 *
	 * @return bool True if this has any items.
	 */
	public function has_item(): bool {
		return ! empty( $this->items );
	}
}
