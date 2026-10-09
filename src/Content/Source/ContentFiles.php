<?php

/**
 * Content files.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Source;

use Closure;
use Blush\Container\Attributes\Defer;
use Blush\Storage\StorageArea;
use Blush\Storage\StorageException;
use Blush\Storage\StorageResolver;

/**
 * Whether the site keeps its content as files, and, when it does, their
 * source (D-667). What only files have (the index, the linter's file
 * checks, file tools, the welcome page's path) asks here, so it can be
 * built on any driver and step aside on a database, where there are no
 * content files to read.
 */
final readonly class ContentFiles
{
	/**
	 * @param Closure(): ContentSource $source
	 */
	public function __construct(
		private StorageResolver $resolver,
		#[Defer(ContentSource::class)] private Closure $source
	) {}

	/**
	 * Returns whether content is kept as files.
	 */
	public function kept(): bool
	{
		return $this->resolver->covers(StorageArea::Content, ContentSource::class);
	}

	/**
	 * Returns the content files' source.
	 *
	 * @throws StorageException When content isn't kept as files.
	 */
	public function source(): ContentSource
	{
		if (! $this->kept()) {
			throw new StorageException('The site keeps its content in a database, so there are no content files to read.');
		}

		return ($this->source)();
	}
}
