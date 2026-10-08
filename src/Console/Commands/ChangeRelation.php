<?php

/**
 * Change relation command.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Console\Commands;

use Blush\Console\Attributes\Argument;
use Blush\Console\Attributes\Command;
use Blush\Console\Attributes\Option;
use Blush\Console\ExitCode;
use Blush\Console\Output;
use Blush\Content\Relation\DataRelationWriter;
use Blush\Content\Relation\InvalidRelation;
use Blush\Content\Relation\Relation;
use Blush\Content\Relation\RelationChanges;
use Blush\Content\Type\ContentTypeLoader;
use Blush\Content\Type\InvalidContentType;
use Blush\Content\Writer\WriteException;

/**
 * Changes a relation that entries use (D-600), as the admin's
 * Relationships form does. Without options, it says how many entries
 * have values in it. `--key` gives it a new front matter key: the old
 * one is kept as an alias, or with `--rewrite` each entry's values move
 * to the new key. `--remove` deletes its file in `user/data/relations`;
 * entries keep their values unless `--strip` removes them, with their
 * ids. `--strip` alone removes the values of a relation that stays.
 */
#[Command('content:relation', 'Show a relation\'s use, give it a new key, or remove it, fitting the files to the change.')]
final readonly class ChangeRelation
{
	public function __construct(
		private ContentTypeLoader $loader,
		private DataRelationWriter $relations,
		private RelationChanges $changes
	) {}

	public function __invoke(
		Output $output,
		#[Argument('The relation\'s name.')] string $name,
		#[Option('A new front matter key; the old one is kept as an alias unless --rewrite.')] ?string $key = null,
		#[Option('Move each entry\'s values to the new key.')] bool $rewrite = false,
		#[Option('Delete the relation\'s file in user/data/relations.')] bool $remove = false,
		#[Option('Remove its values, with their ids, from the entries that have them.')] bool $strip = false
	): ExitCode {
		try {
			$relation = $this->loader->load()->relations()[$name] ?? null;

			if ($relation === null) {
				$output->error(sprintf('There\'s no "%s" relation.', $name));

				return ExitCode::Failure;
			}

			$uses = count($this->changes->uses($relation));

			if ($key === null && ! $remove && ! $strip) {
				$output->line(sprintf('"%s" is written under `%s`; %d %s values in it.', $name, $relation->field, $uses, $uses === 1 ? 'entry has' : 'entries have'));

				return ExitCode::Success;
			}

			if (($key !== null || $remove) && $this->relations->location($name) === null) {
				$output->error(sprintf('"%s" isn\'t defined in user/data/relations, so it\'s changed where it\'s defined.', $name));

				return ExitCode::Failure;
			}

			if ($strip) {
				$stripped = count($this->changes->strip($relation));

				$output->success(sprintf('Removed its values from %d %s.', $stripped, $stripped === 1 ? 'entry' : 'entries'));
			}

			if ($remove) {
				$this->relations->delete($name);
				$output->success(sprintf('Removed "%s".', $name));

				return ExitCode::Success;
			}

			if ($key !== null) {
				$changed = $this->changes->apply($relation, Relation::fromArray([...$relation->toArray(), 'field' => $key]), $rewrite);

				$this->relations->update($changed);
				$output->success($rewrite
					? sprintf('"%s" is written under `%s`, moved in %d %s.', $name, $key, $uses, $uses === 1 ? 'entry' : 'entries')
					: sprintf('"%s" is written under `%s`; `%s` is still read.', $name, $key, implode('`, `', array_diff($changed->aliases, $relation->aliases))));
			}
		} catch (InvalidRelation | InvalidContentType | WriteException $e) {
			$output->error($e->getMessage());

			return ExitCode::Failure;
		}

		return ExitCode::Success;
	}
}
