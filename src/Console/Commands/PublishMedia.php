<?php

/**
 * Media publishing command.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Console\Commands;

use FilesystemIterator;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Blush\Console\Attributes\Command;
use Blush\Console\Attributes\Option;
use Blush\Console\ExitCode;
use Blush\Console\Output;
use Blush\Console\Verbosity;
use Blush\Core\Paths;
use Blush\Media\MediaConfig;
use Blush\Support\Filesystem;

/**
 * Makes `user/media` servable by the web server at the media URL, so
 * media requests never start PHP. By default it links
 * `{public}{url}` to `user/media` with a relative symlink. `--copy`
 * copies the allowed files instead (for hosts that can't symlink), and
 * can be run again to copy what changed. Until media is published, the
 * media route streams it.
 */
#[Command('media:publish', 'Link or copy user/media into the public folder.')]
final readonly class PublishMedia
{
	public function __construct(
		private Paths $paths,
		private MediaConfig $config,
		private Filesystem $filesystem
	) {}

	public function __invoke(
		Output $output,
		#[Option('Copy the files instead of linking the folder.')] bool $copy = false
	): ExitCode {
		$source = $this->paths->media;
		$target = $this->paths->public . $this->config->url;

		if (! is_dir($source)) {
			$output->error(sprintf('There is no media folder at %s.', $this->paths->relative($source)));

			return ExitCode::Failure;
		}

		if (is_link($target) && ($copy || realpath($target) !== realpath($source))) {
			unlink($target);
		}

		return $copy ? $this->copy($output, $source, $target) : $this->link($output, $source, $target);
	}

	/**
	 * Links the public media folder to `user/media`.
	 */
	private function link(Output $output, string $source, string $target): ExitCode
	{
		if (is_link($target)) {
			$output->success(sprintf('%s is already linked to %s.', $this->paths->relative($target), $this->paths->relative($source)));

			return ExitCode::Success;
		}

		if (file_exists($target)) {
			$output->error(sprintf('%s already exists and isn\'t a link; remove it, or run media:publish --copy.', $this->paths->relative($target)));

			return ExitCode::Failure;
		}

		if (! is_dir(dirname($target))) {
			mkdir(dirname($target), 0775, true);
		}

		if (! @symlink($this->filesystem->relative(dirname($target), $source), $target)) {
			$output->error(sprintf('Unable to link %s; this host may not allow symlinks. Run media:publish --copy instead.', $this->paths->relative($target)));

			return ExitCode::Failure;
		}

		$output->success(sprintf('Linked %s to %s.', $this->paths->relative($target), $this->paths->relative($source)));

		return ExitCode::Success;
	}

	/**
	 * Copies the allowed files that are new or changed.
	 */
	private function copy(Output $output, string $source, string $target): ExitCode
	{
		$files = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(
			new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
			static fn (SplFileInfo $file): bool => ! str_starts_with($file->getFilename(), '.')
		));

		$copied  = 0;
		$current = 0;

		foreach ($files as $file) {
			if (! $file instanceof SplFileInfo || ! $file->isFile() || ! $this->config->allows(mime_content_type($file->getPathname()) ?: '')) {
				continue;
			}

			$relative    = substr($file->getPathname(), strlen($source) + 1);
			$destination = "{$target}/{$relative}";

			if (is_file($destination) && filesize($destination) === $file->getSize() && filemtime($destination) === $file->getMTime()) {
				$current++;
				continue;
			}

			if (! is_dir(dirname($destination))) {
				mkdir(dirname($destination), 0775, true);
			}

			copy($file->getPathname(), $destination);
			touch($destination, (int) $file->getMTime());

			$output->line("Copied {$relative}", Verbosity::Verbose);
			$copied++;
		}

		$output->success(sprintf('Copied %d file(s) to %s; %d already current.', $copied, $this->paths->relative($target), $current));

		return ExitCode::Success;
	}
}
