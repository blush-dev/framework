<?php

/**
 * Editor JSON Schemas.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\JsonSchema;

use JsonException;
use Blush\Component\ComponentName;
use Blush\Component\Variant;
use Blush\Content\EntryFields;
use Blush\Content\Schema\FieldType;
use Blush\Content\Schema\Schema;
use Blush\Core\Framework;
use Blush\Media\MediaSchemas;
use Blush\Menu\Link\MenuLinkType;
use Blush\Region\Item\RegionItemType;
use Blush\Translation\LocaleMap;

/**
 * Builds the JSON Schemas editors use to autocomplete and check Blush's
 * data files (D-206, D-207, D-211): `theme.json`, `extension.json`, the
 * site's menu and region files, and entries' built-in front matter. They're written to `resources/schemas`
 * with `composer schemas`, and a test fails when the committed files are
 * stale.
 *
 * Each schema is as open as the file it describes: manifests and items
 * allow keys the schema doesn't describe (a component's props, a theme's
 * menu fields), and menu and region files allow only their own keys.
 * The built-in field types, menu links, and region items describe
 * themselves; an extension's are allowed but not described.
 *
 * Class names and namespace prefixes have no pattern, as in Composer's
 * schema (D-210): PhpStorm reads backslashes in patterns and values
 * differently from other editors, and the manifest classes check them.
 */
