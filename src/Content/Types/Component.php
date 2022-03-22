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

namespace Blush\Content\Types;

use Blush\Contracts\Bootable;

class Component implements Bootable
{
	/**
	 * Registry of content types.
	 *
	 * @since 1.0.0
	 */
        protected Types $registy;

	/**
	 * Array of user-defined content types.
	 *
	 * @since  1.0.0
	 */
        protected array $types;

	/**
	 * Sets up object state.
	 *
	 * @since 1.0.0
	 */
        public function __construct( Types $registry, array $types )
	{
                $this->registry = $registry;
		$this->types    = $types;
        }

	/**
	 * Registers content types on boot.
	 *
	 * @since 1.0.0
	 */
        public function boot(): void
	{
		// Registers user-configured content types.
		foreach ( $this->types as $type => $options ) {
			$this->registry->add( $type, $options );
		}
        }
}
