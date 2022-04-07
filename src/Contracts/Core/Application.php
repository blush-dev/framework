<?php
/**
 * Application contract.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Contracts\Core;

/**
 * Application interface.
 *
 * @since 1.0.0
 */
interface Application extends Container
{
	/**
	 * Access a keyed path and append a path to it.
	 *
	 * @since  1.0.0
	 */
	public function path( string $accessor = '', string $append = '' ): string;

	/**
	 * Returns app path with optional appended path/file.
	 *
	 * @since 1.0.0
	 */
	public function appPath( string $append = '' ): string;

	/**
	 * Returns config path with optional appended path/file.
	 *
	 * @since 1.0.0
	 */
	public function configPath( string $append = '' ): string;

	/**
	 * Returns public path with optional appended path/file.
	 *
	 * @since 1.0.0
	 */
	public function publicPath( string $append = '' ): string;

	/**
	 * Returns view path with optional appended path/file.
	 *
	 * @since 1.0.0
	 */
	public function viewPath( string $append = '' ): string;

	/**
	 * Returns resource path with optional appended path/file.
	 *
	 * @since 1.0.0
	 */
	public function resourcePath( string $append = '' ): string;

	/**
	 * Returns storage path with optional appended path/file.
	 *
	 * @since 1.0.0
	 */
	public function storagePath( string $append = '' ): string;

	/**
	 * Returns cache path with optional appended path/file.
	 *
	 * @since 1.0.0
	 */
	public function cachePath( string $append = '' ): string;

	/**
	 * Returns user path with optional appended path/file.
	 *
	 * @since 1.0.0
	 */
	public function userPath( string $append = '' ): string;

	/**
	 * Returns content path with optional appended path/file.
	 *
	 * @since 1.0.0
	 */
	public function contentPath( string $append = '' ): string;

	/**
	 * Returns media path with optional appended path/file.
	 *
	 * @since 1.0.0
	 */
	public function mediaPath( string $append = '' ): string;

	/**
	 * Returns vendor path with optional appended path/file.
	 *
	 * @since 1.0.0
	 */
	public function vendorPath( string $append = '' ): string;

	/**
	 * Access a keyed URL and append a path to it.
	 *
	 * @since  1.0.0
	 */
	public function url( string $accessor = '', string $append = '' ): string;

	/**
	 * Returns app URL with optional appended path/file.
	 *
	 * @since 1.0.0
	 */
	public function appUrl( string $append = '' ): string;

	/**
	 * Returns config URL with optional appended path/file.
	 *
	 * @since 1.0.0
	 */
	public function configUrl( string $append = '' ): string;

	/**
	 * Returns public URL with optional appended path/file.
	 *
	 * @since 1.0.0
	 */
	public function publicUrl( string $append = '' ): string;

	/**
	 * Returns view URL with optional appended path/file.
	 *
	 * @since 1.0.0
	 */
	public function viewUrl( string $append = '' ): string;

	/**
	 * Returns resource URL with optional appended path/file.
	 *
	 * @since 1.0.0
	 */
	public function resourceUrl( string $append = '' ): string;

	/**
	 * Returns storage URL with optional appended path/file.
	 *
	 * @since 1.0.0
	 */
	public function storageUrl( string $append = '' ): string;

	/**
	 * Returns cache URL with optional appended path/file.
	 *
	 * @since 1.0.0
	 */
	public function cacheUrl( string $append = '' ): string;

	/**
	 * Returns user URL with optional appended path/file.
	 *
	 * @since 1.0.0
	 */
	public function userUrl( string $append = '' ): string;

	/**
	 * Returns content URL with optional appended path/file.
	 *
	 * @since 1.0.0
	 */
	public function contentUrl( string $append = '' ): string;

	/**
	 * Returns media URL with optional appended path/file.
	 *
	 * @since 1.0.0
	 */
	public function mediaUrl( string $append = '' ): string;

	/**
	 * Returns vendor URL with optional appended path/file.
	 *
	 * @since 1.0.0
	 */
	public function vendorUrl( string $append = '' ): string;
}
