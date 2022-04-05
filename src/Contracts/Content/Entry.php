<?php
/**
 * Content entry interface.
 *
 * Defines the contract that content entry classes should implement.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Contracts\Content;

use Blush\Content\Types\Type;

interface Entry
{
	/**
	 * Returns the entry type.
	 *
	 * @since 1.0.0
	 */
	public function type(): Type;

	/**
	 * Returns the entry URI.
	 *
	 * @todo   Allow for taxonomy terms in slug.
	 * @since  1.0.0
	 */
	public function uri(): string;

	/**
	 * Returns the entry content.
	 *
	 * @since 1.0.0
	 */
	public function content(): string;

	/**
	 * Returns entry metadata.
	 *
	 * @since  1.0.0
	 */
	public function meta( string $name = '', mixed $default = false ): mixed;

	/**
	 * Returns only a single meta value.
	 *
	 * @since  1.0.0
	 */
	public function metaSingle( string $name, mixed $default = false ): mixed;

	/**
	 * Ensures that an array of meta values is returned.
	 *
	 * @since  1.0.0
	 */
	public function metaArr( string $name, array $default = [] ): array;

	/**
	 * Returns a Query for content type entries stored in the current
	 * entry's metadata.
	 *
	 * @since  1.0.0
	 */
	public function metaQuery( string $name, array $args = [] ): Query|bool;

	/**
	 * Returns the entry title.
	 *
	 * @since 1.0.0
	 */
	public function title(): string;

	/**
	 * Returns the entry subtitle.
	 *
	 * @since 1.0.0
	 */
	public function subtitle(): string;

	/**
	 * Returns the entry date.
	 *
	 * @since 1.0.0
	 */
	public function date(): string;

	/**
	 * Returns the entry author.
	 *
	 * @since  1.0.0
	 */
	public function author(): string;

	/**
	 * Returns the entry authors.
	 *
	 * @since  1.0.0
	 */
	public function authors(): array;

	/**
	 * Returns a Query of taxonomy entries or false.
	 *
	 * @since  1.0.0
	 */
	public function terms( string $taxonomy, array $args = [] ): Query|bool;

	/**
	 * Returns the entry excerpt.
	 *
	 * @since  1.0.0
	 */
	public function excerpt(
		int $limit = 50,
		string $more = '&hellip;'
	): string;
}
