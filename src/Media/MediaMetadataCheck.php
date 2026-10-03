<?php

/**
 * Media metadata check.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Media;

use Throwable;
use Blush\Content\Lint\Linter;
use Blush\Core\AppConfig;
use Blush\Core\Paths;
use Blush\Data\DataLoader;
use Blush\Field\FieldContext;
use Blush\Field\Severity;
use Blush\Field\Violation;

/**
 * Checks the metadata files under `user/data/media` (D-238, D-293), as
 * `content:lint` reports them, by their path from the site root
 * (`user/data/media/2024/sunset.jpg.yml`). The media library reads a bad
 * one as none, so this is where a site hears about it:
 *
 * - errors: a file that can't be read, or isn't a map of fields, and a
 *   value that doesn't fit its field (alt text as a list);
 * - warnings: a file whose media file is gone (renamed or deleted
 *   without it), or isn't a type the site allows, and a file hidden by
 *   one in another format (`sunset.jpg.json` beside a `.yml`);
 * - notices: keys that aren't one of the kind's fields, and aliases.
 */
final readonly class MediaMetadataCheck
{
	public function __construct(
		private Paths $paths,
		private MediaMetadataStore $store,
		private MediaResolver $resolver,
		private MediaSchemas $schemas,
		private DataLoader $data,
		private AppConfig $app
	) {}

	/**
	 * Checks every metadata file, returning the number checked and each
	 * one's violations, by its path from the site root.
	 *
	 * @return array{int, array<string, list<Violation>>}
	 */
	public function check(): array
	{
		$files      = $this->store->files();
		$violations = [];

		foreach ($files as $key => $file) {
			$path = $this->paths->relative($file['path']);

			$violations[$path] = $this->checkFile((string) $key, $file['path']);

			foreach ($file['shadowed'] as $hidden) {
				$violations[$this->paths->relative($hidden)] = [new Violation(Linter::FILE, sprintf('is hidden by %s, which is read instead; merge them into one file.', basename($file['path'])), Severity::Warning)];
			}
		}

		return [count($files) + array_sum(array_map(static fn (array $file): int => count($file['shadowed']), $files)), $violations];
	}

	/**
	 * Checks one metadata file against the media file it describes.
	 *
	 * @return list<Violation>
	 */
	private function checkFile(string $key, string $path): array
	{
		try {
			$data = $this->data->loadFile($path);
		} catch (Throwable $error) {
			// The loader names the file; the report already does.
			$message = $error->getMessage();
			$message = str_starts_with($message, "{$path}: ") ? substr($message, strlen($path) + 2) : $message;

			return [new Violation(Linter::FILE, sprintf('can\'t be read, so the file has no details: %s', $message))];
		}

		if ($data !== [] && array_is_list($data)) {
			return [new Violation(Linter::FILE, 'isn\'t a map of fields, so the file has no details.')];
		}

		$media = $this->resolver->fromKey($key);

		if ($media === null) {
			return [$this->orphan($key)];
		}

		return $this->schemas->forFile($media)->resolve(MediaMetadata::fromArray($data)->fields(), new FieldContext($this->app->timezone()))->violations;
	}

	/**
	 * The warning for a metadata file whose media file is gone, or isn't
	 * one the site allows.
	 */
	private function orphan(string $key): Violation
	{
		$media = "{$this->paths->media}/{$key}";
		$shown = $this->paths->relative($media);

		return new Violation(Linter::FILE, is_file($media)
			? sprintf('describes %s, which isn\'t a type of media the site allows.', $shown)
			: sprintf('describes %s, which isn\'t there; move this file with its media file, or delete it.', $shown), Severity::Warning);
	}
}
