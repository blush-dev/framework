<?php

/**
 * File menu refs command.
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
use Blush\Console\Outcome;
use Blush\Console\Output;
use Blush\Console\StatusLine;
use Blush\Core\Framework;
use Blush\Menu\MenuException;
use Blush\Menu\MenuRefs;

/**
 * Lists the menus with items that link an entry without its id filed
 * in `ref` (D-676), or with a readable value that no longer names it.
 * `--write` files them. Without it, it fails when any is listed.
 */
#[Command('menu:refs', 'Check that menu links to entries are filed with their ids, and file them.')]
final readonly class FileMenuRefs
{
	public function __construct(private MenuRefs $refs)
	{}

	/**
	 * @throws InvalidInput
	 */
	public function __invoke(
		Output $output,
		#[Option('File the links of each menu listed.')] bool $write = false
	): ExitCode {
		$steps = $output->steps();

		try {
			if ($write) {
				$done = $this->refs->file($steps(...));

				$steps->finish();

				$output->status([
					...array_map(static fn (string $name): StatusLine => new StatusLine(Outcome::Pass, 'filed', $name), $done['filed']),
					...array_map(static fn (string $name, string $message): StatusLine => new StatusLine(Outcome::Failure, 'failed', "{$name}: {$message}"), array_keys($done['failed']), array_values($done['failed']))
				]);

				if ($done['failed'] !== []) {
					return ExitCode::Failure;
				}
			}

			$stale = $this->refs->report();
		} catch (MenuException $error) {
			$steps->finish();

			throw new InvalidInput($error->getMessage(), 0, $error);
		}

		$output->status(array_map(
			static fn (string $name, array $positions): StatusLine => new StatusLine(Outcome::Failure, 'unfiled', sprintf('%s (%s %s)', $name, count($positions) === 1 ? 'item' : 'items', implode(', ', $positions))),
			array_keys($stale),
			array_values($stale)
		));

		$count = count($stale);

		if ($count === 0) {
			$output->success('Every menu link to an entry is filed with its id.');

			return ExitCode::Success;
		}

		$output->error(Output::count($count, 'menu has', 'menus have') . ' links not filed with their ids.', 'File them with `' . Framework::BINARY . ' menu:refs --write`.');

		return ExitCode::Failure;
	}
}
