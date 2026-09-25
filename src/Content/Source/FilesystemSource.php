<?php

/**
 * Filesystem content source.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Source;

use FilesystemIterator;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use UnexpectedValueException;
use Override;
use Blush\Content\Parser\DocumentParsers;
use Blush\Core\Paths;
use Blush\Support\Filesystem;
use Blush\Support\FilesystemException;

/**
 * Reads content documents from `user/content` (the default source). A
 * document is any file whose extension a document parser handles; other
 * files (media in page bundles, stray scripts) are ignored, as are hidden
 * files and folders (`.git`, `.DS_Store`). Every path is confined to the
 * content root.
 */
final readonly class FilesystemSource implements ContentSource
{
	private string $root;

	public function __construct(
		Paths $paths,
		private DocumentParsers $parsers,
		private Filesystem $filesystem = new Filesystem()
	) {
		$this->root = $paths->content;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function files(): array
	{
		if (! is_dir($this->root)) {
			return [];
		}

		try {
			$iterator = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(
				new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS),
				static fn (SplFileInfo $file): bool => ! str_starts_with($file->getFilename(), '.')
			));
		} catch (UnexpectedValueException $e) {
			throw new UnreadableSource(sprintf('Unable to read the content folder "%s".', $this->root), previous: $e);
		}

		$files = [];

		foreach ($iterator as $file) {
			if ($file instanceof SplFileInfo && $file->isFile() && $this->parsers->supports($file->getFilename())) {
				$path         = str_replace('\\', '/', substr($file->getPathname(), strlen($this->root) + 1));
				$files[$path] = new SourceFile($path, (int) $file->getMTime(), (int) $file->getSize());
			}
		}

		ksort($files, SORT_STRING);

		return array_values($files);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function stat(string $path): ?SourceFile
	{
		$absolute = $this->absolute($path);

		if (! is_file($absolute)) {
			return null;
		}

		clearstatcache(true, $absolute);

		return new SourceFile(
			substr($absolute, strlen($this->root) + 1),
			(int) filemtime($absolute),
			(int) filesize($absolute)
		);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function read(string $path): string
	{
		$contents = is_file($absolute = $this->absolute($path)) ? @file_get_contents($absolute) : false;

		return $contents === false
			? throw new UnreadableSource(sprintf('Unable to read content file "%s".', $path))
			: $contents;
	}

	/**
	 * Returns a path's absolute location, confined to the content root.
	 *
	 * @throws UnreadableSource
	 */
	private function absolute(string $path): string
	{
		try {
			return $this->filesystem->confine($this->root, $path);
		} catch (FilesystemException $e) {
			throw new UnreadableSource($e->getMessage(), previous: $e);
		}
	}
}
