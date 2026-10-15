<?php

/**
 * Entries in the API.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Api;

use Blush\Asset\AssetCollector;
use Blush\Asset\AssetRegistry;
use Blush\Asset\AssetUrls;
use Blush\Content\Entry\Entry;
use Blush\Content\EntryFields;
use Blush\Content\Relation\EntryRelations;
use Blush\Content\Relation\Refs;
use Blush\Content\Relation\Relation;
use Blush\Content\Relation\Relations;
use Blush\Content\Type\ContentType;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Type\TypeKind;
use Blush\Core\AppConfig;
use Blush\Field\Field;
use Blush\Field\Fields\ListField;
use Blush\Field\Fields\MediaField;
use Blush\Llms\MarkdownLinks;
use Blush\Media\MediaException;
use Blush\Translation\Messages;

/**
 * Entries as the API sends them (D-737 to D-739):
 *
 *     { id, type, title, subtitle, slug, status, published, updated,
 *       url?, excerpt, image, parent?, fields, relations, body?, assets? }
 *
 * - `url` is there when the type has addresses; `parent` when it nests
 *   (a tree, or a hierarchical collection), a stub or `null`.
 * - `image` is the featured image, a media stub or `null`.
 * - `excerpt` is plain text: the summary, or the body's prose (D-727).
 * - `fields` holds the fields the type declares, so a site's names
 *   never meet Blush's: each one, `null` when it's empty. Undeclared
 *   front matter isn't sent (D-743). A media field is a media stub
 *   (D-738), and dates are in UTC.
 * - `relations` lists each relation's targets the API may show, as
 *   stubs, by the relation's name, in order.
 * - `body` is `html` (as the site renders it), `markdown` (as written,
 *   with full URLs, D-396), or left out for `none`; `assets` beside an
 *   HTML body lists the scripts and styles it asked for (D-569).
 *
 * `include` turns stubs into records in place, one level deep: an
 * included entry has stubs of its own, and no body.
 */
