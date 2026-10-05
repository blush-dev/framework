<?php

/**
 * Filesystem storage.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Storage;

use Override;
use Blush\Content\Source\FilesystemSource;
use Blush\Content\Writer\FilesystemWriter;

/**
 * Content as files in `user/content`, the default.
 */
final readonly class FilesystemStorage implements ContentStorage
{
	/**
	 * @inheritDoc
	 */
	#[Override]
	public function source(): string
	{
		return FilesystemSource::class;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function writer(): string
	{
		return FilesystemWriter::class;
	}
}
