<?php

/**
 * Field library check.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Lint;

use Blush\Content\Type\ContentTypes;
use Blush\Content\Type\DefinitionTables;
use Blush\Field\FieldLibrary;
use Blush\Field\FieldLibraryLoader;
use Blush\Field\FieldTargets;
use Blush\Field\InvalidSchema;
use Blush\Field\Severity;
use Blush\Field\Violation;
use Blush\Translation\Messages;

/**
 * Checks the field library against the places that carry its fields
 * (D-858):
 *
 * - errors: a field left off a place, because a field before it declares
 *   its key or the place won't take it (a media kind's built-in field
 *   name, say), or a registered field left off for the same reasons;
 * - notices: a type's layout naming a field the library doesn't have.
 *
 * Problems are keyed by the file they're fixed in: a library field's
 * (`user/data/fields/{name}.json`), a type's (`user/data/types`), or
 * the fields an extension registers.
 */
final readonly class FieldLibraryCheck
{
	/**
	 * The field its violations use.
	 */
	public const string FIELD = 'fields';

	public function __construct(
		private FieldLibrary $library,
		private ContentTypes $types,
		private FieldTargets $targets,
		private FieldLibraryLoader $loader,
		private DefinitionTables $tables,
		private Messages $messages
	) {}

	/**
	 * Checks the library and every layout.
	 *
	 * @return array<string, list<Violation>>
	 */
	public function check(): array
	{
		$violations = [];

		foreach ($this->types->all() as $type) {
			foreach ($type->layout->names() as $name) {
				if ($this->library->find($name) === null) {
					$violations[$this->tables->types()->location($type->name)][] = new Violation(self::FIELD, $this->messages->t('content.field_library_check.unknown_field', '{types} place the "{field}" field, which the field library doesn\'t have, so it isn\'t shown.', types: $type->labels->plural, field: $name), Severity::Notice);
				}
			}
		}

		try {
			$problems = $this->targets->problems($this->library);
		} catch (InvalidSchema $e) {
			return [...$violations, 'config/fields.php' => [new Violation(self::FIELD, $e->getMessage())]];
		}

		foreach ($problems as $name => $messages) {
			foreach ($messages as $message) {
				$violations[$this->key($name)][] = new Violation(self::FIELD, $message);
			}
		}

		return $violations;
	}

	/**
	 * Returns where problems are listed: a library field's file, or the
	 * fields an extension registers.
	 */
	private function key(string $name): string
	{
		return $this->library->find($name) === null
			? (string) $this->messages->t('content.field_library_check.registered', 'the fields {extension} registers', extension: $name)
			: $this->loader->records()->location($name);
	}
}