final readonly class EntryResource
{
	/**
	 * What `?body=` takes.
	 *
	 * @var list<string>
	 */
	public const array BODIES = ['html', 'markdown', 'none'];

	public function __construct(
		private ApiEntries $entries,
		private EntryRelations $related,
		private Relations $relations,
		private ContentTypes $types,
		private MediaResource $media,
		private MarkdownLinks $links,
		private AssetCollector $collector,
		private AssetRegistry $assets,
		private AssetUrls $assetUrls,
		private AppConfig $app
	) {}

	/**
	 * Returns an entry's stub: what links to it.
	 *
	 * @return array{id: ?string, type: string, title: string, url: ?string}
	 */
	public function stub(Entry $entry): array
	{
		return ['id' => $entry->id, 'type' => $entry->type->name, 'title' => $entry->title, 'url' => $this->entries->url($entry)];
	}

	/**
	 * Returns an entry.
	 *
	 * @return array<string, mixed>
	 * @throws MediaException
	 */
	public function item(Entry $entry, string $body = 'none', ApiIncludes $include = new ApiIncludes()): array
	{
		$type   = $entry->type;
		$parent = $entry->parentId === null ? null : $this->entries->find($entry->parentId);

		$data = [
			'id'        => $entry->id,
			'type'      => $type->name,
			'title'     => $entry->title,
			'subtitle'  => $entry->subtitle(),
			'slug'      => $entry->slug,
			'status'    => $entry->status->name,
			'date'      => ApiValues::date($entry->date),
			'updated'   => ApiValues::date($entry->updated),
			...($type->expires ? ['expires' => ApiValues::date($entry->expires)] : []),
			...($this->entries->hasUrls($type) ? ['url' => $this->entries->url($entry)] : []),
			'excerpt'   => self::text($entry->excerpt(more: '')),
			'image'     => $this->media->reference($entry->fields['image'] ?? null, $include->media),
			...($type->kind() === TypeKind::Tree || $this->types->nestsByParent($type->name) ? ['parent' => $parent === null ? null : $this->stub($parent)] : []),
			'fields'    => $this->fields($entry, $include),
			'relations' => $this->relationsOf($entry, $include)
		];

		return match ($body) {
			'html'     => [...$data, ...$this->html($entry)],
			'markdown' => [...$data, 'body' => $this->links->absolute($entry->raw())],
			default    => $data
		};
	}

	/**
	 * Returns the fields the type declares, but Blush's own and the
	 * relations', media fields as media.
	 *
	 * @throws MediaException
	 */
	private function fields(Entry $entry, ApiIncludes $include): object
	{
		$fields = [];

		foreach ($this->declared($entry->type) as $name => $field) {
			$value = $entry->fields[$name] ?? null;

			$fields[$name] = match (true) {
				$field instanceof MediaField => $this->media->reference($value, $include->media),
				$field instanceof ListField && $field->item instanceof MediaField && is_array($value) => array_values(array_filter(
					array_map(fn (mixed $item): ?array => $this->media->reference($item, $include->media), $value),
					static fn (?array $item): bool => $item !== null
				)),
				default => ApiValues::value($value)
			};
		}

		return ApiValues::object($fields);
	}

	/**
	 * Returns the fields an entry's `fields` holds, by name: those its
	 * type declares, but Blush's own and the relations' (D-743), which
	 * are also the only ones a write may set (D-762).
	 *
	 * @return array<string, Field>
	 */
	public function declared(ContentType $type): array
	{
		$skip = [EntryFields::ID => true, Refs::FIELD => true, ...array_fill_keys(array_keys(EntryFields::schema(new Messages())->fields), true)];

		foreach ($this->relations->for($type->name) as $relation) {
			foreach ([$relation->field, ...$relation->aliases] as $key) {
				$skip[$key] = true;
			}
		}

		return array_filter($this->types->carried($type->name), static fn (string $name): bool => ! isset($skip[$name]), ARRAY_FILTER_USE_KEY);
	}

	/**
	 * Returns the relations an entry's `relations` lists, by name.
	 *
	 * @return array<string, Relation>
	 */
	public function listed(ContentType $type): array
	{
		return array_filter($this->relations->for($type->name), self::lists(...));
	}

	/**
	 * Returns each relation's targets the API may show, by name.
	 *
	 * @throws MediaException
	 */
	private function relationsOf(Entry $entry, ApiIncludes $include): object
	{
		$relations = [];

		foreach ($this->listed($entry->type) as $name => $relation) {
			$targets = array_values(array_filter($this->related->related($entry, $name), $this->entries->shows(...)));

			$relations[$name] = $include->includesRelation($name)
				? array_map(fn (Entry $target): array => $this->item($target), $targets)
				: array_map($this->stub(...), $targets);
		}

		return ApiValues::object($relations);
	}

	/**
	 * Returns an entry's rendered body, and the assets it asked for as
	 * full URLs: `styles`, then `scripts`, in the order they load.
	 *
	 * @return array{body: string, assets: array{styles: list<string>, scripts: list<string>}}
	 */
	private function html(Entry $entry): array
	{
		[$html, $handles] = $this->collector->isolate(static fn (): string => $entry->content());

		$styles  = [];
		$scripts = [];

		foreach ($this->assets->ordered($handles) as $asset) {
			foreach ($asset->styles as $style) {
				$url = $this->assetUrls->url($style->path, $style->from);

				if ($url !== null) {
					$styles[] = ApiValues::url($this->app, $url);
				}
			}

			foreach ($asset->scripts as $script) {
				$url = $this->assetUrls->url($script->path, $script->from);

				if ($url !== null) {
					$scripts[] = ApiValues::url($this->app, $url);
				}
			}
		}

		return ['body' => $html, 'assets' => ['styles' => array_values(array_unique($styles)), 'scripts' => array_values(array_unique($scripts))]];
	}

	/**
	 * Returns HTML as plain text, on one line.
	 */
	private static function text(string $html): string
	{
		$text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

		return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
	}

	/**
	 * Returns whether a relation is one the API lists on its sources:
	 * not a parent, which is `parent`, or a translation.
	 */
	public static function lists(Relation $relation): bool
	{
		return ! $relation->kind->isWithinType();
	}
}
