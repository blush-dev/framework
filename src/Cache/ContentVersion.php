<?php

/**
 * Content version.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Cache;

use Throwable;
use Psr\Clock\ClockInterface;
use Blush\Content\Index\ContentIndex;
use Blush\Core\Paths;
use Blush\Support\Filesystem;

/**
 * One stored value that changes whenever what the site shows might have:
 * after the index changes, on publish, on `cache:clear`, and when a
 * scheduled entry's time comes. Every derived cache (pages, bodies,
 * tokens) keys on it, so invalidating them all is one small write, and
 * stale entries are simply never read again (`publish` and `cache:clear`
 * delete them).
 *
 * It lives in `storage/cache/content-version.json` with the next
 * scheduled time. Reading it is one small file per process. When that
 * time has passed, the version moves on by itself: to a hash of the old
 * version and the time, so every request that notices computes the same
 * new version, and the next scheduled time is found in the index.
 */
final class ContentVersion
{
	/**
	 * The file's name in `storage/cache`.
	 */
	public const string FILE = 'content-version.json';

	/**
	 * The version and next scheduled time, once read.
	 *
	 * @var ?array{version: string, scheduled: ?int}
	 */
	private ?array $state = null;

	public function __construct(
		private readonly Paths $paths,
		private readonly ClockInterface $clock,
		private readonly ContentIndex $index
	) {}

	/**
	 * Returns the file's path.
	 */
	public function path(): string
	{
		return "{$this->paths->cache}/" . self::FILE;
	}

	/**
	 * Returns the current version, moving it on first when a scheduled
	 * time has passed. A site without a version gets one.
	 */
	public function current(): string
	{
		$state = $this->state ??= $this->read() ?? $this->next(bin2hex(random_bytes(8)));
		$now   = $this->now();

		if ($state['scheduled'] !== null && $state['scheduled'] <= $now) {
			$state = $this->next(hash('xxh64', "{$state['version']}:{$state['scheduled']}"));
		}

		return $state['version'];
	}

	/**
	 * Returns the next scheduled time the version will change at, or
	 * `null`.
	 */
	public function scheduled(): ?int
	{
		$this->current();

		return $this->state['scheduled'] ?? null;
	}

	/**
	 * Replaces the version with a new one and returns it.
	 */
	public function bump(): string
	{
		return $this->next(bin2hex(random_bytes(8)))['version'];
	}

	/**
	 * Stores a new version with the index's next scheduled time. A
	 * failed write is ignored: the version still holds for this process,
	 * and the next one tries again.
	 *
	 * @return array{version: string, scheduled: ?int}
	 */
	private function next(string $version): array
	{
		$this->state = ['version' => $version, 'scheduled' => $this->index->snapshot()->nextScheduled($this->now())];

		try {
			new Filesystem()->writeAtomic($this->path(), json_encode($this->state, JSON_THROW_ON_ERROR) . "\n");
		} catch (Throwable) {
			// Tried again by the next process.
		}

		return $this->state;
	}

	/**
	 * Reads the stored state, or returns `null` when there's none.
	 *
	 * @return ?array{version: string, scheduled: ?int}
	 */
	private function read(): ?array
	{
		$contents = @file_get_contents($this->path());
		$data     = $contents === false ? null : json_decode($contents, true);

		if (! is_array($data) || ! is_string($data['version'] ?? null) || $data['version'] === '') {
			return null;
		}

		$scheduled = $data['scheduled'] ?? null;

		return ['version' => $data['version'], 'scheduled' => is_int($scheduled) ? $scheduled : null];
	}

	/**
	 * Returns the current Unix time.
	 */
	private function now(): int
	{
		return $this->clock->now()->getTimestamp();
	}
}
