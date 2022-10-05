<?php
/**
 * Template component class.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Template;

// Contracts.
use Blush\Contracts\Bootable;
use Blush\Contracts\Template\Tags;

class Component implements Bootable
{
	/**
	 * Sets up the object state.
	 *
	 * @since 1.0.0
	 */
	public function __construct(
		protected Tags $registry,
		protected array $tags
	) {}

	/**
	 * Bootstraps the component.
	 *
	 * @since 1.0.0
	 */
	public function boot(): void
	{
		foreach ( $this->tags as $name => $callback ) {
			$this->registry->add( $name, $callback );
		}
	}
}
