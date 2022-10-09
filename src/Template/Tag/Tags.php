<?php
/**
 * Template tags registry.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Template\Tag;

use Blush\App;
use Blush\Contracts\Template\{TemplateTag, TemplateTags};
use Blush\Tools\Collection;

class Tags extends Collection implements TemplateTags
{
	/**
	 * Creates a new tag object if it exists.
	 *
	 * @since 1.0.0
	 */
	public function callback(
		string $name,
		Collection $data,
		array $args = []
	): ?TemplateTag
	{
		// Check if the tag is registered and that its class exists.
		if ( $this->has( $name ) && class_exists( $this->get( $name ) ) ) {
			$callback = $this->get( $name );

			// Creates a new object from the registered tag class.
			$tag = new $callback( ...$args );

			// Set the data before returning the tag.
			$tag->setData( $data );
			return $tag;
		}

		return null;
	}
}
