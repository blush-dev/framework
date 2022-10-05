<?php
/**
 * Template tag abstract class.
 *
 * Template tags can be registered with the template engine so that they are
 * "bolted" onto the object and behave as if they were methods (e.g.,
 * `$engine->tagName()`). Developers can create custom constructors with any
 * number of parameters, required or not, and the engine will pass down those
 * that are input. Essentially, this is just a way of extending the template
 * engine for custom use cases.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Template\Tags;

use Stringable;
use Blush\Contracts\{CastsToHtml, CastsToText};
use Blush\Tools\Collection;

abstract class Tag implements CastsToHtml, CastsToText, Stringable
{
	/**
	 * Shared data passed in from a view.
	 *
	 * @since 1.0.0
	 */
	protected Collection $data;

	/**
	 * Developers must overload this method and return either an empty string
	 * or valid HTML.  The resulting output is expected to be safe for
	 * output within a template.
	 *
	 * @since 1.0.0
	 */
	abstract public function toHtml(): string;

	/**
	 * Developers must overload this method and return either an empty string
	 * or the element's value as a string. If the tag is only ever meant to
	 * output HTML (e.g., like a complex gallery implementation), returning
	 * an empty string instead would be OK.  The return value of this
	 * method is not expected to be escaped and safe for display.  This
	 * should happen at the point of display.
	 *
	 * @since 1.0.0
	 */
	abstract public function toText(): string;

	/**
	 * Sets the data for the tag.  Generally, this is `View` data that is
	 * automatically passed in from the `Engine`, at least when used in that
	 * context.  So, any data should be data normally available.  However,
	 * developers should perform checks on whether data exists and is valid
	 * before using it.
	 *
	 * @since 1.0.0
	 */
	public function setData( Collection $data ): void
	{
		$this->data = $data;
	}

	/**
	 * Returns the tag HTML if it is used directly as a string.
	 *
	 * @since 1.0.0
	 */
	public function __toString(): string
	{
		return $this->toHtml();
	}
}
