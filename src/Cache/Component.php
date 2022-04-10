<?php
/**
 * Cache component.
 *
 * Bootstraps the cache component, acting as a bridge to the cache registry.
 * On booting, it sets up the default and user-configured drivers and stores.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Cache;

// Contracts.
use Blush\Contracts\Bootable;
use Blush\Contracts\Cache\Registry;

// Classes.
use Blush\Config;

class Component implements Bootable
{
	/**
	 * Sets up object state.
	 *
	 * @since  1.0.0
	 */
	public function __construct(
		protected Registry $registry,
		protected array $drivers,
		protected $stores
	) {}

	/**
	 * Bootstraps the component, setting up cache drivers and stores.
	 *
	 * @since  1.0.0
	 */
	public function boot(): void
	{
		// Add drivers to the cache registry.
		foreach ( $this->drivers as $name => $driver ) {
			$this->registry->addDriver( $name, $driver );
		}

		// Add stores to the cache registry.
		foreach ( $this->stores as $name => $options ) {
			$this->registry->addStore( $name, $options );
		}
	}
}