final readonly class JsonSchemas
{
	/**
	 * The folder the schemas are written to, relative to the framework.
	 */
	public const string DIRECTORY = 'resources/schemas';

	/**
	 * The JSON Schema draft the schemas use; draft 7 has the widest
	 * editor support.
	 */
	private const string DRAFT = 'http://json-schema.org/draft-07/schema#';

	/**
	 * Matches a theme slug or a location name.
	 */
	private const string SLUG_PATTERN = '^[a-z0-9][a-z0-9_-]*$';

	/**
	 * Matches a file path inside a theme.
	 */
	private const string PATH_PATTERN = '^[A-Za-z0-9_-][A-Za-z0-9._-]*(/[A-Za-z0-9_-][A-Za-z0-9._-]*)*$';

	/**
	 * Matches a folder path inside a theme.
	 */
	private const string FOLDER_PATTERN = '^/?[A-Za-z0-9_-][A-Za-z0-9._-]*(/[A-Za-z0-9_-][A-Za-z0-9._-]*)*/?$';

	/**
	 * Matches an extension's autoload folder: relative, and not leaving
	 * the extension.
	 */
	private const string EXTENSION_PATH_PATTERN = '^(?!/)(?!.*\\.\\.).+$';

	/**
	 * Returns every schema, by file name.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function all(): array
	{
		return [
			'entry.schema.json'     => $this->entry(),
			'extension.schema.json' => $this->extension(),
			'media.schema.json'     => $this->media(),
			'menu.schema.json'      => $this->menu(),
			'region.schema.json'    => $this->region(),
			'theme.schema.json'     => $this->theme()
		];
	}

	/**
	 * Returns a schema as the JSON written to its file.
	 *
	 * @param  array<string, mixed> $schema
	 * @throws JsonException
	 */
	public static function encode(array $schema): string
	{
		$json = json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

		// Tabs, like the rest of the project's JSON.
		return preg_replace_callback('/^(?: {4})+/m', static fn (array $match): string => str_repeat("\t", intdiv(strlen($match[0]), 4)), $json) . "\n";
	}

	/**
	 * Returns the schema for a theme's `theme.json`.
	 *
	 * @return array<string, mixed>
	 */
	public function theme(): array
	{
		$field = ['$ref' => '#/definitions/field'];

		return [
			'$schema'     => self::DRAFT,
			'title'       => sprintf('%s theme manifest', Framework::NAME),
			'description' => 'A theme\'s theme.json: its name, assets, settings, and the menu and region locations it shows.',
			'type'        => 'object',
			'required'    => ['name'],
			'properties'  => [
				'$schema'     => ['type' => 'string', 'description' => 'The JSON Schema editors check this file with.'],
				'name'        => ['type' => 'string', 'minLength' => 1, 'description' => 'The theme\'s name.'],
				'version'     => ['type' => 'string', 'description' => 'The theme\'s version, such as 1.0.0.'],
				'description' => ['type' => 'string', 'description' => 'What the theme is for.'],
				'parent'      => [
					'type'        => 'string',
					'pattern'     => self::SLUG_PATTERN,
					'description' => 'The slug of the theme this one builds on. Anything this theme doesn\'t include comes from its parent.'
				],
				'styles'      => [
					'type'        => 'array',
					'items'       => ['type' => 'string', 'pattern' => self::PATH_PATTERN],
					'default'     => ['style.css'],
					'description' => 'Stylesheets every page loads, relative to the theme. Defaults to ["style.css"].'
				],
				'scripts'     => [
					'type'        => 'array',
					'items'       => ['type' => 'string', 'pattern' => self::PATH_PATTERN],
					'description' => 'Scripts every page loads, relative to the theme.'
				],
				'provider'    => [
					'type'        => 'string',
					'description' => 'The class name of the theme\'s service provider, registered before the site\'s.'
				],
				'autoload'    => $this->autoload('Namespace prefixes and the folders inside the theme their classes are in, such as {"Notebook\\\\": "src/"}.'),
				'settings'    => [
					'type'                 => 'object',
					'description'          => 'Options site owners set in user/data/theme.json, by name. They use the same field types as custom fields.',
					'additionalProperties' => $field
				],
				'menus'       => $this->locations('The places the theme shows the site\'s menus, by name: a label, or an object.', [
					'label'  => ['type' => 'string', 'description' => 'The location\'s name, which also names its <nav>. Keep it short, such as "Primary".'],
					'depth'  => ['type' => 'integer', 'minimum' => 1, 'description' => 'The deepest level shown (1 is the top level). Deeper items are left out.'],
					'fields' => [
						'type'                 => 'object',
						'description'          => 'Extra keys menu items may have, as field definitions by name.',
						'additionalProperties' => $field
					]
				]),
				'regions'     => $this->locations('The places the theme shows the site\'s regions, by name: a label, or an object.', [
					'label' => ['type' => 'string', 'description' => 'The location\'s name.'],
					'items' => [
						'type'        => 'array',
						'description' => 'What the region shows until the site has a region file for it.',
						'items'       => ['$ref' => '#/definitions/regionItem']
					]
				]),
				'bleed'       => [
					'type'                 => 'object',
					'description'          => 'The classes the admin\'s editor writes to widen an element past the text column (D-313): "wide" into the margin, "full" edge to edge. Each defaults to "bleed-wide" and "bleed-full"; the theme styles them.',
					'additionalProperties' => false,
					'properties'           => [
						'wide' => ['type' => 'string', 'pattern' => '^[A-Za-z_][A-Za-z0-9_-]*$', 'description' => 'The class for wider than the text, into the margin.'],
						'full' => ['type' => 'string', 'pattern' => '^[A-Za-z_][A-Za-z0-9_-]*$', 'description' => 'The class for edge to edge.']
					]
				],
				'variants'    => [
					'type'                 => 'object',
					'description'          => 'Variants the theme adds to components, by component name (such as "callout" or "notebook/card"), and under "image", the classes it offers Markdown images (such as "inline-left"). Their labels and descriptions are in the theme\'s lang/ catalog, under components.{name}.variants.{variant} (images.variants.{variant} for images).',
					'propertyNames'        => ['pattern' => '^' . ComponentName::SYNTAX . '$'],
					'additionalProperties' => [
						'type'  => 'array',
						'items' => [
							'oneOf' => [
								['type' => 'string', 'pattern' => '^' . Variant::SYNTAX . '$', 'not' => ['const' => Variant::DEFAULT], 'description' => 'The variant\'s name: lowercase letters, digits, and hyphens. Its class is component-{name}--{variant}.'],
								[
									'type'                 => 'object',
									'required'             => ['name'],
									'additionalProperties' => false,
									'properties'           => [
										'name'     => ['type' => 'string', 'pattern' => '^' . Variant::SYNTAX . '$', 'not' => ['const' => Variant::DEFAULT], 'description' => 'The variant\'s name.'],
										'modifier' => ['type' => 'string', 'pattern' => '^' . Variant::SYNTAX . '$', 'description' => 'The class modifier to add instead of the name: component-{name}--{modifier}.']
									]
								]
							]
						]
					]
				],
				'requires'    => $this->requires('What the theme needs, such as {"blush": "^2.0"}. Not checked yet.')
			],
			'definitions' => [
				'field'      => $this->field(),
				'regionItem' => $this->regionItem(),
				'localeText' => $this->localeText()
			]
		];
	}

	/**
	 * Returns the schema for a local extension's `extension.json`.
	 *
	 * @return array<string, mixed>
	 */
	public function extension(): array
	{
		return [
			'$schema'     => self::DRAFT,
			'title'       => sprintf('%s extension manifest', Framework::NAME),
			'description' => 'A local extension\'s extension.json: its name, service provider, and the classes autoloaded for it.',
			'type'        => 'object',
			'required'    => ['name', 'provider'],
			'properties'  => [
				'$schema'     => ['type' => 'string', 'description' => 'The JSON Schema editors check this file with.'],
				'name'        => [
					'type'        => 'string',
					'pattern'     => '^[a-z0-9]([_.-]?[a-z0-9]+)*(/[a-z0-9](([_.]|-{1,2})?[a-z0-9]+)*)?$',
					'description' => 'The extension\'s name: a lowercase slug, or vendor/name.'
				],
				'version'     => ['type' => 'string', 'default' => '0.0.0', 'description' => 'The extension\'s version, such as 1.0.0.'],
				'description' => ['type' => 'string', 'description' => 'What the extension does.'],
				'provider'    => [
					'type'        => 'string',
					'description' => 'The class name of the extension\'s service provider.'
				],
				'autoload'    => $this->autoload('Namespace prefixes, each ending in a backslash, and the folders inside the extension their classes are in, such as {"Acme\\\\Gallery\\\\": "src/"}.', true),
				'requires'    => $this->requires('What the extension needs, by name, with Composer-style version constraints: php, blush, ext-{name} for PHP extensions, and other extensions.')
			]
		];
	}

	/**
	 * Returns the schema for a media file's metadata file
	 * (`user/data/media/…`, D-287): the built-in fields, every kind's and
	 * each kind's together, since a data file doesn't say its kind. A
	 * site's and extensions' fields differ by site, so other keys are
	 * allowed.
	 *
	 * @return array<string, mixed>
	 */
	public function media(): array
	{
		$fields = [];

		foreach (MediaSchemas::builtIn() as $set) {
			foreach ($set->fields as $field) {
				$fields[$field->name] = $field;
			}
		}

		return [
			'$schema'     => self::DRAFT,
			'title'       => sprintf('%s media metadata', Framework::NAME),
			'description' => 'A media file\'s metadata, kept in user/data/media: the built-in fields (alt is for images). Sites and extensions add their own.',
			...new Schema($fields)->jsonSchema()
		];
	}

	/**
	 * Returns the schema for an entry's front matter (or a data entry):
	 * the built-in fields. A type's own fields and its taxonomies' term
	 * fields differ by site, so other keys are allowed (D-211).
	 *
	 * @return array<string, mixed>
	 */
	public function entry(): array
	{
		return [
			'$schema'     => self::DRAFT,
			'title'       => sprintf('%s entry', Framework::NAME),
			'description' => 'An entry\'s front matter: the fields every entry understands. Content types and taxonomies add their own.',
			...EntryFields::schema()->jsonSchema()
		];
	}

	/**
	 * Returns the schema for a site menu file, `user/data/menus/{name}`: a
	 * label and items, or the list of items on its own.
	 *
	 * @return array<string, mixed>
	 */
	public function menu(): array
	{
		$items = [
			'type'        => 'array',
			'description' => 'The menu\'s items, in order.',
			'items'       => ['$ref' => '#/definitions/menuItem']
		];

		return [
			'$schema'     => self::DRAFT,
			'title'       => sprintf('%s menu', Framework::NAME),
			'description' => 'A site menu in user/data/menus/, shown by the theme location with the same name.',
			'anyOf'       => [
				[
					'type'                 => 'object',
					'properties'           => [
						'$schema' => ['type' => 'string', 'description' => 'The JSON Schema editors check this file with.'],
						'label'   => [...self::text(), 'description' => 'Names the navigation for screen readers. Defaults to the theme\'s name for the location.'],
						'items'   => $items
					],
					'additionalProperties' => false
				],
				$items
			],
			'definitions' => [
				'menuItem'   => $this->menuItem(),
				'localeText' => $this->localeText()
			]
		];
	}

	/**
	 * Returns the schema for a site region file, `user/data/regions/{name}`:
	 * items, or the list of items on its own.
	 *
	 * @return array<string, mixed>
	 */
	public function region(): array
	{
		$items = [
			'type'        => 'array',
			'description' => 'What the region shows, in order. They replace the theme\'s items; an empty list clears them.',
			'items'       => ['$ref' => '#/definitions/regionItem']
		];

		return [
			'$schema'     => self::DRAFT,
			'title'       => sprintf('%s region', Framework::NAME),
			'description' => 'A site region in user/data/regions/, shown by the theme location with the same name.',
			'anyOf'       => [
				[
					'type'                 => 'object',
					'properties'           => [
						'$schema' => ['type' => 'string', 'description' => 'The JSON Schema editors check this file with.'],
						'items'   => $items
					],
					'additionalProperties' => false
				],
				$items
			],
			'definitions' => [
				'regionItem' => $this->regionItem(),
				'localeText' => $this->localeText()
			]
		];
	}

	/**
	 * Returns the schema for a menu item: its options, and each built-in
	 * link kind's keys. Other keys are the theme's fields.
	 *
	 * @return array<string, mixed>
	 */
	private function menuItem(): array
	{
		$links = [];

		foreach (MenuLinkType::cases() as $type) {
			$links += $type->className()::itemSchema($type->value, self::text());
		}

		return [
			'type'        => 'object',
			'description' => 'A menu item: one link (or none, for a heading over children) and its options. A theme may accept more keys.',
			'properties'  => [
				...$links,
				'label'       => [...self::text(), 'description' => 'The text shown. Needed for route and url links; for the others it replaces the title.'],
				'children'    => [
					'type'        => 'array',
					'description' => 'Items shown under this one. An item with children and no link is a heading for them.',
					'items'       => ['$ref' => '#/definitions/menuItem']
				],
				'icon'        => [...self::text(), 'description' => 'An icon shown with the label, such as house or mytheme/github.'],
				'description' => [...self::text(), 'description' => 'A short line of text under the label, for larger dropdown menus.'],
				'image'       => [...self::text(), 'description' => 'The URL of a small image shown with the label.'],
				'badge'       => [...self::text(), 'description' => 'A short tag, such as New.'],
				'class'       => [...self::text(), 'description' => 'A CSS class for the item.'],
				'rel'         => [...self::text(), 'description' => 'The link\'s rel, such as me for your own profiles.']
			]
		];
	}

	/**
	 * Returns the schema for a region item: each built-in kind's key.
	 * Other keys are a component's props or a view's data.
	 *
	 * @return array<string, mixed>
	 */
	private function regionItem(): array
	{
		$kinds = [];

		foreach (RegionItemType::cases() as $type) {
			$kinds += $type->className()::itemSchema($type->value, self::text());
		}

		return [
			'type'        => 'object',
			'description' => sprintf('One thing a region shows. An item is one kind: %s.', implode(', ', array_map(static fn (RegionItemType $type): string => $type->value, RegionItemType::cases()))),
			'properties'  => $kinds
		];
	}

	/**
	 * Returns the schema for a locale map: text by locale (D-202).
	 *
	 * @return array<string, mixed>
	 */
	private function localeText(): array
	{
		return [
			'type'                 => 'object',
			'description'          => 'Text by locale, such as {"en": "About", "fr": "À propos"}.',
			'propertyNames'        => ['pattern' => trim(LocaleMap::LOCALE, '/')],
			'additionalProperties' => ['type' => 'string']
		];
	}

	/**
	 * Returns the schema for text, or a locale map of it.
	 *
	 * @return array<string, mixed>
	 */
	private static function text(): array
	{
		return ['anyOf' => [['type' => 'string'], ['$ref' => '#/definitions/localeText']]];
	}

	/**
	 * Returns the schema for a field definition: the shared keys, and each
	 * built-in type's own keys when `type` names it (editors offer those
	 * once `type` is set).
	 *
	 * @return array<string, mixed>
	 */
	private function field(): array
	{
		$field = ['$ref' => '#/definitions/field'];
		$types = array_map(
			static fn (FieldType $type): array => ['const' => $type->value, 'description' => $type->description()],
			FieldType::cases()
		);

		$properties = [
			'name'        => ['type' => 'string', 'description' => 'The key the value is read from. Fields in a map are named by their key.'],
			'type'        => [
				'description' => 'The field type. Extensions may add others.',
				'default'     => FieldType::Text->value,
				'anyOf'       => [...$types, ['type' => 'string']]
			],
			'required'    => ['type' => 'boolean', 'description' => 'Whether a value must be present.'],
			'default'     => ['description' => 'The value used when none is given.'],
			'aliases'     => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Other keys the value may be read from.'],
			'label'       => ['type' => 'string', 'description' => 'A human-readable name.'],
			'description' => ['type' => 'string', 'description' => 'Help text.']
		];

		$conditions = [];

		foreach (FieldType::cases() as $type) {
			$own = $type->className()::definitionSchema($field);

			if ($own === []) {
				continue;
			}

			$conditions[] = [
				'if'   => ['properties' => ['type' => ['const' => $type->value]], 'required' => ['type']],
				'then' => ['properties' => $own]
			];
		}

		return [
			'type'        => 'object',
			'description' => 'A field definition.',
			'properties'  => $properties,
			'allOf'       => $conditions
		];
	}

	/**
	 * Returns the schema for a map of location names to a label or an
	 * object.
	 *
	 * @param  array<string, array<string, mixed>> $properties The object form's keys.
	 * @return array<string, mixed>
	 */
	private function locations(string $description, array $properties): array
	{
		return [
			'type'                 => 'object',
			'description'          => $description,
			'propertyNames'        => ['pattern' => self::SLUG_PATTERN],
			'additionalProperties' => [
				'anyOf' => [
					['type' => 'string', 'description' => 'The location\'s label.'],
					['type' => 'object', 'properties' => $properties]
				]
			]
		];
	}

	/**
	 * Returns the schema for an `autoload` object and its PSR-4 map.
	 *
	 * An extension's folders are checked more loosely than a theme's.
	 *
	 * @return array<string, mixed>
	 */
	private function autoload(string $description, bool $extension = false): array
	{
		return [
			'type'        => 'object',
			'description' => 'Classes to autoload.',
			'properties'  => [
				'psr-4' => [
					'type'                 => 'object',
					'description'          => $description,
					'additionalProperties' => ['type' => 'string', 'pattern' => $extension ? self::EXTENSION_PATH_PATTERN : self::FOLDER_PATTERN]
				]
			]
		];
	}

	/**
	 * Returns the schema for a `requires` map.
	 *
	 * @return array<string, mixed>
	 */
	private function requires(string $description): array
	{
		return [
			'type'                 => 'object',
			'description'          => $description,
			'additionalProperties' => ['type' => 'string']
		];
	}
}
