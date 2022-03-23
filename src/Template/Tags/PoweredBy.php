<?php
/**
 * Powered by Blush class.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Template\Tags;

// Contracts.
use Blush\Contracts\{Displayable, Renderable};

class PoweredBy implements Displayable, Renderable
{
	/**
	 * Stores the array of notes.
	 *
	 * @since 1.0.0
	 */
	protected array $superpowers;

	/**
	 * Sets up the object state.
	 *
	 * @since 1.0.0
	 */
	public function __construct()
	{
		$this->superpowers = [
			'Powered by heart and soul.',
			'Powered by crazy ideas and passion.',
			'Powered by the thing that holds all things together in the universe.',
			'Powered by love.',
			'Powered by the vast and endless void.',
			'Powered by the code of a maniac.',
			'Powered by peace and understanding.',
			'Powered by coffee.',
			'Powered by sleepness nights.',
			'Powered by the love of all things.',
			'Powered by something greater than myself.'
		];
	}

	/**
	 * Displays the message.
	 *
	 * @since 1.0.0
	 */
	public function display(): void
	{
		echo $this->render();
	}

	/**
	 * Returns the message.
	 *
	 * @since 1.0.0
	 */
	public function render(): string
	{
		$collection = $this->superpowers;

		return $collection[ array_rand( $collection, 1 ) ];
	}
}
