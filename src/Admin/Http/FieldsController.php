<?php

/**
 * Admin fields controller.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin\Http;

use Psr\Http\Message\ResponseInterface;
use Blush\Content\Record\EntryTable;
use Blush\Content\Type\ContentType;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Type\DataFieldWriter;
use Blush\Content\Type\InvalidContentType;
use Blush\Field\ControlDefaults;
use Blush\Field\Field;
use Blush\Field\FieldConfig;
use Blush\Field\Fields\ObjectField;
use Blush\Field\FieldTargets;
use Blush\Field\FormField;
use Blush\Field\InvalidSchema;
use Blush\Field\LibraryField;
use Blush\Http\Response;
use Blush\Http\Status;
use Blush\Storage\Record\RecordException;
use Blush\Storage\Record\RecordStores;
use Blush\Translation\Messages;
use Blush\Translation\Text;

/**
 * Answers `GET {path}/api/fields/library` (D-858): the site's field
 * library, for Structure → Fields. Each field has its `name` (its key),
 * `label`, `description`, whether it's `required`, field `type`, the `control` it's drawn with (by
 * key), whether it's `editable` (while `user/data/fields` is read), its
 * data `file`, the `types` that carry it (`{"name", "label"}`, by plural
 * name), and its `problems`: why it's left off a type (D-789). The library
 * is only the site's (D-790): fields core and extensions register aren't
 * listed. Beside them, `create` says whether fields can be made here.
 *
 * `GET {path}/api/fields/library/{name}` (`show()`) describes one field
 * the same way, with the `field` as forms take it (`FormField`: with its
 * control, D-769, and a choice's option labels as `choices`), and
 * `counts`: how many entries of the types that carry it have a value for
 * it, by its name, and for each field in an Object by both keys
 * (`details.servings`, D-780; read from the content records, so removing
 * a field inside it can say what it leaves behind). `detail()` describes
 * a field among any set of types, so a change can be answered with the
 * types it made (`FieldEditController`).
 */
final readonly class FieldsController
{
	public function __construct(
		private ContentTypes $types,
		private FieldConfig $config,
		private DataFieldWriter $writer,
		private FieldTargets $targets,
		private RecordStores $stores,
		private ControlDefaults $defaults,
		private Messages $messages
	) {}

	public function __invoke(): ResponseInterface
	{
		$problems = $this->problems($this->types);

		return Response::json([
			'fields' => array_values(array_map(fn (LibraryField $field): array => $this->summary($this->types, $field, $problems), $this->types->library->all())),
			'create' => $this->config->library
		], headers: ['Cache-Control' => 'no-store']);
	}

	public function show(string $name): ResponseInterface
	{
		$field = $this->types->library->find($name);

		if ($field === null) {
			return Response::json(['error' => $this->messages->t('admin.fields_controller.not_found', 'The field library has no "{name}" field.', name: $name)], Status::NotFound, ['Cache-Control' => 'no-store']);
		}

		return Response::json($this->detail($this->types, $field), headers: ['Cache-Control' => 'no-store']);
	}

	/**
	 * Describes a field among a set of types, as `show()` does.
	 *
	 * @return array<string, mixed>
	 */
	public function detail(ContentTypes $types, LibraryField $field): array
	{
		return [
			...$this->summary($types, $field, $this->problems($types)),
			'field'  => FormField::of($field->field, $field->controls, $this->defaults)->toArray(),
			'counts' => (object) $this->counts($types, $field)
		];
	}

	/**
	 * Returns the types that carry a library field, by plural name.
	 *
	 * @return list<ContentType>
	 */
	public static function typesWith(ContentTypes $types, string $name): array
	{
		$with = array_values(array_filter($types->all(), static fn (ContentType $type): bool => $type->layout->has($name)));

		usort($with, static fn (ContentType $a, ContentType $b): int => strnatcasecmp($a->labels->plural, $b->labels->plural));

		return $with;
	}

	/**
	 * How many entries of the types that carry a field have a value for it
	 * (by its name or an alias), and for an Object's fields by its key and
	 * theirs.
	 *
	 * @return array<string, int>
	 */
	private function counts(ContentTypes $types, LibraryField $field): array
	{
		$names  = array_map(static fn (ContentType $type): string => $type->name, self::typesWith($types, $field->name()));
		$counts = [$field->name() => 0];

		if ($names === []) {
			return $counts;
		}

		try {
			$records = $this->stores->query(EntryTable::table())->withoutContent()->get();
		} catch (RecordException) {
			return $counts;
		}

		foreach ($records as $record) {
			$front = $record->fields['fields'] ?? null;

			if (in_array($record->fields['type'] ?? null, $names, true) && is_array($front)) {
				self::count([$field->field], $front, '', $counts);
			}
		}

		return $counts;
	}

	/**
	 * Counts the fields a map has values for, and the fields of its
	 * Objects, under the key they're at.
	 *
	 * @param array<array-key, Field> $fields
	 * @param array<array-key, mixed> $data
	 * @param array<string, int>      $counts
	 */
	private static function count(array $fields, array $data, string $at, array &$counts): void
	{
		foreach ($fields as $field) {
			$key = array_find([$field->name, ...$field->aliases], static fn (string $key): bool => ! in_array($data[$key] ?? null, [null, '', []], true));
			$as  = $at === '' ? $field->name : "{$at}.{$field->name}";

			$counts[$as] ??= 0;

			if ($key === null) {
				continue;
			}

			$counts[$as]++;

			if ($field instanceof ObjectField && is_array($data[$key])) {
				self::count($field->schema->fields, $data[$key], $as, $counts);
			}
		}
	}

	/**
	 * Why library fields are left off the types that carry them (D-786),
	 * by field name.
	 *
	 * @return array<string, list<Text>>
	 */
	private function problems(ContentTypes $types): array
	{
		try {
			return $this->targets->problems($types->library);
		} catch (InvalidSchema) {
			return [];
		}
	}

	/**
	 * A field as the list describes it, with why it's left off types.
	 *
	 * @param  array<string, list<Text>> $problems
	 * @return array<string, mixed>
	 */
	private function summary(ContentTypes $types, LibraryField $field, array $problems): array
	{
		try {
			$file = $this->writer->location($field->name());
		} catch (InvalidContentType) {
			$file = null;
		}

		return [
			'name'        => $field->name(),
			'label'       => $field->field->label,
			'description' => $field->field->description,
			'required'    => $field->field->required,
			'type'        => $field->field->type(),
			'control'     => FormField::of($field->field, $field->controls, $this->defaults)->control::name(),
			'editable'    => $file !== null && $this->config->library,
			'file'        => $file,
			'types'       => array_map(static fn (ContentType $type): array => ['name' => $type->name, 'label' => $type->labels->plural], self::typesWith($types, $field->name())),
			'problems'    => $problems[$field->name()] ?? []
		];
	}
}
