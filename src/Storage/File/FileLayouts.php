<?php

/**
 * File layouts.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Storage\File;

use Blush\Core\Paths;
use Blush\Storage\Record\InvalidRecord;
use Blush\Storage\Record\Table;
use Blush\Storage\StorageArea;

/**
 * How the filesystem driver keeps each table (D-643). A table is a folder
 * in its area's root by default: `user/content/{table}`,
 * `user/data/{table}`, or `storage/{table}` for accounts. A table kept
 * otherwise, such as one already kept as one file, registers its layout
 * when the layouts are built, in a provider's `register()`:
 *
 *     $this->container->resolving(FileLayouts::class, static function (object $layouts): void {
 *         $layouts->register('gallery/albums', FileLayout::oneFile('/path/to/albums.json', 'albums'));
 *     });
 *
 * Layouts are the filesystem driver's alone: another driver never reads
 * them.
 */
final class FileLayouts
{
	/**
	 * Layouts by table name.
	 *
	 * @var array<string, FileLayout>
	 */
	private array $layouts = [];

	public function __construct(
		private readonly Paths $paths
	) {}

	/**
	 * Registers how a table is kept.
	 */
	public function register(string $table, FileLayout $layout): void
	{
		$this->layouts[$table] = $layout;
	}

	/**
	 * Returns how a table is kept.
	 *
	 * @throws InvalidRecord When a table whose key holds paths would be a folder, whose files are named by it.
	 */
	public function for(Table $table): FileLayout
	{
		$layout = $this->layouts[$table->name] ?? FileLayout::folder(match ($table->area) {
			StorageArea::Content => $this->paths->content,
			StorageArea::Data    => $this->paths->data,
			default              => $this->paths->storage
		} . "/{$table->name}");

		if ($table->pathKey && ! $layout->oneFile) {
			throw new InvalidRecord(sprintf('"%s" is keyed by paths, so it\'s kept as one file; register a one-file layout for it.', $table->name));
		}

		return $layout;
	}
}
