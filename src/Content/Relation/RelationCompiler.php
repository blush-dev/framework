<?php

/**
 * Relation compiler.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Relation;

use Blush\Content\EntryFields;
use Blush\Content\Type\Collection;
use Blush\Content\Type\ContentType;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Type\Tree;
use Blush\Field\Fields\ReferenceField;

/**
 * Makes a site's relations (D-585): the ones it defines (D-593: from
 * extensions, config, and `user/data/relations`, which the types carry),
 * then the ones its content types already say, as presets, so existing
 * content and config keep working (D-078):
 *
 * - **A `reference` field** with a `to` (in a type's own fields or field
 *   sets) is a `Reference` relation; one whose type doesn't exist is
 *   left out (`content:lint` reports it).
 * - **A tree** and **a hierarchical collection** have a `Parent` relation
 *   named `parent`, filed in `refs` (D-591); a tree's folder is its
 *   written form.
 * - **Every type** has a `Translation` relation named `translation_of`.
 */
final readonly class RelationCompiler
{
	/**
	 * The name of the relation to an entry's parent.
	 */
	public const string PARENT = 'parent';

	/**
	 * Returns the site's relations.
	 *
	 * @param  iterable<Relation> $extra More relations, beyond the ones the types carry.
	 * @throws InvalidRelation When they don't fit together.
	 */
	public function compile(ContentTypes $types, iterable $extra = []): Relations
	{
		$relations = [];

		foreach ($types->relations() as $relation) {
			$relations[] = $relation;
		}

		foreach ($types->all() as $type) {
			$own = [];

			foreach ($types->relations() as $relation) {
				if ($relation->isFrom($type->name)) {
					array_push($own, $relation->name, $relation->field);
				}
			}

			if ($type instanceof Tree || ($type instanceof Collection && $type->hierarchical)) {
				$relations[] = new Relation(self::PARENT, RelationKind::Parent, [$type->name], [$type->name], label: 'Parent');
				$own[]       = self::PARENT;
			}

			$relations[] = new Relation(EntryFields::TRANSLATION_OF, RelationKind::Translation, [$type->name], [$type->name], label: 'Original');

			foreach ($types->schema($type->name)->fields as $field) {
				if ($field instanceof ReferenceField && $field->to !== '' && $types->has($field->to) && ! in_array($field->name, $own, true)) {
					$relations[] = self::reference($type, $field);
				}
			}
		}

		foreach ($extra as $relation) {
			$relations[] = $relation;
		}

		return new Relations($relations, array_keys($types->all()));
	}


	/**
	 * Returns a reference field's relation.
	 */
	private static function reference(ContentType $type, ReferenceField $field): Relation
	{
		return new Relation(
			$field->name,
			RelationKind::Reference,
			[$type->name],
			[$field->to],
			aliases: $field->aliases,
			multiple: $field->multiple,
			ordered: true,
			min: $field->required ? 1 : 0,
			label: $field->label
		);
	}
}
