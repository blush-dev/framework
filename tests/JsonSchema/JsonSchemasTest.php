<?php

/**
 * Manifest JSON Schema tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\JsonSchema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Core\Framework;
use Blush\Plugin\PluginManifest;
use Blush\Plugin\PluginSource;
use Blush\Field\Field;
use Blush\Field\FieldFactory;
use Blush\Field\FieldRegistrar;
use Blush\Field\FieldRegistry;
use Blush\Field\Fields\DateField;
use Blush\Field\Fields\EnumField;
use Blush\Field\Fields\ListField;
use Blush\Field\FieldType;
use Blush\Field\Schema;
use Blush\Content\Relation\Relation;
use Blush\JsonSchema\JsonSchemas;
use Blush\Menu\Link\MenuLink;
use Blush\Menu\Link\MenuLinkType;
use ReflectionClass;
use Blush\Theme\ThemeManifest;

#[CoversClass(JsonSchemas::class)]
#[CoversClass(Field::class)]
#[CoversClass(FieldType::class)]
#[CoversClass(Schema::class)]
#[CoversClass(MenuLink::class)]
final class JsonSchemasTest extends TestCase
{
	public function testCommittedSchemasAreCurrent(): void
	{
		foreach (new JsonSchemas()->all() as $file => $schema) {
			$this->assertStringEqualsFile(
				Framework::path(JsonSchemas::DIRECTORY . "/{$file}"),
				JsonSchemas::encode($schema),
				"{$file} is stale; run `composer schemas`."
			);
		}
	}

	public function testTheRelationSchemaDescribesEveryKeyARelationWrites(): void
	{
		$relation = Relation::fromArray([
			'name' => 'actors', 'kind' => 'reference', 'from' => ['movie'], 'to' => ['person'], 'field' => 'cast', 'aliases' => ['stars'],
			'label' => 'Cast', 'multiple' => true, 'ordered' => true, 'min' => 1, 'max' => 9, 'create' => true, 'translations' => 'add',
			'control' => 'cards', 'defaults' => ['tom'], 'inverse' => ['archive' => 'actors', 'label' => 'Acted in', 'types' => ['movie'], 'max' => 5]
		]);
		$properties = new JsonSchemas()->relation()['properties'] ?? null;
		$this->assertIsArray($properties);
		$inverseSchema = $properties['inverse'] ?? null;
		$this->assertIsArray($inverseSchema);
		$this->assertIsArray($inverseSchema['oneOf'] ?? null);
		$object = $inverseSchema['oneOf'][1] ?? null;
		$this->assertIsArray($object);
		$side = $object['properties'] ?? null;
		$this->assertIsArray($side);
		$keys     = array_keys($properties);
		$inverse  = array_keys($side);
		$written  = $relation->toArray();

		$this->assertSame([], array_diff(array_keys(array_diff_key($written, ['name' => true])), $keys), 'Every key a relation\'s file can hold (D-600).');
		$this->assertSame([], array_diff(array_keys(is_array($written['inverse'] ?? null) ? $written['inverse'] : []), $inverse));
		$this->assertContains('$schema', $keys, 'A file may name its schema.');
	}

	public function testPatternsWriteBackslashesAsHexEscapes(): void
	{
		// PhpStorm turns `\\` in a pattern into `\`, which breaks it (D-209).
		foreach (new JsonSchemas()->all() as $file => $schema) {
			array_walk_recursive($schema, function (mixed $value, int|string $key) use ($file): void {
				if ($key === 'pattern' && is_string($value)) {
					$this->assertStringNotContainsString('\\\\', $value, "{$file} has a pattern with an escaped backslash.");
				}
			});
		}
	}

	public function testDescribesEveryBuiltInFieldOption(): void
	{
		$registry = new FieldRegistry();
		new FieldRegistrar($registry)->register();
		$factory = new FieldFactory($registry);

		$definitions = [
			FieldType::Number->value    => ['integer' => true, 'min' => 1, 'max' => 5],
			FieldType::Enum->value      => ['options' => ['a', 'b']],
			FieldType::List->value      => ['item' => ['type' => 'number']],
			FieldType::Reference->value => ['to' => 'post', 'multiple' => false],
			FieldType::Object->value    => ['fields' => [['name' => 'a']], 'closed' => true]
		];

		$shared = ['name', 'type', 'class', 'aliases', 'required', 'default', 'label', 'description'];

		foreach (FieldType::cases() as $type) {
			$field   = $factory->fromArray(['name' => 'sample', 'type' => $type->value, ...($definitions[$type->value] ?? [])]);
			$options = array_diff(array_keys($field->toArray()), $shared);

			$this->assertSame(
				[],
				array_values(array_diff($options, array_keys($type->className()::definitionSchema([])))),
				"The {$type->value} field type's schema is missing options."
			);
		}
	}

	public function testFieldDefinitionsNameEveryBuiltInType(): void
	{
		$json = JsonSchemas::encode(new JsonSchemas()->theme());

		foreach (FieldType::cases() as $type) {
			$this->assertStringContainsString("\"const\": \"{$type->value}\"", $json);
		}

		// Each type's options and controls are checked when `type` names it.
		$this->assertSame(count(FieldType::cases()), substr_count($json, '"if": {'));
	}

	public function testDescribesEveryBuiltInMenuLinkKey(): void
	{
		foreach (MenuLinkType::cases() as $type) {
			$link = new ReflectionClass($type->className())->newInstanceWithoutConstructor();

			$this->assertSame(
				[$type->value, ...$link->keys()],
				array_keys($type->className()::itemSchema($type->value, [])),
				"The {$type->value} link's schema doesn't match the keys it reads."
			);
		}
	}

	public function testTypeListsHaveNoTypeSpecificKeywords(): void
	{
		// PhpStorm skips `pattern`, `minimum`, and the like beside a type
		// list (D-212).
		$check = function (mixed $node, string $file) use (&$check): void {
			if (! is_array($node)) {
				return;
			}

			if (is_array($node['type'] ?? null)) {
				$this->assertSame([], array_values(array_intersect(array_keys($node), ['pattern', 'minLength', 'maxLength', 'minimum', 'maximum', 'items', 'properties'])), "{$file} has a type list with type-specific keywords.");
			}

			foreach ($node as $child) {
				$check($child, $file);
			}
		};

		foreach (new JsonSchemas()->all() as $file => $schema) {
			$check($schema, $file);
		}
	}

	public function testSchemasDescribeTheirValues(): void
	{
		$schema = new Schema([
			new DateField('published')->aliases('date')->described('The publish date.'),
			new EnumField('status', ['draft', 'published'])->default('published'),
			new ListField('class')
		], closed: true);

		$this->assertSame([
			'type'                 => 'object',
			'properties'           => [
				'published' => ['anyOf' => [['type' => 'string', 'pattern' => '^\\s*\\d{4}-\\d{2}-\\d{2}'], ['type' => 'integer']], 'description' => 'The publish date.'],
				'date'      => ['anyOf' => [['type' => 'string', 'pattern' => '^\\s*\\d{4}-\\d{2}-\\d{2}'], ['type' => 'integer']], 'description' => 'Same as published. The publish date.'],
				'status'    => ['enum' => ['draft', 'published'], 'default' => 'published'],
				'class'     => ['anyOf' => [['type' => 'array', 'items' => ['type' => ['string', 'number']]], ['type' => ['string', 'number']]]]
			],
			'additionalProperties' => false
		], $schema->jsonSchema());
		$this->assertSame(['type' => 'object'], new Schema()->jsonSchema());
	}

	public function testManifestsAcceptASchemaKey(): void
	{
		$theme = ThemeManifest::fromArray('/themes/nova', ['$schema' => 'theme.schema.json', 'name' => 'acme/nova', 'label' => 'Nova', 'namespace' => 'nova']);

		$this->assertSame('acme/nova', $theme->name);

		$plugin = PluginManifest::fromArray([
			'$schema'   => 'plugin.schema.json',
			'name'      => 'acme/gallery',
			'label'     => 'Gallery',
			'namespace' => 'gallery',
			'provider'  => 'Acme\\Gallery\\GalleryServiceProvider',
			'source'    => PluginSource::Local,
			'path'      => '/plugins/gallery'
		]);

		$this->assertSame('acme/gallery', $plugin->name);
	}
}
