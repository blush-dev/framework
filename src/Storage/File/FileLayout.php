<?php

/**
 * File layout.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Storage\File;

/**
 * How the filesystem driver keeps a table (D-643): a **folder** of JSON
 * files, one a record, named by the record's key (D-646), or its id for
 * a table without one; or **one file** holding the table's records as a
 * JSON list, under a top-level key or as the whole file. Other top-level
 * keys in a one-file table are kept as they are.
 *
 * Paths are absolute; `mode` is what new files are written with.
 */
final readonly class FileLayout
{
	public function __construct(
		public bool $oneFile,
		public string $path,
		public ?string $root = null,
		public int $mode = 0664
	) {}

	/**
	 * A folder of files, one a record.
	 */
	public static function folder(string $path, int $mode = 0664): self
	{
		return new self(false, rtrim($path, '/'), null, $mode);
	}

	/**
	 * One file for the table, its records listed under `$root`, or as the
	 * whole file without one.
	 */
	public static function oneFile(string $path, ?string $root = null, int $mode = 0664): self
	{
		return new self(true, $path, $root, $mode);
	}
}
