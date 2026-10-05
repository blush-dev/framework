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
use Blush\Core\Framework;
use Blush\Extension\ExtensionLinks;
use Blush\Extension\ExtensionName;
use Blush\Extension\ExtensionNamespace;
use Blush\Field\Control;
use Blush\Field\FieldSet;
use Blush\Field\FieldType;
use Blush\Field\Schema;
use Blush\Media\MediaMetadata;
use Blush\Media\MediaSchemas;
use Blush\Menu\Link\MenuLinkType;
use Blush\Region\Item\RegionItemType;
use Blush\Theme\PreviewLayout;
use Blush\Theme\ThemePreview;
use Blush\Translation\LocaleMap;

/**
 * Builds the JSON Schemas editors use to autocomplete and check Blush's
 * data files (D-206, D-207, D-211): `theme.json`, `plugin.json`, the
 * site's menu and region files, and entries' built-in front matter. They're written to `resources/schemas`
 * with `composer schemas`, and a test fails when the committed files are
 * stale.
 *
 * Each schema is as open as the file it describes: manifests and items
 * allow keys the schema doesn't describe (a component's props, a theme's
 * menu fields), and menu and region files allow only their own keys.
 * The built-in field types, menu links, and region items describe
 * themselves; a plugin's are allowed but not described.
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
	 * Matches a location name.
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
	 * Matches an autoload path: relative, and not leaving the extension.
	 */
	private const string PATH_INSIDE_PATTERN = '^(?!/)(?!.*\\.\\.).*$';

	/**
	 * Returns every schema, by file name.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function all(): array
	{
		return [
			'entry.schema.json'     => $this->entry(),
			'field-set.schema.json' => $this->fieldSet(),
			'icons.schema.json'     => $this->iconPack(),
			'media.schema.json'     => $this->media(),
			'menu.schema.json'      => $this->menu(),
			'plugin.schema.json'    => $this->plugin(),
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
			'description' => 'A theme\'s theme.json: its name, label, namespace, assets, settings, and the menu and region locations it shows.',
			'type'        => 'object',
			'required'    => [],
			'properties'  => [
				'$schema'     => ['type' => 'string', 'description' => 'The JSON Schema editors check this file with.'],
				...$this->identity('theme'),
				'version'     => ['type' => 'string', 'description' => 'The theme\'s version, such as 1.0.0.'],
				'description' => ['type' => 'string', 'description' => 'What the theme is for.'],
				'authors'     => $this->authors('theme'),
				'license'     => $this->license('theme'),
				...$this->links('theme'),
				'parent'      => [
					'type'        => 'string',
					'pattern'     => trim(ExtensionName::PATTERN, '#'),
					'description' => 'The name of the theme this one builds on, such as "blush/default". Anything this theme doesn\'t include comes from its parent.'
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
				'autoload'    => $this->autoload('theme', 'Namespace prefixes and the folders inside the theme their classes are in, such as {"Notebook\\\\": "src/"}.'),
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
				'preview'     => $this->themePreview(),
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
				'require'     => $this->requires('What the theme needs, as composer.json says it, with version constraints: php, blush-dev/framework for Blush, ext-{name} for PHP extensions, other plugins, themes, or icon packs by vendor/name, and libraries Composer installed. When the active theme\'s chain has a requirement that isn\'t met, the default theme runs in its place. Without it, the require in the composer.json beside this file is used.'),
				'conflict'    => $this->requires('What the theme can\'t run with, as composer.json says it, each with the versions it can\'t: php, blush-dev/framework for Blush, ext-{name} for PHP extensions, and other plugins, themes, or icon packs by vendor/name, which conflict when they\'re turned on. When it conflicts with something that\'s on, its chain doesn\'t run, and the default theme runs in its place. Without it, the conflict in the composer.json beside this file is used.'),
				'replace'     => $this->requires('The packages the theme stands in for, as composer.json says it, each with the versions it stands in for (self.version for its own): a require of one is met by this theme when it runs. It can\'t be activated while one it replaces is on. Without it, the replace in the composer.json beside this file is used.'),
				'provide'     => $this->requires('The packages the theme provides an implementation of, as composer.json says it, each with the versions it provides (self.version for its own): a require of one is met by this theme when it runs, and any number of extensions may provide one. Without it, the provide in the composer.json beside this file is used.')
			],
			'definitions' => [
				'field'      => $this->field(),
				'regionItem' => $this->regionItem(),
				'localeText' => $this->localeText()
			]
		];
	}

	/**
	 * Returns the schema for a local plugin's `plugin.json` (D-378).
	 *
	 * @return array<string, mixed>
	 */
	public function plugin(): array
	{
		return [
			'$schema'     => self::DRAFT,
			'title'       => sprintf('%s plugin manifest', Framework::NAME),
			'description' => 'A local plugin\'s plugin.json: its name, label, namespace, service provider, and the classes autoloaded for it.',
			'type'        => 'object',
			'required'    => [],
			'properties'  => [
				'$schema'     => ['type' => 'string', 'description' => 'The JSON Schema editors check this file with.'],
				...$this->identity('plugin'),
				'version'     => ['type' => 'string', 'default' => '0.0.0', 'description' => 'The plugin\'s version, such as 1.0.0.'],
				'description' => ['type' => 'string', 'description' => 'What the plugin does.'],
				'provider'    => [
					'type'        => 'string',
					'description' => 'The class name of the plugin\'s service provider. Leave it out for a plugin without one, such as one that only loads autoload.files.'
				],
				'autoload'    => $this->autoload('plugin', 'Namespace prefixes, each ending in a backslash, and the folders inside the plugin their classes are in, such as {"Acme\\\\Gallery\\\\": "src/"}.'),
				'require'     => $this->requires('What the plugin needs, as composer.json says it, with version constraints: php, blush-dev/framework for Blush, ext-{name} for PHP extensions, other plugins, themes, or icon packs by vendor/name, and libraries Composer installed. A plugin whose requirements aren\'t met doesn\'t run. Without it, the require in the composer.json beside this file is used.'),
				'conflict'    => $this->requires('What the plugin can\'t run with, as composer.json says it, each with the versions it can\'t: php, blush-dev/framework for Blush, ext-{name} for PHP extensions, and other plugins, themes, or icon packs by vendor/name, which conflict when they\'re turned on. A plugin that conflicts with something that\'s on doesn\'t run. Without it, the conflict in the composer.json beside this file is used.'),
				'replace'     => $this->requires('The packages the plugin stands in for, as composer.json says it, each with the versions it stands in for (self.version for its own): a require of one is met by this plugin when it runs. It doesn\'t run while one it replaces is on. Without it, the replace in the composer.json beside this file is used.'),
				'provide'     => $this->requires('The packages the plugin provides an implementation of, as composer.json says it, each with the versions it provides (self.version for its own): a require of one is met by this plugin when it runs, and any number of extensions may provide one. Without it, the provide in the composer.json beside this file is used.'),
				'authors'     => $this->authors('plugin'),
				'license'     => $this->license('plugin'),
				...$this->links('plugin')
			]
		];
	}

	/**
	 * Returns the schema for an icon pack's `icons.json` (D-378).
	 *
	 * @return array<string, mixed>
	 */
	public function iconPack(): array
	{
		return [
			'$schema'     => self::DRAFT,
			'title'       => sprintf('%s icon pack manifest', Framework::NAME),
			'description' => 'An icon pack\'s icons.json: its name, label, namespace, and the folder its SVG icons are in.',
			'type'        => 'object',
			'required'    => [],
			'properties'  => [
				'$schema'     => ['type' => 'string', 'description' => 'The JSON Schema editors check this file with.'],
				...$this->identity('icon pack'),
				'version'     => ['type' => 'string', 'description' => 'The pack\'s version, such as 1.0.0.'],
				'description' => ['type' => 'string', 'description' => 'What the icons are.'],
				'folder'      => [
					'type'        => 'string',
					'pattern'     => self::FOLDER_PATTERN,
					'description' => 'The folder inside the pack its *.svg files are in, such as "svg". Defaults to the pack\'s own folder. Each {icon}.svg is {namespace}/{icon}.'
				],
				'require'     => $this->requires('What the pack needs, as composer.json says it, with version constraints: php, blush-dev/framework for Blush, ext-{name} for PHP extensions, other plugins, themes, or icon packs by vendor/name, and libraries Composer installed. A pack whose requirements aren\'t met doesn\'t load. Without it, the require in the composer.json beside this file is used.'),
				'conflict'    => $this->requires('What the pack can\'t run with, as composer.json says it, each with the versions it can\'t: php, blush-dev/framework for Blush, ext-{name} for PHP extensions, and other plugins, themes, or icon packs by vendor/name, which conflict when they\'re turned on. A pack that conflicts with something that\'s on doesn\'t load. Without it, the conflict in the composer.json beside this file is used.'),
				'replace'     => $this->requires('The packages the pack stands in for, as composer.json says it, each with the versions it stands in for (self.version for its own): a require of one is met by this pack when it runs. It doesn\'t load while one it replaces is on. Without it, the replace in the composer.json beside this file is used.'),
				'provide'     => $this->requires('The packages the pack provides an implementation of, as composer.json says it, each with the versions it provides (self.version for its own): a require of one is met by this pack when it runs, and any number of extensions may provide one. Without it, the provide in the composer.json beside this file is used.'),
				'authors'     => $this->authors('icon pack'),
				'license'     => $this->license('icon pack'),
				...$this->links('icon pack')
			]
		];
	}

	/**
	 * Returns the schema for a field set file, `user/data/fields/{name}`
	 * (D-337): its label, help, targets, and fields, as a list of named
	 * definitions or a map of names to them.
	 *
	 * @return array<string, mixed>
	 */
	public function fieldSet(): array
	{
		$field  = ['$ref' => '#/definitions/field'];
		$target = [
			'type'        => 'string',
			'pattern'     => trim(FieldSet::TARGET_PATTERN, '/'),
			'description' => 'A place the set\'s fields are added: a kind and a name, such as type:post for the post content type.'
		];

		return [
			'$schema'              => self::DRAFT,
			'title'                => sprintf('%s field set', Framework::NAME),
			'description'          => 'A field set: fields added to each place it targets, such as content types. It\'s named after its file.',
			'type'                 => 'object',
			'additionalProperties' => false,
			'properties'           => [
				'$schema'     => ['type' => 'string', 'description' => 'The JSON Schema editors check this file with.'],
				'name'        => ['type' => 'string', 'pattern' => trim(FieldSet::NAME_PATTERN, '/'), 'description' => 'The set\'s name, which must match its file\'s.'],
				'label'       => ['type' => 'string', 'description' => 'The set\'s name for people, heading its fields in the admin. Its name, made readable, by default.'],
				'description' => ['type' => 'string', 'description' => 'Help text, shown under the label in the admin.'],
				'targets'     => [
					'description' => 'Where the fields are added, all of one kind (type:, media:, or settings:).',
					'anyOf'       => [$target, ['type' => 'array', 'items' => $target]]
				],
				'slot'        => [
					'type'        => 'string',
					'pattern'     => trim(FieldSet::NAME_PATTERN, '/'),
					'description' => 'The slot its places show it in, from the ones their kind offers; every kind has details, its default. Left out, the kind\'s default.'
				],
				'fields'      => [
					'description' => 'The fields: a list of definitions with names, or a map of names to definitions.',
					'anyOf'       => [
						['type' => 'array', 'items' => ['allOf' => [$field, ['required' => ['name']]]]],
						['type' => 'object', 'additionalProperties' => $field]
					]
				]
			],
			'definitions'          => [
				'field' => $this->field()
			]
		];
	}

	/**
	 * Returns the schema for a media file's metadata file
	 * (`user/data/media/…`, D-287): the built-in fields, every kind's and
	 * each kind's together, since a data file doesn't say its kind, and
	 * the file's `id` (D-487), which a size of another image doesn't
	 * have, so it isn't required, and an image's `sizes` (D-488). A site's and extensions' fields differ
	 * by site, so other keys are allowed.
	 *
	 * @return array<string, mixed>
	 */
	public function media(): array
	{
		$schema = MediaSchemas::allBuiltIn()->jsonSchema();
		$fields = is_array($schema['properties'] ?? null) ? $schema['properties'] : [];

		return [
			'$schema'     => self::DRAFT,
			'title'       => sprintf('%s media metadata', Framework::NAME),
			'description' => 'A media file\'s metadata, kept in user/data/media: the built-in fields (alt is for images). Sites and extensions add their own.',
			...$schema,
			'properties'  => [
				'$schema' => ['type' => 'string', 'description' => 'The JSON Schema editors check this file with.'],
				...$fields,
				MediaMetadata::SIZES => [
					'type'                 => 'object',
					'description'          => 'An image\'s other sizes: each file\'s path in user/media, with its width and height. media:sizes records them.',
					'additionalProperties' => [
						'type'                 => 'object',
						'properties'           => ['width' => ['type' => 'integer', 'minimum' => 1], 'height' => ['type' => 'integer', 'minimum' => 1]],
						'required'             => ['width', 'height'],
						'additionalProperties' => false
					]
				],
				MediaMetadata::ID => [
					'type'        => 'string',
					'pattern'     => '^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$',
					'description' => 'The media file\'s id, a UUID, kept last. Blush writes it; media:ids adds a missing one.'
				]
			]
		];
	}

	/**
	 * Returns the schema for an entry's front matter (or a data entry):
	 * the built-in fields, and the required `id` (D-477). A type's own
	 * fields and its taxonomies' term fields differ by site, so other
	 * keys are allowed (D-211).
	 *
	 * @return array<string, mixed>
	 */
	public function entry(): array
	{
		$schema = EntryFields::schema()->jsonSchema();
		$fields = is_array($schema['properties'] ?? null) ? $schema['properties'] : [];

		return [
			'$schema'     => self::DRAFT,
			'title'       => sprintf('%s entry', Framework::NAME),
			'description' => 'An entry\'s front matter: the fields every entry understands. Content types and taxonomies add their own.',
			...$schema,
			'properties'  => [
				...$fields,
				EntryFields::ID => [
					'type'        => 'string',
					'pattern'     => '^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$',
					'description' => 'The entry\'s id, a UUID, kept last. Blush writes it; content:ids adds a missing one.'
				]
			],
			'required'    => [EntryFields::ID]
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
			static fn (FieldType $type): array => ['const' => $type->value, 'description' => $type->className()::typeDescription()],
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
			'description' => ['type' => 'string', 'description' => 'Help text.'],
			'control'     => [
				'description' => 'How the admin edits the field, when not its type\'s default.',
				'anyOf'       => array_map(static fn (Control $control): array => ['const' => $control->value, 'description' => $control->label()], Control::cases())
			]
		];

		$conditions = [];

		foreach (FieldType::cases() as $type) {
			$class = $type->className();
			$own   = [
				...$class::definitionSchema($field),
				'control' => ['enum' => array_map(static fn (Control $control): string => $control->value, $class::controls())]
			];

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
	 * Returns the schema for an extension's `license` (D-426, D-427).
	 *
	 * @return array<string, mixed>
	 */
	private function license(string $kind): array
	{
		return [
			'description' => sprintf('How the %s may be used: an SPDX identifier such as "MIT", a list of them any of which applies, "(MIT or Apache-2.0)", "(MIT and OFL-1.1)" when all apply, or "proprietary". Common open source licenses link to their text in the admin. Without it, the license in the composer.json beside this file is used.', $kind),
			'oneOf'       => [
				['type' => 'string'],
				['type' => 'array', 'minItems' => 1, 'items' => ['type' => 'string', 'minLength' => 1]]
			]
		];
	}

	/**
	 * Returns the keys every extension's manifest has (D-378): its
	 * `name`, `label`, and `namespace`.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function identity(string $kind): array
	{
		return [
			'name'      => [
				'type'        => 'string',
				'pattern'     => trim(ExtensionName::PATTERN, '#'),
				'description' => sprintf('The %s\'s name, the key it\'s known by: vendor/name, such as "acme/gallery". Its folder is extensions/{vendor}/{name}, so it must match the folder. Without it, the name in the composer.json beside this file is used; for a Composer package, it\'s the package name.', $kind)
			],
			'label'     => ['type' => 'string', 'minLength' => 1, 'description' => sprintf('The %s\'s title, as people read it. Without one, it\'s shown by its name.', $kind)],
			'namespace' => [
				'type'        => 'string',
				'pattern'     => trim(ExtensionNamespace::PATTERN, '/'),
				'not'         => ['enum' => ExtensionNamespace::RESERVED],
				'description' => sprintf('The namespace the %s\'s components, icons, and translations go by, such as "gallery" for gallery/slideshow. No two installed extensions may share one. Without one, it\'s the name with hyphens for the "/" and any ".", such as "acme-gallery".', $kind)
			]
		];
	}

	/**
	 * Returns the schema for an `autoload` object, in Composer's shape:
	 * its PSR-4 map and its `files` (D-418).
	 *
	 * @return array<string, mixed>
	 */
	private function autoload(string $kind, string $description): array
	{
		return [
			'type'        => 'object',
			'description' => 'What to autoload, as composer.json says it. Without it, the autoload in the composer.json beside this file is used.',
			'properties'  => [
				'psr-4' => [
					'type'                 => 'object',
					'description'          => $description,
					'additionalProperties' => ['type' => 'string', 'pattern' => self::PATH_INSIDE_PATTERN]
				],
				'files' => [
					'type'        => 'array',
					'items'       => ['type' => 'string', 'pattern' => self::PATH_INSIDE_PATTERN],
					'description' => sprintf('Files inside the %s loaded once when it runs, such as ["src/helpers.php"].', $kind)
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

	/**
	 * Returns the schema for a theme manifest's `preview` (D-381).
	 *
	 * @return array<string, mixed>
	 */
	private function themePreview(): array
	{
		$color = [
			'oneOf' => [
				['type' => 'string', 'pattern' => ThemePreview::COLOR, 'description' => 'One hex color for both the light and dark palettes.'],
				[
					'type'        => 'array',
					'items'       => ['type' => 'string', 'pattern' => ThemePreview::COLOR],
					'minItems'    => 2,
					'maxItems'    => 2,
					'description' => 'The light palette\'s color, then the dark one\'s.'
				]
			]
		];

		return [
			'type'                 => 'object',
			'description'          => 'What the admin\'s Themes screen sketches the theme\'s preview from, instead of a screenshot.',
			'additionalProperties' => false,
			'properties'           => [
				'layout'  => [
					'enum'        => array_column(PreviewLayout::cases(), 'value'),
					'default'     => PreviewLayout::Centered->value,
					'description' => 'The page shape: "centered" (one column), "sidebar" (text beside a sidebar), or "wide" (a hero over a row of cards).'
				],
				'type'    => ['type' => 'string', 'description' => 'A line about the theme\'s type, such as "Serif headings · sans body". The admin shows it in its own font.'],
				'palette' => [
					'type'                 => 'object',
					'description'          => 'The theme\'s colors, each a hex color or a [light, dark] pair.',
					'required'             => ThemePreview::ROLES,
					'additionalProperties' => false,
					'properties'           => array_fill_keys(ThemePreview::ROLES, $color)
				]
			]
		];
	}

	/**
	 * Returns the schemas for a manifest's `homepage`, `support`, and
	 * `funding` (D-428), `abandoned` (D-433), and `suggest` (D-434), in
	 * `composer.json`'s shape.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function links(string $kind): array
	{
		$url     = ['type' => 'string', 'format' => 'uri', 'pattern' => '^https?://'];
		$support = [];

		foreach (ExtensionLinks::SUPPORT as $key) {
			$support[$key] = match ($key) {
				'email' => ['type' => 'string', 'format' => 'email', 'description' => 'An email address for support.'],
				'irc'   => ['type' => 'string', 'format' => 'uri', 'pattern' => '^ircs?://', 'description' => 'An IRC channel, such as irc://irc.libera.chat/example.'],
				default => [...$url, 'description' => match ($key) {
					'docs'     => 'The documentation.',
					'source'   => 'Where to browse or download the source.',
					'issues'   => 'The issue tracker.',
					'forum'    => 'The forum.',
					'chat'     => 'A chat channel.',
					'wiki'     => 'The wiki.',
					'rss'      => 'An RSS feed.',
					default    => 'How to report a security issue.'
				}]
			};
		}

		return [
			'homepage' => [...$url, 'description' => sprintf('The %s\'s website. Without it, the homepage in the composer.json beside this file is used.', $kind)],
			'support'  => [
				'type'                 => 'object',
				'additionalProperties' => false,
				'description'          => sprintf('Where to get help with the %s, as composer.json has it. Without it, the support in the composer.json beside this file is used.', $kind),
				'properties'           => $support
			],
			'funding'  => [
				'type'        => 'array',
				'description' => sprintf('Where to fund the %s, as composer.json has it. Without it, the funding in the composer.json beside this file is used.', $kind),
				'items'       => [
					'type'                 => 'object',
					'required'             => ['url'],
					'additionalProperties' => false,
					'properties'           => [
						'type' => ['type' => 'string', 'description' => 'The kind of funding, such as github, patreon, opencollective, tidelift, ko-fi, or custom.'],
						'url'  => [...$url, 'description' => 'Where to fund it.']
					]
				]
			],
			'abandoned' => [
				'oneOf'       => [['type' => 'boolean'], ['type' => 'string', 'pattern' => trim(ExtensionName::PATTERN, '#')]],
				'description' => sprintf('Whether the %s is no longer maintained: true, or the name of the package to use instead (vendor/name), as composer.json has it. It only warns. Without it, the abandoned in the composer.json beside this file is used.', $kind)
			],
			'suggest' => [
				'type'                 => 'object',
				'description'          => sprintf('Packages that would work well with the %s, each mapped to why, as composer.json has it: other plugins, themes, or icon packs by vendor/name, libraries, or ext-{name} for PHP extensions. It\'s only shown, never enforced. Without it, the suggest in the composer.json beside this file is used.', $kind),
				'additionalProperties' => ['type' => 'string']
			]
		];
	}

	/**
	 * Returns the schema for a manifest's `authors` (D-384), in
	 * `composer.json`'s shape.
	 *
	 * @return array<string, mixed>
	 */
	private function authors(string $kind): array
	{
		return [
			'type'        => 'array',
			'description' => sprintf('Who made the %s, as composer.json lists authors. Without it, the authors in the composer.json beside this file are used.', $kind),
			'items'       => [
				'type'                 => 'object',
				'required'             => ['name'],
				'additionalProperties' => false,
				'properties'           => [
					'name'     => ['type' => 'string', 'minLength' => 1, 'description' => 'Their name.'],
					'email'    => ['type' => 'string', 'format' => 'email', 'description' => 'Their email address.'],
					'homepage' => ['type' => 'string', 'format' => 'uri', 'pattern' => '^https?://', 'description' => 'Their website.'],
					'role'     => ['type' => 'string', 'description' => 'What they did, such as "Developer" or "Designer".']
				]
			]
		];
	}
}
