<?php

/**
 * Embedded reader registry.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Media\Embedded;

use Blush\Support\Registry;

/**
 * Maps embedded metadata reader keys to `EmbeddedReader` classes (D-289),
 * in the order their values win. It starts with the built-in readers; an
 * extension adds one (say, for HEIC maker notes) by registering it in a
 * `resolving()` callback, after them unless it re-registers them.
 *
 * @extends Registry<EmbeddedReader>
 */
final class EmbeddedReaderRegistry extends Registry
{
	/**
	 * @inheritDoc
	 */
	protected const string CONTRACT = EmbeddedReader::class;
}
