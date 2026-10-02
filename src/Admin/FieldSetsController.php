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
use Blush\Content\Type\ContentTypes;
use Blush\Content\Type\DataFieldSetWriter;
use Blush\Content\Type\InvalidContentType;
use Blush\Core\Paths;
use Blush\Field\Field;
use Blush\Field\FieldConfig;
use Blush\Field\FieldSet;
use Blush\Field\FieldSetOrigin;
use Blush\Field\FieldSlot;
use Blush\Field\FieldTargets;
use Blush\Field\FieldTargetSource;
use Blush\Field\InvalidSchema;
use Blush\Http\Response;
use Blush\Http\Status;

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
 * same way, with its `fields` as definitions (`Field::toArray()`, without
 * classes) in place of the count. `detail()` describes a set among any
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
		private Paths $paths
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
			'fields'  => array_values(array_map(static fn (Field $field): array => array_diff_key($field->toArray(), ['class' => true]), $set->schema->fields)),
			'options' => $this->targets(),
			'kinds'   => $this->kinds()
		];
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
			$file = $origin === FieldSetOrigin::Data ? $this->writer->path($set->name) : null;
		} catch (InvalidContentType) {
			$file = null;
		}

		return [
			'name'        => $set->name,
			'label'       => $set->label,
			'description' => $set->description,
			'kind'        => $set->kind(),
			'slot'        => $this->slot($set),
			'origin'      => $origin->value,
			'editable'    => $file !== null && $this->config->dataSets,
			'file'        => $file === null ? null : $this->paths->relative($file),
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
