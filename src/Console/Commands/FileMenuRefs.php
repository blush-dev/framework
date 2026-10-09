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
use Blush\Console\Output;
use Blush\Console\Style;
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
		try {
			if ($write) {
				$done = $this->refs->file();

				foreach ($done['filed'] as $name) {
					$output->line(sprintf('%s  %s', $output->style('filed  ', Style::Green), $name));
				}

				foreach ($done['failed'] as $name => $message) {
					$output->line(sprintf('%s  %s: %s', $output->style('failed ', Style::Red), $name, $message));
				}

				if ($done['failed'] !== []) {
					return ExitCode::Failure;
				}
			}

			$stale = $this->refs->report();
		} catch (MenuException $error) {
			throw new InvalidInput($error->getMessage(), 0, $error);
		}

		foreach ($stale as $name => $positions) {
			$output->line(sprintf('%s  %s (%s %s)', $output->style('unfiled', Style::Red), $name, count($positions) === 1 ? 'item' : 'items', implode(', ', $positions)));
		}

		$count = count($stale);

		if ($count === 0) {
			$output->success('Every menu link to an entry is filed with its id.');

			return ExitCode::Success;
		}

		$output->error(sprintf('%d %s links not filed with their ids; file them with --write.', $count, $count === 1 ? 'menu has' : 'menus have'));

		return ExitCode::Failure;
	}
}
