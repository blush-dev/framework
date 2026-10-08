<?php

/**
 * Ignored Site Health problems, in a file.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin;

use JsonException;
use Override;
use Blush\Core\Paths;
use Blush\Support\Filesystem;

/**
 * Keeps the ignored Site Health problems in
 * `user/data/health/ignored.json` (D-613): site data, kept with the
 * site, not derived from it as the last report is. A file that can't be
 * read ignores nothing.
 */
final readonly class FileIgnoredProblems implements IgnoredProblems
{
	public function __construct(
		private Paths $paths,
		private Filesystem $filesystem
	) {}

	/**
	 * Returns the file's path.
	 */
	public function path(): string
	{
		return "{$this->paths->data}/health/ignored.json";
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function all(): array
	{
		$contents = @file_get_contents($this->path());

		if ($contents === false) {
			return [];
		}

		try {
			$data = json_decode($contents, true, 8, JSON_THROW_ON_ERROR);
		} catch (JsonException) {
			return [];
		}

		$ignored = [];

		foreach (is_array($data) ? $data : [] as $key => $record) {
			if (is_string($key) && is_array($record) && is_string($record['by'] ?? null) && is_string($record['at'] ?? null)) {
				$ignored[$key] = ['by' => $record['by'], 'at' => $record['at']];
			}
		}

		return $ignored;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function ignore(string $key, string $by, string $at): void
	{
		$this->save([...$this->all(), $key => ['by' => $by, 'at' => $at]]);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function unignore(string $key): void
	{
		$ignored = $this->all();

		if (isset($ignored[$key])) {
			unset($ignored[$key]);
			$this->save($ignored);
		}
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function keep(array $keys): void
	{
		$ignored = $this->all();
		$kept    = array_intersect_key($ignored, array_flip($keys));

		if (count($kept) !== count($ignored)) {
			$this->save($kept);
		}
	}

	/**
	 * Writes the records, sorted by key, or removes the file when there
	 * are none.
	 *
	 * @param array<string, array{by: string, at: string}> $ignored
	 */
	private function save(array $ignored): void
	{
		if ($ignored === []) {
			@unlink($this->path());

			return;
		}

		ksort($ignored, SORT_STRING);

		$this->filesystem->writeAtomic($this->path(), json_encode($ignored, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n");
	}
}
