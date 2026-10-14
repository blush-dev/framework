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

use Blush\Field\FieldSet;
use Blush\Field\FieldSetLoader;
use Blush\Field\FieldSets;
use Blush\Field\FieldTargets;
use Blush\Field\InvalidSchema;
use Blush\Field\Severity;
use Blush\Field\Violation;
use Blush\Translation\Messages;

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
		private FieldSetLoader $loader,
		private Messages $messages
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
				[$kind, $place] = explode(':', $target, 2);

				$message = match (true) {
					! $this->targets->hasKind($kind)                     => $this->messages->t('content.field_set_check.unknown_kind', '"{set}" names {target}, but fields can\'t attach to a "{kind}" yet.', set: $name, target: $target, kind: $kind),
					$place !== FieldSet::EVERY && ! isset($targets[$target]) => $this->messages->t('content.field_set_check.unknown_target', '"{set}" names {target}, which the site doesn\'t have, so it isn\'t used there.', set: $name, target: $target),
					default                                              => null
				};

				if ($message !== null) {
					$violations[$this->key($name)][] = new Violation(self::FIELD, $message, Severity::Notice);
				}
			}

			$kind = $set->kind() ?? '';
			$in   = $set->slot === null ? null : $this->targets->slotFor($set);

			if ($in !== null && $in->name !== $set->slot) {
				$violations[$this->key($name)][] = new Violation('slot', $this->messages->t('content.field_set_check.slot', '"{set}" has the slot "{slot}", which {kind} don\'t offer, so it\'s in "{in}".', set: $name, slot: $set->slot, kind: mb_strtolower((string) $this->targets->sources()[$kind]->label()), in: $in->name), Severity::Notice);
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
	 * Returns where problems are listed: a set's file, or the fields an
	 * extension registers.
	 */
	private function key(string $name): string
	{
		return $this->sets->find($name) === null ? (string) $this->messages->t('content.field_set_check.registered', 'the fields {extension} registers', extension: $name) : $this->dataFile($name);
	}

	/**
	 * Returns a data set's file from the site's root.
	 */
	private function dataFile(string $name): string
	{
		return $this->loader->records()->location($name);
	}
}
