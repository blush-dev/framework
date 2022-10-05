<?php
/**
 * Template tags interface.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Contracts\Template;

// Concretes.
use Blush\Template\Tags\Tag;
use Blush\Tools\Collection;

interface Tags
{
	/**
	 * Creates a new tag object if it exists.
	 *
	 * @since 1.0.0
	 */
	public function callback( string $name, Collection $data, array $args = [] ): ?Tag;
}
