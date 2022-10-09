<?php
/**
 * Content type interface.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Contracts\Content;

interface ContentType
{
	/**
	 * Returns the content type name.
	 *
	 * @since 1.0.0
	 */
	public function name(): string;

	/**
	 * Returns the content type name (alias for `name()`).
	 *
	 * @since 1.0.0
	 * @deprecated 1.0.0
	 */
	public function type(): string;

	/**
	 * Conditional check if the typs is the homepage alias.
	 *
	 * @since 1.0.0
	 */
	public function isHomeAlias(): bool;

	/**
	 * Returns the content type path.
	 *
	 * @since 1.0.0
	 */
	public function path(): string;

	/**
	 * Returns a keyed URL path.
	 *
	 * @since 1.0.0
	 */
	public function urlPath( string $key = '' ): string;

	/**
	 * Returns the content type URL.
	 *
	 * @since 1.0.0
	 */
	public function url(): string;

	/**
	 * Returns the content type URL for single entries.
	 *
	 * @since 1.0.0
	 */
	public function singleUrl( array $params = [] ): string;

	/**
	 * Returns the content type feed URL.
	 *
	 * @since 1.0.0
	 */
	public function feedUrl(): string;

	/**
	* Returns the content type RSS feed URL.
	*
	* @since 1.0.0
	*/
       public function rssFeedUrl(): string;

       /**
	* Returns the content type atom feed URL.
	*
	* @since 1.0.0
	*/
       public function atomFeedUrl(): string;

	/**
	 * Returns the content type URL for year archives.
	 *
	 * @since 1.0.0
	 */
	public function yearUrl( string $year ): string;

	/**
	 * Returns the content type URL for month archives.
	 *
	 * @since 1.0.0
	 */
	public function monthUrl( string $year, string $month ): string;

	/**
	 * Returns the content type URI for day archives.
	 *
	 * @since 1.0.0
	 */
	public function dayUrl( string $year, string $month, string $day ): string;

	/**
	 * Whether the content type should have a feed.
	 *
	 * @since 1.0.0
	 */
	public function hasFeed(): bool;

	/**
	 * Whether date archives are supported. If time archives are supported
	 * date archives are required to be enabled.
	 *
	 * @since 1.0.0
	 */
	public function hasDateArchives(): bool;

	/**
	 * Whether time archives are supported.
	 *
	 * @since 1.0.0
	 */
	public function hasTimeArchives(): bool;

	/**
	 * Whether routing is enabled.
	 *
	 * @since 1.0.0
	 */
	public function routing(): bool;

	/**
	 * Returns the type this content type collects.
	 *
	 * @since  1.0.0
	 */
	public function collect(): string|bool|null;

	/**
	 * Returns the type that terms of this type collects if a taxonomy.
	 *
	 * @since  1.0.0
	 */
	public function termCollect(): string|bool|null;

	/**
	 * Returns an array of Query arguments when the content type is used in
	 * a collection.
	 *
	 * @since 1.0.0
	 */
	public function collectionArgs(): array;

	/**
	 * Returns an array of Query arguments when the terms of the content
	 * type is called as a collection. Only works for taxonomies.
	 *
	 * @since 1.0.0
	 */
	public function termCollectionArgs(): array;

	/**
	 * Returns an array of Query arguments when the content type is used in
	 * a feed.
	 *
	 * @since 1.0.0
	 */
	public function feedArgs(): array;

	/**
	 * Whether this type is a taxonomy.
	 *
	 * @since 1.0.0
	 */
	public function isTaxonomy(): bool;

	/**
	 * Returns the content type routes as an array.
	 *
	 * @since 1.0.0
	 */
	public function routes(): array;
}
