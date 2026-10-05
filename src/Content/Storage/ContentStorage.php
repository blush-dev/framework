<?php

/**
 * Content storage.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Storage;

use Blush\Content\Source\ContentSource;
use Blush\Content\Writer\ContentWriter;

/**
 * Where a site keeps its content (D-003, D-485): the source that reads
 * documents and the writer that changes them, as a pair. The site picks
 * one by name with `StorageConfig`'s driver for content (D-486), and the
 * container builds both classes, so each can ask for the services it
 * needs, a database connection or the content repository alike.
 */
interface ContentStorage
{
	/**
	 * Returns the class that reads the content.
	 *
	 * @return class-string<ContentSource>
	 */
	public function source(): string;

	/**
	 * Returns the class that writes the content.
	 *
	 * @return class-string<ContentWriter>
	 */
	public function writer(): string;
}
