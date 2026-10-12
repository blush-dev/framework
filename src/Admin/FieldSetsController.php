<?php

/**
 * Admin field sets controller.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin;

use Psr\Http\Message\ResponseInterface;
use Blush\Content\Record\EntryTable;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Type\DataFieldSetWriter;
use Blush\Content\Type\InvalidContentType;
use Blush\Field\Field;
use Blush\Field\Fields\ObjectField;
use Blush\Field\FieldConfig;
use Blush\Field\FieldSet;
use Blush\Field\FieldSetOrigin;
use Blush\Field\FieldSlot;
use Blush\Field\FieldTargets;
use Blush\Field\FieldTargetSource;
use Blush\Field\InvalidSchema;
use Blush\Http\Response;
use Blush\Http\Status;
use Blush\Storage\Record\RecordException;
use Blush\Storage\Record\RecordStores;

/**
 * Answers `GET {path}/api/fields/sets` (D-337): the site's field sets, for
 * Structure → Fields. Each set has its `name`, `label`, `description`,
 * the `kind` of place its targets are, its `slot` (D-347: the one it's
 * in, its kind's default when it names none or one the kind lacks), its
 * `origin` (`extension`, `config`, or `data`), whether it's `editable` (a
 * data set, while `user/data/fields` is read), its data `file` (or
 * `null`), its `targets` (each `{"key", "label", "found"}`, `found` being
 * whether the site has the place, labeled with its name when it does),
 * and how many `fields` it has. Beside them, `create` says whether sets
 * can be made here, and `targets` lists every place a set can attach to
 * (`{"key", "label", "group", "kind"}`: each content type by plural
 * name, then each kind of media file and Settings screen; `group` names
 * the kind of place), and `kinds` lists each kind with its `slots`
 * (`{"name", "label", "description"}`, the first its default).
 *
 * `GET {path}/api/fields/sets/{name}` (`show()`) describes one set the
 * same way, with its `fields` as forms take them (`Field::toForm()`: each
 * with its control, D-769, and a choice's option labels as `choices`) in
 * place of the count, and `counts`: how many entries of its
 * types have a value for each of its fields, by name, and for each
 * field in a group by both keys (`details.servings`, D-780; read from the
 * content records, so removing a field or changing its key can say what
 * it leaves behind). `detail()` describes a set among any
 * set of types, so a change can be answered with the types it made
 * (`FieldSetEditController`).
 */
final readonly class FieldSetsController
{
	public function __construct(
		private ContentTypes $types,
		private FieldConfig $config,
		private DataFieldSetWriter $writer,
		private FieldTargets $targets,
		private RecordStores $stores
	) {}

	public function __invoke(): ResponseInterface
	{
		$sets = array_values(array_map(fn (FieldSet $set): array => $this->summary($this->types, $set), $this->types->sets->all()));

		return Response::json([
			'sets'    => $sets,
			'create'  => $this->config->dataSets,
			'targets' => $this->targets(),
			'kinds'   => $this->kinds()
		], headers: ['Cache-Control' => 'no-store']);
	}

	public function show(string $name): ResponseInterface
	{
		$set = $this->types->sets->find($name);

		if ($set === null) {
			return Response::json(['error' => sprintf('There\'s no "%s" field set.', $name)], Status::NotFound, ['Cache-Control' => 'no-store']);
		}

		return Response::json($this->detail($this->types, $set), headers: ['Cache-Control' => 'no-store']);
	}

	/**
	 * Describes a set among a set of types, as `show()` does.
	 *
	 * @return array<string, mixed>
	 */
	public function detail(ContentTypes $types, FieldSet $set): array
	{
		return [
			...$this->summary($types, $set),
			'fields'  => array_values(array_map(static fn (Field $field): array => $field->toForm(), $set->schema->fields)),
			'counts'  => (object) $this->counts($set),
			'options' => $this->targets(),
			'kinds'   => $this->kinds()
		];
	}

	/**
	 * How many entries of a set's types have a value for each of its
	 * fields (by its name or an alias), by name, and for a group's
	 * fields by the group's key and theirs.
	 *
	 * @return array<string, int>
	 */
	private function counts(FieldSet $set): array
	{
		$types  = array_map(static fn (string $target): string => substr($target, 5), array_filter($set->targets, static fn (string $target): bool => str_starts_with($target, 'type:')));
		$counts = array_fill_keys(array_map(static fn (Field $field): string => $field->name, array_values($set->schema->fields)), 0);

		if ($types === []) {
			return $counts;
		}

		try {
			$records = $this->stores->query(EntryTable::table())->withoutContent()->get();
		} catch (RecordException) {
			return $counts;
		}

		foreach ($records as $record) {
			$front = $record->fields['fields'] ?? null;

			if (! in_array($record->fields['type'] ?? null, $types, true) || ! is_array($front)) {
				continue;
			}

			self::count($set->schema->fields, $front, '', $counts);
		}

		return $counts;
	}

	/**
	 * Counts the fields a map has values for, and the fields of its
	 * groups, under the key they're at.
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
	 * Every place a set can attach to, kind by kind.
	 *
	 * @return list<array{key: string, label: string, group: string}>
	 */
	private function targets(): array
	{
		$targets = [];

		try {
			foreach ($this->targets->sources() as $source) {
				foreach ($source->fieldTargets() as $target) {
					$targets[] = ['key' => $target->key(), 'label' => $target->label(), 'group' => $source->label(), 'kind' => $source->kind()];
				}
			}
		} catch (InvalidSchema) {
			return $targets;
		}

		return $targets;
	}

	/**
	 * Every kind of place, with its slots (the first is its default).
	 *
	 * @return list<array{kind: string, label: string, slots: list<array{name: string, label: string, description: string}>}>
	 */
	private function kinds(): array
	{
		try {
			$sources = $this->targets->sources();
		} catch (InvalidSchema) {
			return [];
		}

		return array_values(array_map(static fn (FieldTargetSource $source): array => [
			'kind'  => $source->kind(),
			'label' => $source->label(),
			'slots' => array_map(static fn (FieldSlot $slot): array => $slot->toArray(), $source->slots())
		], $sources));
	}

	/**
	 * The slot a set is in: the one it names, when its kind offers it, or
	 * the kind's default; as written for a kind the site doesn't have.
	 */
	private function slot(FieldSet $set): ?string
	{
		try {
			return $this->targets->slotFor($set)->name ?? $set->slot;
		} catch (InvalidSchema) {
			return $set->slot;
		}
	}

	/**
	 * A set as the list describes it.
	 *
	 * @return array<string, mixed>
	 */
	private function summary(ContentTypes $types, FieldSet $set): array
	{
		$origin = $types->sets->origin($set->name);

		try {
			$file = $origin === FieldSetOrigin::Data ? $this->writer->location($set->name) : null;
		} catch (InvalidContentType) {
			$file = null;
		}

		return [
			'name'        => $set->name,
			'label'       => $set->label,
			'description' => $set->description,
			'kind'        => $set->kind(),
			'slot'        => $this->slot($set),
			'position'    => $set->position,
			'origin'      => $origin->value,
			'editable'    => $file !== null && $this->config->dataSets,
			'file'        => $file,
			'targets'     => array_map(function (string $key): array {
				try {
					$target = $this->targets->find($key);
				} catch (InvalidSchema) {
					$target = null;
				}

				return ['key' => $key, 'label' => $target?->label() ?? $key, 'found' => $target !== null];
			}, $set->targets),
			'fields'      => count($set->schema->fields)
		];
	}
}
