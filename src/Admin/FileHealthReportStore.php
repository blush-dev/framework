<?php

/**
 * Site Health report file store.
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
 * Keeps Site Health's last report in `storage/health.json` (D-545). A
 * file that can't be read is no report, so the next visit checks again.
 */
final readonly class FileHealthReportStore implements HealthReportStore
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
		return "{$this->paths->storage}/health.json";
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function load(): ?array
	{
		$contents = @file_get_contents($this->path());

		if ($contents === false) {
			return null;
		}

		try {
			$report = json_decode($contents, true, 32, JSON_THROW_ON_ERROR);
		} catch (JsonException) {
			return null;
		}

		if (! is_array($report) || $report === [] || array_is_list($report)) {
			return null;
		}

		return array_filter($report, is_string(...), ARRAY_FILTER_USE_KEY);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function save(array $report): void
	{
		$this->filesystem->writeAtomic($this->path(), json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
	}
}
