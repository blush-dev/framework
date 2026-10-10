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

use Blush\Console\Attributes\Command;
use Blush\Console\Attributes\Option;
use Blush\Console\ExitCode;
use Blush\Console\Outcome;
use Blush\Console\Output;
use Blush\Console\StatusLine;
use Blush\Console\Verbosity;
use Blush\Core\Framework;
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
 *
 * Either way, the served folder gets an `.htaccess` (`HTACCESS`, D-499)
 * so Apache serves its files the way the media route does: never runs a
 * script (by any of a name's extensions), sends `nosniff`, and sandboxes
 * SVG. It's `user/media`'s own when linked. An `.htaccess` that isn't the
 * one this writes (its first line is `MARKER`) is left alone, with a
 * warning. Other servers need the same rules in their own config.
 */
#[Command('media:publish', 'Link or copy user/media into the public folder.')]
final readonly class PublishMedia
{
	/**
	 * The first line of the `.htaccess` this writes, how it knows its own.
	 */
	public const string MARKER = '# Written by media:publish, which keeps it current. Remove this line to keep your own.';

	/**
	 * The `.htaccess` for the served folder (D-499). Each part is in an
	 * `<IfModule>`, so a server without the module skips it rather than
	 * failing.
	 */
	public const string HTACCESS = self::MARKER . <<<'HTACCESS'

		# Media is only ever served as files: no script runs here, by any of
		# a name's extensions.
		<IfModule mod_authz_core.c>
			<FilesMatch "\.(?i:php\d*|pht|phtml|phar|phps|cgi|pl|py|sh|shtml|asp|aspx|jsp)(\.|$)">
				Require all denied
			</FilesMatch>
		</IfModule>
		<IfModule mod_headers.c>
			Header set X-Content-Type-Options "nosniff"
			<FilesMatch "\.(?i:svg)$">
				Header set Content-Security-Policy "sandbox"
			</FilesMatch>
		</IfModule>

		HTACCESS;

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
			$this->protect($output, $source);
			$output->success(sprintf('%s is already linked to %s.', $this->paths->relative($target), $this->paths->relative($source)));

			return ExitCode::Success;
		}

		if (file_exists($target)) {
			$output->error(sprintf('%s already exists and isn\'t a link.', $this->paths->relative($target)), 'Remove it, or run `' . Framework::BINARY . ' media:publish --copy`.');

			return ExitCode::Failure;
		}

		if (! is_dir(dirname($target))) {
			mkdir(dirname($target), 0775, true);
		}

		if (! @symlink($this->filesystem->relative(dirname($target), $source), $target)) {
			$output->error(sprintf('Unable to link %s; this host may not allow symlinks.', $this->paths->relative($target)), 'Run `' . Framework::BINARY . ' media:publish --copy` instead.');

			return ExitCode::Failure;
		}

		$this->protect($output, $source);
		$output->success(sprintf('Linked %s to %s.', $this->paths->relative($target), $this->paths->relative($source)));

		return ExitCode::Success;
	}

	/**
	 * Copies the allowed files that are new or changed.
	 */
	private function copy(Output $output, string $source, string $target): ExitCode
	{
		$copied  = [];
		$current = 0;
		$checked = 0;
		$steps   = $output->steps();

		// How many there are isn't known until they're all found.
		foreach ($this->filesystem->files($source) as $relative => $file) {
			$steps('Copying media files', ++$checked, 0);

			if (! $this->config->allows(mime_content_type($file->getPathname()) ?: '')) {
				continue;
			}

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

			$copied[] = (string) $relative;
		}

		$steps->finish();

		$output->status(array_map(static fn (string $relative): StatusLine => new StatusLine(Outcome::Pass, 'copied', $relative), $copied), level: Verbosity::Verbose);

		$this->protect($output, $target);
		$output->success(sprintf('Copied %s to %s; %s already current.', Output::count(count($copied), 'file', 'files'), $this->paths->relative($target), number_format($current)));

		return ExitCode::Success;
	}

	/**
	 * Writes the served folder's `.htaccess` (`HTACCESS`), unless one
	 * that isn't this command's is there.
	 */
	private function protect(Output $output, string $folder): void
	{
		$file     = "{$folder}/.htaccess";
		$existing = is_file($file) ? (string) file_get_contents($file) : null;

		if ($existing === self::HTACCESS) {
			return;
		}

		if ($existing !== null && ! str_starts_with($existing, self::MARKER)) {
			$output->warning(sprintf('%s is your own, so it\'s left as is. Make sure it keeps scripts from running there; see the media docs.', $this->paths->relative($file)));

			return;
		}

		if (! is_dir($folder)) {
			mkdir($folder, 0775, true);
		}

		$this->filesystem->writeAtomic($file, self::HTACCESS);
		$output->status([new StatusLine(Outcome::Pass, 'wrote', $this->paths->relative($file))], level: Verbosity::Verbose);
	}
}
