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

use Blush\Content\Source\ContentFiles;
use Blush\Content\Source\FilesystemSource;
use Blush\Field\Violation;
use Blush\Field\ViolationKind;
use Blush\Translation\Messages;
use Blush\Translation\Text;

/**
 * Finds files in `user/content` in the formats Blush read before entries
 * became Markdown only (D-501): `.markdown`, `.html`, `.json`, `.yaml`,
 * and `.yml`. They aren't read any more, so each is an error, keyed by
 * its path in the content folder, saying how to make it a `.md` file.
 * Only files have formats, so it checks only the filesystem driver's
 * content (D-642).
 */
final readonly class FormatCheck
{
	public function __construct(
		private ContentFiles $contentFiles,
		private Messages $messages
	) {}

	/**
	 * Checks the content folder.
	 *
	 * @return array<string, list<Violation>>
	 */
	public function check(): array
	{
		$source = $this->contentFiles->kept() ? $this->contentFiles->source() : null;

		if (! $source instanceof FilesystemSource) {
			return [];
		}

		$violations = [];

		foreach ($source->others() as $path) {
			$message = $this->message(strtolower(pathinfo($path, PATHINFO_EXTENSION)));

			if ($message !== null) {
				$violations[$path] = [new Violation(Linter::FILE, $message, kind: ViolationKind::Unreadable)];
			}
		}

		return $violations;
	}

	/**
	 * Returns what to do with a file of an extension, or `null` for one
	 * that was never content.
	 */
	private function message(string $extension): ?Text
	{
		return match ($extension) {
			'markdown'            => $this->messages->t('content.format_check.markdown', 'isn\'t read: entries are .md files. Rename it to .md.'),
			'html'                => $this->messages->t('content.format_check.html', 'isn\'t read: entries are .md files. Rename it to .md; HTML in a Markdown body still renders.'),
			'json', 'yaml', 'yml' => $this->messages->t('content.format_check.data', 'isn\'t read: entries are .md files. Move its keys into the front matter of a .md file, with its body after.'),
			default               => null
		};
	}
}
