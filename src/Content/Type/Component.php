<?php
/**
 * Content types component.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Content\Type;

use Blush\Contracts\Bootable;
use Blush\Contracts\Content\ContentTypes;

class Component implements Bootable
{
	/**
	 * Sets up object state.
	 *
	 * @since 1.0.0
	 */
        public function __construct(
		protected ContentTypes $registry,
		protected array $types
	) {}

	/**
	 * Registers content types on boot.
	 *
	 * @since 1.0.0
	 */
        public function boot(): void
	{
		// Registers user-configured content types.
		foreach ( $this->types as $name => $options ) {
			$this->registry->add( $name, $options );
		}

		// Registers the virtual content type.
		$this->registry->add( 'virtual', [
			'public'  => false,
			'routing' => false
		] );
	}
}
