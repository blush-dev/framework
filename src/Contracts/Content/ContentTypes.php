<?php
/**
 * Content types interface.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Contracts\Content;

interface ContentTypes
{
	/**
	 * Gets a custom content type by its path.
	 *
	 * @since  1.0.0
	 */
	public function getTypeFromPath( string $path ): ContentType|false;

	/**
	 * Gets a custom content type by its URI.
	 *
	 * @since  1.0.0
	 */
	public function getTypeFromUri( string $uri ): ContentType|false;

	/**
	 * Sorts types by their path.
	 *
	 * @since 1.0.0
	 */
	public function sortByPath(): array;
}
