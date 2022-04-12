<?php
/**
 * Template view interface.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Contracts\Template;

// Concretes.
use Blush\Tools\Collection;

interface View
{
	/**
	 * Sets the view paths.
	 *
	 * @since 1.0.0
	 */
	public function setPaths( array $paths ): void;

	/**
	 * Gets the view paths.
	 *
	 * @since 1.0.0
	 */
	public function getPaths(): array;

	/**
	 * Sets the view data.
	 *
	 * @since 1.0.0
	 */
	public function setData( Collection $data ): void;

	/**
	 * Gets the view data.
	 *
	 * @since 1.0.0
	 */
	public function getData(): Collection;

	/**
	 * Returns the located template.
	 *
	 * @since 1.0.0
	 */
	public function template(): string;

	/**
	 * Displays the view.
	 *
	 * @since 1.0.0
	 */
	public function display(): void;

	/**
	 * Returns the view.
	 *
	 * @since 1.0.0
	 */
	public function render(): string;
}
