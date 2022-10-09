<?php
/**
 * Template tag contract.
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

namespace Blush\Contracts\Template;

use Stringable;
use Blush\Contracts\{CastsToHtml, CastsToText};
use Blush\Tools\Collection;

interface TemplateTag extends CastsToHtml, CastsToText, Stringable
{
	/**
	 * Sets the data for the tag.
	 *
	 * @since 1.0.0
	 */
	public function setData( Collection $data ): void;
}
