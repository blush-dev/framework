<?php

/**
 * Content list command.
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
use Blush\Console\InvalidInput;
use Blush\Console\Output;
use Blush\Content\Entries;
use Blush\Content\Entry\Entry;
use Blush\Content\Status;
use Blush\Content\Type\ContentTypes;

/**
 * Lists the indexed entries, whatever their status or visibility, in file
 * order: all of them, or those of one type or status.
 */
#[Command('content:list', 'List the indexed entries.')]
final readonly class ListContent
{
	public function __construct(
		private Entries $content,
		private ContentTypes $types
	) {}

	/**
	 * @throws InvalidInput
	 */
	public function __invoke(
		Output $output,
		#[Option('Only entries of this type.')] ?string $type = null,
		#[Option('Only entries with this status.')] ?Status $status = null
	): ExitCode {
		$query = $this->content->query()->any()->limit(null);

		if ($type !== null) {
			if (! $this->types->has($type)) {
				throw new InvalidInput(sprintf('There is no "%s" content type; the types are %s.', $type, implode(', ', array_keys($this->types->all()))));
			}

			$query = $query->type($type);
		}

		if ($status !== null) {
			$query = $query->status($status);
		}

		$entries = $query->get();

		if ($entries->isEmpty()) {
			$output->comment('No entries found.');

			return ExitCode::Success;
		}

		$output->table(
			['Type', 'Key', 'Title', 'Status', 'Visibility', 'Published'],
			array_map(static fn (Entry $entry): array => [
				$entry->type->name,
				$entry->landing ? '(landing)' : $entry->key,
				$entry->title,
				$entry->status->value,
				$entry->visibility->value,
				$entry->published?->format('Y-m-d H:i') ?? ''
			], $entries->all())
		);

		$output->comment(sprintf('%d %s.', count($entries), count($entries) === 1 ? 'entry' : 'entries'));

		return ExitCode::Success;
	}
}
