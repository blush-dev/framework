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

use Blush\Tools\Media;

interface ContentEntry
{
	/**
	 * Returns the entry type.
	 *
	 * @since 1.0.0
	 */
	public function type(): ContentType;

	/**
	 * Returns the entry name/slug/ID.
	 *
	 * @since 1.0.0
	 */
	public function name(): string;

	/**
	 * Returns the entry's visibility.
	 *
	 * @since 1.0.0
	 */
	public function visibility(): string;

	/**
	 * Checks if an entry is viewable to the public.
	 *
	 * @since 1.0.0
	 */
	public function isPublic(): bool;

	/**
	 * Checks if an entry is hidden from the public.
	 *
	 * @since 1.0.0
	 */
	public function isHidden(): bool;

	/**
	 * Returns the entry URL.
	 *
	 * @since  1.0.0
	 */
	public function url(): string;

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
	public function metaQuery( string $name, array $args = [] ): ContentQuery|false;

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
	* Returns the entry published datetime.
	*
	* @since 1.0.0
	*/
       public function published( string $format = '' ): string;

       /**
	* Returns the entry updated datetime.
	*
	* @since 1.0.0
	*/
       public function updated( string $format = '' ): string;

	/**
	 * Returns the entry date.
	 *
	 * @since 1.0.0
	 * @deprecated 1.0.0
	 */
	public function date(): string;

	/**
	 * Returns the entry author.
	 *
	 * @since  1.0.0
	 */
	public function author(): ContentEntry|false;

	/**
	 * Returns the entry authors.
	 *
	 * @since  1.0.0
	 */
	public function authors(): array;

	/**
	* Returns a media object based on a media file path stored as metadata.
	*
	* @since  1.0.0
	*/
       public function media( string $name = 'image' ): Media|null;

	/**
	 * Returns an array of view paths assigned as metadata.
	 *
	 * @since  1.0.0
	 */
	public function viewPaths(): array;

	/**
	 * Returns an array of Query arguments if assigned as metadata.
	 *
	 * @since  1.0.0
	 */
	public function collectionArgs(): array;

	/**
	 * Returns an array of the taxonomy (content type) objects associated
	 * with the entry.
	 *
	 * @since  1.0.0
	 */
	public function taxonomies(): array;

	/**
	 * Conditional check if the entry is associated with a taxonomy.
	 *
	 * @since  1.0.0
	 */
	public function hasTaxonomy( string $taxonomy ): bool;

	/**
	 * Returns a Query of taxonomy entries or false.
	 *
	 * @since  1.0.0
	 */
	public function terms( string $taxonomy, array $args = [] ): ContentQuery|false;

	/**
	 * Conditional check if the entry has a term from a specific taxonomy.
	 *
	 * @since  1.0.0
	 */
	public function hasTerm( string $taxonomy, string $term ): bool;

	/**
	 * Returns the entry excerpt.
	 *
	 * @since  1.0.0
	 */
	public function excerpt( int $limit = 50, string $more = '&hellip;' ): string;

	/**
	 * Returns an estimated reading time in hours (if an hour or longer) and
	 * minutes.
	 *
	 * @since  1.0.0
	 */
	public function readingTime( int $words_per_min = 200 ): string;
}
