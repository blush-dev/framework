<?php

/**
 * Field set check.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Lint;

use Blush\Data\DataStore;
use Blush\Field\FieldSetLoader;
use Blush\Field\FieldSetOrigin;
use Blush\Field\FieldSets;
use Blush\Field\FieldTargets;
use Blush\Field\InvalidSchema;
use Blush\Field\Severity;
use Blush\Field\Violation;

/**
 * Checks the field sets against the places they attach to (D-337, D-341):
 *
 * - errors: a set whose fields don't fit a target, such as a field name
 *   a media kind's built-in fields already use (a content type's stop
 *   the site at load, so they don't get here), or a kind's targets taken
 *   together (two settings screens' sets using one name, D-343);
 * - notices: a target the site doesn't have (perhaps a type that's
 *   turned off), or a kind of target it has none of, and a `slot` its
 *   kind doesn't offer (in the kind's default, D-347).
 *
 * Each set's problems are keyed by its file (a data set's
 * `user/data/fields` file, `config/fields.php`), or by the set's name
 * for an extension's.
 */
final readonly class FieldSetCheck
{
	/**
	 * The field its violations use.
	 */
	public const string FIELD = 'targets';

	public function __construct(
		private FieldSets $sets,
		private FieldTargets $targets,
		private DataStore $data
	) {}

	/**
	 * Checks every set.
	 *
	 * @return array<string, list<Violation>>
	 */
	public function check(): array
	{
		$violations = [];

		try {
			$targets = $this->targets->all();
		} catch (InvalidSchema $e) {
			return ['config/fields.php' => [new Violation(self::FIELD, $e->getMessage())]];
		}

		foreach ($this->sets as $name => $set) {
			foreach ($set->targets as $target) {
				[$kind] = explode(':', $target, 2);

				$message = match (true) {
					! $this->targets->hasKind($kind) => sprintf('"%s" names %s, but fields can\'t attach to a "%s" yet.', $name, $target, $kind),
					! isset($targets[$target])       => sprintf('"%s" names %s, which the site doesn\'t have, so it isn\'t used there.', $name, $target),
					default                          => null
				};

				if ($message !== null) {
					$violations[$this->key($name)][] = new Violation(self::FIELD, $message, Severity::Notice);
				}
			}

			$kind = $set->kind() ?? '';
			$in   = $set->slot === null ? null : $this->targets->slotFor($set);

			if ($in !== null && $in->name !== $set->slot) {
				$violations[$this->key($name)][] = new Violation('slot', sprintf('"%s" has the slot "%s", which %s don\'t offer, so it\'s in "%s".', $name, $set->slot, mb_strtolower($this->targets->sources()[$kind]->label()), $in->name), Severity::Notice);
			}
		}

		try {
			$problems = $this->targets->problems($this->sets);
		} catch (InvalidSchema) {
			$problems = [];
		}

		foreach ($problems as $name => $messages) {
			foreach ($messages as $message) {
				$violations[$this->key($name)][] = new Violation(self::FIELD, $message);
			}
		}

		return $violations;
	}

	/**
	 * Returns where a set's problems are listed.
	 */
	private function key(string $name): string
	{
		return match ($this->sets->origin($name)) {
			FieldSetOrigin::Data      => $this->dataFile($name),
			FieldSetOrigin::Config    => 'config/fields.php',
			FieldSetOrigin::Extension => sprintf('the "%s" field set', $name)
		};
	}

	/**
	 * Returns a data set's file from the site's root.
	 */
	private function dataFile(string $name): string
	{
		return $this->data->location(FieldSetLoader::DATA_DIRECTORY . "/{$name}");
	}
}
