<?php

/**
 * Embedded metadata reader.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Media\Embedded;

use Throwable;
use Blush\Media\MediaException;

/**
 * Reads a media file's embedded metadata with every registered reader
 * (D-289), in the registry's order, the first value for a key winning. A
 * reader that fails on a file is skipped: embedded metadata is a help,
 * never a reason a file can't be indexed.
 */
final readonly class EmbeddedMetadataReader
{
	/**
	 * Moves on when what the readers return changes, so the media index
	 * reads files again.
	 */
	public const int VERSION = 2;

	public function __construct(
		private EmbeddedReaderRegistry $registry,
		private EmbeddedReaderFactory $factory
	) {}

	/**
	 * @throws MediaException When a reader can't be built.
	 */
	public function read(string $path, string $mime): EmbeddedMetadata
	{
		$found = new EmbeddedMetadata();

		foreach (array_keys($this->registry->all()) as $key) {
			$reader = $this->factory->make($key);

			if ($reader === null) {
				continue;
			}

			try {
				$found = $found->merge($reader->read($path, $mime));
			} catch (Throwable) {
				continue;
			}
		}

		return $found;
	}

	/**
	 * What reading depends on: the readers and this version.
	 */
	public function fingerprint(): string
	{
		return self::VERSION . ':' . implode(',', array_keys($this->registry->all()));
	}
}
