<?php

/**
 * Content format check.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Lint;

use Blush\Core\Paths;
use Blush\Field\Violation;
use Blush\Support\Filesystem;

/**
 * Finds files in `user/content` in the formats Blush read before entries
 * became Markdown only (D-501): `.markdown`, `.html`, `.json`, `.yaml`,
 * and `.yml`. They aren't read any more, so each is an error, keyed by
 * its path in the content folder, saying how to make it a `.md` file.
 */
final readonly class FormatCheck
{
	public function __construct(
		private Paths $paths,
		private Filesystem $filesystem = new Filesystem()
	) {}

	/**
	 * Checks the content folder.
	 *
	 * @return array<string, list<Violation>>
	 */
	public function check(): array
	{
		$violations = [];

		foreach ($this->filesystem->files($this->paths->content) as $path => $file) {
			$message = self::message(strtolower(pathinfo($path, PATHINFO_EXTENSION)));

			if ($message !== null) {
				$violations[str_replace('\\', '/', $path)] = [new Violation(Linter::FILE, $message)];
			}
		}

		ksort($violations, SORT_STRING);

		return $violations;
	}

	/**
	 * Returns what to do with a file of an extension, or `null` for one
	 * that was never content.
	 */
	private static function message(string $extension): ?string
	{
		return match ($extension) {
			'markdown'            => 'isn\'t read: entries are .md files. Rename it to .md.',
			'html'                => 'isn\'t read: entries are .md files. Rename it to .md; HTML in a Markdown body still renders.',
			'json', 'yaml', 'yml' => 'isn\'t read: entries are .md files. Move its keys into the front matter of a .md file, with its body after.',
			default               => null
		};
	}
}
