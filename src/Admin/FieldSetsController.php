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
use Blush\Content\Type\ContentType;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Type\ContentTypeTarget;
use Blush\Content\Type\DataFieldSetWriter;
use Blush\Content\Type\InvalidContentType;
use Blush\Core\Paths;
use Blush\Field\Field;
use Blush\Field\FieldConfig;
use Blush\Field\FieldSet;
use Blush\Field\FieldSetOrigin;
use Blush\Http\Response;
use Blush\Http\Status;

/**
 * Answers `GET {path}/api/fields/sets` (D-337): the site's field sets, for
 * Structure → Fields. Each set has its `name`, `label`, `description`,
 * `origin` (`extension`, `config`, or `data`), whether it's `editable`
 * (a data set, while `user/data/fields` is read), its data `file` (or
 * `null`), its `targets` (each `{"key", "label", "found"}`, `found` being
 * whether the place exists, labeled with the type's plural name when it
 * does), and how many `fields` it has. Beside them, `create` says whether
 * sets can be made here, and `targets` lists every place a set can
 * attach to (`{"key", "label"}`: each content type, by plural name).
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
		private Paths $paths
	) {}

	public function __invoke(): ResponseInterface
	{
		$sets = array_values(array_map(fn (FieldSet $set): array => $this->summary($this->types, $set), $this->types->sets->all()));

		return Response::json([
			'sets'    => $sets,
			'create'  => $this->config->dataSets,
			'targets' => self::targets($this->types)
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
			'options' => self::targets($types)
		];
	}

	/**
	 * Every place a set can attach to: each content type.
	 *
	 * @return list<array{key: string, label: string}>
	 */
	private static function targets(ContentTypes $types): array
	{
		$targets = array_map(static fn (ContentType $type): array => [
			'key'   => ContentTypeTarget::keyFor($type->name),
			'label' => $type->labels->plural
		], array_values($types->all()));

		usort($targets, static fn (array $a, array $b): int => strnatcasecmp($a['label'], $b['label']));

		return $targets;
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
			'origin'      => $origin->value,
			'editable'    => $file !== null && $this->config->dataSets,
			'file'        => $file === null ? null : $this->paths->relative($file),
			'targets'     => array_map(static function (string $key) use ($types): array {
				[$kind, $name] = explode(':', $key, 2);
				$type          = $kind === ContentTypeTarget::KIND ? $types->find($name) : null;

				return ['key' => $key, 'label' => $type?->labels->plural ?? $key, 'found' => $type !== null];
			}, $set->targets),
			'fields'      => count($set->schema->fields)
		];
	}
}
