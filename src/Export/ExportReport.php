<?php

/**
 * Export report.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Export;

use Blush\Content\Index\IndexReport;

/**
 * What an export did: the indexing run, how many URLs were exported and
 * files copied, how many files were written or already current, and
 * what was left out.
 *
 * - `redirects`: URLs that answered with a redirect, and where to.
 * - `skipped`: URLs a source listed that aren't pages (an empty date
 *   archive, say). They're expected, and only reported verbosely.
 * - `broken`: links on exported pages to URLs that aren't pages, and
 *   the page that linked to each.
 * - `failures`: URLs that errored or couldn't be written, and why.
 * - `notices`: what host files couldn't express, and host files a
 *   `public/` file replaced.
 *
 * `rendered` is `false` when `--incremental` found nothing changed and
 * kept the previous pages (D-139).
 */
final readonly class ExportReport
{
	/**
	 * @param list<string>          $removed   Files removed from the output folder.
	 * @param array<string, string> $redirects Path => location.
	 * @param list<string>          $skipped
	 * @param array<string, string> $broken    Path => the page linking to it.
	 * @param array<string, string> $failures  Path => message.
	 * @param list<string>          $notices
	 */
	public function __construct(
		public string $path,
		public string $url,
		public ?IndexReport $index = null,
		public int $pages = 0,
		public int $files = 0,
		public int $written = 0,
		public int $unchanged = 0,
		public array $removed = [],
		public array $redirects = [],
		public array $skipped = [],
		public array $broken = [],
		public array $failures = [],
		public array $notices = [],
		public bool $rendered = true,
		public int $milliseconds = 0
	) {}

	/**
	 * Returns whether everything was exported: no URL failed, and every
	 * content file was indexed.
	 */
	public function isSuccessful(): bool
	{
		return $this->failures === [] && ($this->index === null || $this->index->failures === []);
	}
}
