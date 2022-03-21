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
	 * Collection of content types.
	 *
	 * @since 1.0.0
	 */
        protected Types $types;

	/**
	 * Config array for content types.
	 *
	 * @since 1.0.0
	 */
        protected array $config;

	/**
	 * Sets up object state.
	 *
	 * @since 1.0.0
	 */
        public function __construct( Types $types, array $config )
	{
                $this->types  = $types;
                $this->config = $config;
        }

	/**
	 * Registers content types on boot.
	 *
	 * @since 1.0.0
	 */
        public function boot(): void
	{
		// Registers user-configured content types.
                foreach ( $this->config as $type => $options ) {
                        $this->types->add( $type, $options );
                }

		// Adds the internal `page` content type.
		$this->types->add( 'page', [
			'path'    => '',
			'routing' => false,
			'collect' => false
		] );
        }
}
