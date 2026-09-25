<?php

/**
 * Markdown environment building event.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Markdown\Events;

use League\CommonMark\Environment\EnvironmentBuilderInterface;

/**
 * Dispatched once, when the CommonMark adapter builds its environment,
 * after the configured extensions and inline parsers are added. Listeners
 * add syntax, renderers, or event listeners of their own. This event is
 * specific to the CommonMark adapter (D-080).
 */
final readonly class MarkdownEnvironmentBuilding
{
	public function __construct(public EnvironmentBuilderInterface $environment)
	{}
}
