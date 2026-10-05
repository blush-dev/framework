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
 * Content as files in `user/content`, the default: each document a
 * Markdown file with front matter (`.md`, D-501).
 */
final readonly class FilesystemStorage implements ContentStorage
{
	/**
	 * The extension of a content file, without the dot. Other files in
	 * the content folder aren't content.
	 */
	public const string EXTENSION = 'md';

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
