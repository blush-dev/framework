<?php

/**
 * PHP file content index.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Index;

use Override;
use Blush\Content\Query\Query;
use Blush\Content\Query\Selection;
use Blush\Core\Paths;
use Blush\Support\FilesystemException;
use Blush\Support\PhpArrayFile;

/**
 * The default index: `storage/index/content.php`, a PHP file returning the
 * snapshot's array (D-044). Opcache keeps it in shared memory, so reading
 * it costs no parsing, and it's read once per request. Queries run as
 * array filters (`ArraySelector`).
 */
final class PhpIndex implements ContentIndex
{
	/**
	 * The index file's name in `storage/index`.
	 */
	public const string FILE = 'content.php';

	private ?IndexSnapshot $snapshot = null;

	private readonly PhpArrayFile $file;

	public function __construct(Paths $paths, private readonly ArraySelector $selector = new ArraySelector())
	{
		$this->file = new PhpArrayFile("{$paths->index}/" . self::FILE);
	}

	/**
	 * Returns the index file's path.
	 */
	public function path(): string
	{
		return $this->file->path;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function exists(): bool
	{
		return $this->file->exists();
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function snapshot(): IndexSnapshot
	{
		if ($this->snapshot !== null) {
			return $this->snapshot;
		}

		try {
			$data = $this->file->read();
		} catch (FilesystemException) {
			$data = null;
		}

		return $this->snapshot = $data === null ? IndexSnapshot::empty() : IndexSnapshot::fromArray($data);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function save(IndexSnapshot $snapshot): void
	{
		try {
			$this->file->write($snapshot->toArray());
		} catch (FilesystemException $e) {
			throw new IndexException(sprintf('Unable to write the content index: %s', $e->getMessage()), previous: $e);
		}

		$this->snapshot = $snapshot;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function clear(): void
	{
		$this->file->delete();
		$this->snapshot = null;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function select(Query $query, int $now): Selection
	{
		return $this->selector->select($this->snapshot()->records, $query, $now);
	}
}
