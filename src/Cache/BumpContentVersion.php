<?php

/**
 * Content version listener.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Cache;

use Blush\Content\Events\ContentIndexed;

/**
 * Moves the content version on whenever the content index is stored.
 */
final readonly class BumpContentVersion
{
	public function __construct(private ContentVersion $version)
	{}

	public function __invoke(ContentIndexed $event): void
	{
		$this->version->bump();
	}
}
