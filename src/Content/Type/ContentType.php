<?php

/**
 * Content type.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Type;

use DateTimeInterface;
use NoDiscard;
use Blush\Storage\Record\Order;
use Blush\Field\Definition;
use Blush\Field\Field;
use Blush\Field\FieldFactory;
use Blush\Field\InvalidSchema;
use Blush\Field\Schema;

/**
 * A content type: its entries, how they're routed, listed, and fed, and
 * the fields they have. Every type but the site's pages is kept in `_`
 * and its name (`_post`, D-683), which no definition names; its URLs
 * come from its settings, never its folder. The kinds are final
 * classes (D-157): `Collection` for listed entries such as posts (and
 * terms: a collection a classify relation files entries under, D-593),
 * `Tree` for entries that nest by folder (the built-in page type, which claims the content
 * root, is one; D-386), and `Profiles` for the
 * people entries credit (D-351). One model serves types from code and
 * from data (D-042). A type credits people through credit relations
 * (D-602), such as `authors`; `byline` names the one that's its byline.
 *
 * `fromArray()` builds any kind from a definition array (its `kind`)
 * and also accepts 1.x's option names (D-078), such as `path`,
 * `routing`, and `date_archives`. A taxonomy (`kind: taxonomy`, or 1.x's
 * `taxonomy: true`) is refused, saying what replaced it (D-591).
 */
abstract readonly class ContentType
{
	/**
	 * Names no type may have (D-683): the `_` folders the site's pages
	 * keep at the content root, `_system` (for system pages such as
	 * errors, D-684) and the `_error` it replaces, and `_drafts`.
	 */
	public const array RESERVED = ['system', 'error', 'drafts'];

	/**
	 * The folder under `user/content` the type's files are kept in:
	 * `_` and its name (D-683), or `''`, the content root, for the
	 * site's pages.
	 */
	public string $folder;

	/**
	 * The folders a collection or profiles type keeps its files in below
	 * its own (D-629), `{year}` say, or `null` for none: its files are
	 * directly in its folder. Only a site keeping content in files has
	 * them.
	 */
	public ?FolderPattern $folders;

	/**
	 * The type's own fields, beyond the built-in ones.
	 */
	public Schema $schema;

	/**
	 * What people call the type and its entries ("Literary genres", "New
	 * literary genre"), such as in the admin.
	 */
	public TypeLabels $labels;

	/**
	 * What the type is for, in a sentence, such as the admin's empty
	 * list; `''` for none.
	 */
	public string $description;

	/**
	 * The name of an icon the admin shows the type with (an `icon`
	 * directive name, such as `film`), or `null` for its kind's.
	 */
	public ?string $icon;

	/**
	 * The name of the credit relation that's its entries' byline (D-602),
	 * or `null` for its only one (`ContentTypes::byline()`).
	 */
	public ?string $byline;

	/**
	 * @param  string            $name         Lowercase letters, digits, and underscores; not one of `RESERVED`.
	 * @param  ?string           $folders      A folder pattern for the folders below the type's (`{year}`, D-629), or `null` for none.
	 * @param  bool              $public       Whether the type is public at all.
	 * @param  TypeUrls|false    $urls         URL settings, or `false` for no routes.
	 * @param  Listing           $listing      How the type's listing page lists entries.
	 * @param  TypeFeed|false    $feed         Feed settings, or `false` for no feed.
	 * @param  bool              $sitemap      Whether entries are in the sitemap.
	 * @param  DateArchives      $dateArchives How finely date archives go.
	 * @param  iterable<Field>   $fields       Fields beyond the built-in ones.
	 * @param  bool              $closed       Whether undeclared front matter is an error.
	 * @param  ?TypeLabels       $labels       Defaults to labels made from the name.
	 * @param  string            $description  What the type is for, in a sentence.
	 * @param  ?string           $icon         An icon name for the admin.
	 * @param  ?string           $byline       The credit relation that's its byline, or `null` for its only one.
	 * @param  bool              $llms         Whether entries are listed in `llms.txt` (D-398; each kind's default, `TypeKind::inLlmsByDefault()`).
	 * @param  ?FileName         $filename     How new files are named (D-511, D-514); the slug alone by default.
	 * @throws InvalidContentType
	 */
	protected function __construct(
		public string $name,
		?string $folders,
		public bool $public,
		public TypeUrls|false $urls,
		public Listing $listing,
		public TypeFeed|false $feed,
		public bool $sitemap,
		public DateArchives $dateArchives,
		iterable $fields,
		bool $closed,
		?TypeLabels $labels = null,
		string $description = '',
		?string $icon = null,
		?string $byline = null,
		public bool $llms = true,
		public ?FileName $filename = null
	) {
		if (preg_match('/^[a-z][a-z0-9_]*$/', $name) !== 1) {
			throw new InvalidContentType(sprintf(
				'Content type name "%s" must start with a lowercase letter and use only lowercase letters, digits, and underscores.',
				$name
			));
		}

		if (in_array($name, self::RESERVED, true)) {
			throw new InvalidContentType(sprintf(
				'"%s" can\'t name a content type: _%s is a folder the site\'s pages keep (the reserved names are %s).',
				$name,
				$name,
				implode(', ', self::RESERVED)
			));
		}

		try {
			$this->schema = new Schema($fields, $closed);
		} catch (InvalidSchema $e) {
			throw new InvalidContentType(sprintf('Content type "%s" has invalid fields: %s', $name, $e->getMessage()), previous: $e);
		}

		$this->folder      = static::folderOf($name);
		$this->folders     = self::pattern($folders, $name, $this->keysByFolder());
		$this->labels      = $labels ?? TypeLabels::named($name);
		$this->description = trim($description);
		$this->icon        = $icon === null || trim($icon) === '' ? null : trim($icon);
		$this->byline      = $byline === null || trim($byline) === '' ? null : trim($byline);
	}

	/**
	 * Returns the type's kind.
	 */
	abstract public function kind(): TypeKind;

	/**
	 * Returns the URL prefix, without slashes: the URLs' prefix, or the
	 * type's name when there isn't one (D-683). Types without URLs have
	 * none.
	 */
	public function prefix(): string
	{
		if ($this->urls === false) {
			return '';
		}

		return $this->urls->prefix ?? $this->name;
	}

	/**
	 * Returns the path the page catch-all serves the type's entries
	 * under, without slashes: the type's name, or a tree's own prefix
	 * (D-683).
	 */
	public function pagePath(): string
	{
		return $this->name;
	}

	/**
	 * Returns the full route pattern for a route key, such as
	 * `/archives/{year}/{month}/{day}/{name}` for `single`, or `null` when
	 * the type has no URLs or no such key. `$more` adds default paths
	 * for keys the type doesn't know itself: its relations' archives
	 * (`ContentTypes::routePattern()`, D-596).
	 *
	 * @param array<string, string> $more
	 */
	public function routePattern(string $key, array $more = []): ?string
	{
		$path = $this->urls === false ? null : $this->urls->path($key) ?? $more[$key] ?? null;

		return $path === null ? null : '/' . trim($this->prefix() . '/' . $path, '/');
	}

	/**
	 * Returns whether the type has routes of its own.
	 */
	public function hasUrls(): bool
	{
		return $this->urls !== false;
	}


	/**
	 * Returns whether the page catch-all serves the type's entries at
	 * their folder paths, as 1.x did for types without routes.
	 */
	public function servedAsPages(): bool
	{
		return ! $this->hasUrls();
	}

	/**
	 * Returns whether the type has a feed.
	 */
	public function hasFeed(): bool
	{
		return $this->feed !== false;
	}

	/**
	 * Returns the type the listing page lists: the listing's `type`, or the
	 * type itself.
	 */
	public function listedType(): string
	{
		return $this->listing->type ?? $this->name;
	}

	/**
	 * Returns the listing page's 1.x query arguments, for
	 * `Query::fromArray()`.
	 *
	 * @return array<string, mixed>
	 */
	public function listingArguments(): array
	{
		return [...$this->listing->arguments(), 'type' => $this->listedType()];
	}

	/**
	 * Returns the key of an entry's parent in this type, from its key and
	 * normalized front matter, or `null` when it has none. Only trees and
	 * hierarchical collections nest.
	 *
	 * @param array<string, mixed> $values
	 */
	public function parentKey(string $key, array $values): ?string
	{
		return null;
	}

	/**
	 * Returns whether the folders below the type's are part of its
	 * entries' keys, as a tree's are (D-088). A collection's and the
	 * profiles' are only where files are kept (D-629): `_post/2026/hello.md`
	 * is `hello`.
	 */
	public function keysByFolder(): bool
	{
		return false;
	}

	/**
	 * Returns the folder an entry's file is kept in: the type's, then the
	 * folders its pattern gives the slug and date (D-629). With `_`
	 * folders given (`_drafts`), it's those instead: a hidden file is
	 * kept where it is, outside the pattern.
	 *
	 * @param list<string> $hidden
	 */
	public function directoryFor(string $slug, DateTimeInterface $date, array $hidden = []): string
	{
		return ltrim(implode('/', [$this->folder, ...($hidden === [] ? $this->folders?->segments($slug, $date) ?? [] : $hidden)]), '/');
	}

	/**
	 * Returns the `_` folders between the type's folder and a path's
	 * file (`_drafts`), which keep what they mean wherever the file is
	 * kept; a folder entry's own folder isn't one of them.
	 *
	 * @return list<string>
	 */
	public function hiddenFolders(string $path): array
	{
		$below    = $this->folder === '' ? $path : substr($path, strlen($this->folder) + 1);
		$segments = explode('/', $below);
		$file     = array_pop($segments);

		if ($segments !== [] && str_starts_with((string) $file, 'index.')) {
			array_pop($segments);
		}

		return array_values(array_filter($segments, static fn (string $segment): bool => str_starts_with($segment, '_')));
	}

	/**
	 * Returns how the type names the files it creates: its own pattern
	 * (`filename`, any kind, D-511, D-514), else the slug alone (D-515).
	 */
	public function naming(): FileName
	{
		return $this->filename ?? FileName::byDefault();
	}

	/**
	 * Returns how the type's entries are ordered when nothing says
	 * otherwise, as `Query::orderBy()` takes it: newest published first
	 * here. Never by file, which a database doesn't have (D-516).
	 *
	 * @return array{string, Order}
	 */
	public function order(): array
	{
		return ['published', Order::Desc];
	}

	/**
	 * Builds a type from a definition array. Keys are the kind's
	 * constructor parameter names or the 1.x option names; `urls` may be
	 * `false` or a map (`TypeUrls::fromArray()`), `listing` a map
	 * (`Listing::fromArray()`), `feed` a boolean or a map
	 * (`TypeFeed::fromArray()`), `dateArchives` names a `DateArchives`,
	 * `filename` is a `FileName` pattern, `folders` a `FolderPattern`, and
	 * `fields` (with `closed`) defines the schema. A type never names its
	 * folder (D-683): `folder`, or 1.x's `path`, is refused.
	 *
	 * @param  array<array-key, mixed> $data
	 * @throws InvalidContentType
	 */
	public static function fromArray(array $data, FieldFactory $fields): self
	{
		$name = $data['name'] ?? null;

		if (! is_string($name)) {
			throw new InvalidContentType('A content type definition needs a "name".');
		}

		$data    = self::renamed($data, $name);
		$kind    = self::kindOf($data, $name);

		if (array_key_exists('folder', $data)) {
			throw new InvalidContentType(self::namedFolder($name, $data['folder']));
		}

		if ($kind === TypeKind::Tree && array_key_exists('folders', $data)) {
			throw new InvalidContentType(sprintf('Content type "%s" has a folder pattern, but a tree\'s folders are its pages\', so it takes none.', $name));
		}

		$unknown = array_diff(array_map(strval(...), array_keys($data)), ['name', 'kind', ...$kind->options()]);

		if ($unknown !== []) {
			throw new InvalidContentType(sprintf('Content type "%s" (%s) has unknown options: %s.', $name, $kind->value, implode(', ', $unknown)));
		}

		$definition = new Definition($data, sprintf('Content type "%s"', $name));

		try {
			$schema = $fields->schema($definition->listOrMap('fields'), $definition->bool('closed'));
			$common = [
				'name'        => $name,
				'folders'     => $definition->nullableString('folders'),
				'public'      => $definition->bool('public', true),
				'sitemap'     => $definition->bool('sitemap', true),
				'fields'      => array_values($schema->fields),
				'closed'      => $schema->closed,
				'labels'      => TypeLabels::fromArray($definition->map('labels'), $name),
				'description' => $definition->nullableString('description') ?? '',
				'icon'        => $definition->nullableString('icon'),
				'filename'    => self::filename($definition, $name)
			];

			if ($kind === TypeKind::Profiles) {
				return new Profiles(...[
					...$common,
					'llms'    => $definition->bool('llms', false),
					'urls'    => self::urls($data['urls'] ?? [], $name),
					'listing' => Listing::fromArray($definition->map('listing'), sprintf('Content type "%s" listing', $name)),
					'feed'    => self::feed($data['feed'] ?? false, $name)
				]);
			}

			$common['byline'] = is_string($data['byline'] ?? null) ? $data['byline'] : null;

			if ($kind === TypeKind::Tree) {
				unset($common['folders']);

				return new Tree(...[...$common, 'llms' => $definition->bool('llms', true), 'prefix' => $definition->nullableString('prefix')]);
			}

			$common = [
				...$common,
				'urls'    => self::urls($data['urls'] ?? [], $name),
				'listing' => Listing::fromArray($definition->map('listing'), sprintf('Content type "%s" listing', $name)),
				'feed'    => self::feed($data['feed'] ?? false, $name)
			];

			$order = TypeOrder::tryFrom($definition->nullableString('order') ?? TypeOrder::Published->value)
				?? throw new InvalidContentType(sprintf('Content type "%s" "order" must be one of %s.', $name, implode(', ', array_column(TypeOrder::cases(), 'value'))));

			return new Collection(...[
				...$common,
				'dateArchives' => self::dateArchives($definition, $name),
				'llms'         => $definition->bool('llms', true),
				'hierarchical' => $definition->bool('hierarchical'),
				'order'        => $order
			]);
		} catch (InvalidSchema $e) {
			throw new InvalidContentType($e->getMessage(), previous: $e);
		}
	}

	/**
	 * Returns whether a data file may change the type when it's defined
	 * in code (D-349): collections, taxonomies, and trees in a folder
	 * (D-386). The site's pages and its profiles type stay as the code
	 * defines them.
	 */
	public function isOverridable(): bool
	{
		return true;
	}

	/**
	 * Returns what the type is to the site, for messages about a type
	 * that can't be overridden: "the site's pages", say.
	 */
	public function role(): string
	{
		return sprintf('a %s type', $this->kind()->value);
	}

	/**
	 * Returns the type with a data file's options laid over it (D-349):
	 * each option the data names replaces the type's whole option, by its
	 * 2.x or 1.x name. The name and kind stay the type's, since entries
	 * are filed by them, though its folder pattern may change (D-629).
	 * The site's pages and profiles types can't be overridden
	 * (`isOverridable()`).
	 *
	 * @param  array<array-key, mixed> $data
	 * @throws InvalidContentType
	 */
	public function overriddenBy(array $data, FieldFactory $fields): self
	{
		if (! $this->isOverridable()) {
			throw new InvalidContentType(sprintf(
				'The "%s" content type is %s, which user/data/types can\'t change; define it in one place.',
				$this->name,
				$this->role()
			));
		}

		$data = self::renamed($data, $this->name);
		$kind = array_key_exists('kind', $data) || array_key_exists('taxonomy', $data) ? self::kindOf($data, $this->name) : $this->kind();

		unset($data['name'], $data['kind']);

		if ($kind !== $this->kind()) {
			throw new InvalidContentType(sprintf('user/data/types/%s can\'t change the type\'s kind; entries are filed by it.', $this->name));
		}

		return self::fromArray([...$this->toArray(), ...$data], $fields);
	}

	/**
	 * Returns the type as a definition array that `fromArray()` accepts,
	 * leaving out settings at their defaults.
	 *
	 * @return array<string, mixed>
	 */
	public function toArray(): array
	{
		$data = [
			'name'        => $this->name,
			'kind'        => $this->kind()->value,
			'folders'     => $this->folders?->pattern,
			'urls'        => $this->urls === false ? false : ($this->urls->toArray() ?: null),
			'listing'     => $this->listing->toArray(),
			'feed'        => $this->feed === false ? null : ($this->feed->toArray() ?: true),
			'public'      => $this->public ? null : false,
			'sitemap'     => $this->sitemap ? null : false,
			'llms'        => $this->llms === $this->kind()->inLlmsByDefault() ? null : $this->llms,
			'labels'      => $this->labels->toArray($this->name),
			'description' => $this->description === '' ? null : $this->description,
			'icon'        => $this->icon,
			'filename'    => $this->filename?->pattern,
			...$this->options(),
			...$this->schema->toArray()
		];

		$data = array_intersect_key($data, array_flip(['name', 'kind', ...$this->kind()->options()]));

		return array_filter($data, static fn (mixed $value): bool => $value !== null && $value !== []);
	}

	/**
	 * Returns the kind's own settings for `toArray()`.
	 *
	 * @return array<string, mixed>
	 */
	protected function options(): array
	{
		return [];
	}


	/**
	 * Returns the folder a type is kept in: `_` and its name (D-683), so
	 * type folders stand apart from the page folders beside them in the
	 * content root. The site's pages are the content root itself.
	 */
	protected static function folderOf(string $name): string
	{
		return "_{$name}";
	}

	/**
	 * Returns why a definition naming its folder is refused (D-683),
	 * saying what to do instead.
	 */
	public static function namedFolder(string $name, mixed $folder): string
	{
		return sprintf(
			'Content type "%s" names its folder (%s), but every type is kept in _%s now: move its files there, and give it a URL prefix if its addresses came from the folder. For a type in user/data/types, content:type-folders --write (or Site Health) does both.',
			$name,
			is_string($folder) ? "\"{$folder}\"" : 'with "folder"',
			$name
		);
	}

	/**
	 * Moves 1.x option names to their 2.x names.
	 *
	 * @param  array<array-key, mixed> $data
	 * @return array<array-key, mixed>
	 * @throws InvalidContentType
	 */
	private static function renamed(array $data, string $name): array
	{
		$renames = [
			'path'            => 'folder',
			'routing'         => 'urls',
			'collection'      => 'listing'
		];

		foreach ($renames as $old => $new) {
			if (array_key_exists($old, $data)) {
				$data[$new] ??= $data[$old];
				unset($data[$old]);
			}
		}

		// Credits are relations (D-602).
		foreach (['people', 'authors'] as $gone) {
			if (array_key_exists($gone, $data)) {
				throw new InvalidContentType(sprintf('Content type "%s" sets "%s", but a type credits people through a credit relation now, defined with the others (in user/data/relations or a plugin): {"kind": "credit", "from": ["%s"], "to": ["profile"]}.', $name, $gone, $name));
			}
		}

		if (array_key_exists('collect', $data)) {
			$collect = $data['collect'];
			unset($data['collect']);

			if (is_string($collect)) {
				$data['listing'] = [...(is_array($data['listing'] ?? null) ? $data['listing'] : []), 'type' => $collect];
			} elseif ($collect !== false && $collect !== null) {
				throw new InvalidContentType(sprintf('Content type "%s" "collect" must be a type name or false.', $name));
			}
		}

		if (array_key_exists('date_archives', $data) || array_key_exists('time_archives', $data)) {
			$flags = new Definition($data, sprintf('Content type "%s"', $name));

			try {
				$data['dateArchives'] ??= DateArchives::fromFlags($flags->bool('date_archives'), $flags->bool('time_archives'))->value;
			} catch (InvalidSchema $e) {
				throw new InvalidContentType($e->getMessage(), previous: $e);
			}

			unset($data['date_archives'], $data['time_archives']);
		}

		return $data;
	}

	/**
	 * Returns the kind a definition names with `kind`, and drops 1.x's
	 * `taxonomy: false`. A taxonomy is refused with what replaced it
	 * (D-591, D-593): a collection and a classify relation, which
	 * `content:taxonomies --write` (or Site Health) writes for a data type.
	 *
	 * @param  array<array-key, mixed> $data
	 * @throws InvalidContentType
	 */
	private static function kindOf(array &$data, string $name): TypeKind
	{
		$taxonomy = $data['taxonomy'] ?? null;
		$kind     = $data['kind'] ?? null;
		unset($data['taxonomy']);

		if ($taxonomy === true || $kind === 'taxonomy') {
			throw new InvalidContentType(sprintf(
				'Content type "%s" is a taxonomy, which Blush no longer has: make it a collection (with "order: position", and "hierarchical: true" if its terms nest) and define a classify relation named "%s" in the content config\'s "relations" or user/data/relations. For a type in user/data/types, content:taxonomies --write does this.',
				$name,
				$name
			));
		}

		if ($taxonomy !== null && $taxonomy !== false) {
			throw new InvalidContentType(sprintf('Content type "%s" "taxonomy" must be true or false.', $name));
		}

		if ($kind === null) {
			return TypeKind::Collection;
		}

		$case = is_string($kind) ? TypeKind::tryFrom($kind) : null;

		return $case ?? throw new InvalidContentType(sprintf(
			'Content type "%s" "kind" must be one of %s.',
			$name,
			implode(', ', array_column(TypeKind::cases(), 'value'))
		));
	}

	/**
	 * Reads the `urls` option.
	 *
	 * @throws InvalidSchema
	 */
	private static function urls(mixed $value, string $name): TypeUrls|false
	{
		if ($value === false) {
			return false;
		}

		if (! is_array($value)) {
			throw new InvalidSchema(sprintf('Content type "%s" "urls" must be false or a map.', $name));
		}

		return TypeUrls::fromArray($value, sprintf('Content type "%s" urls', $name));
	}

	/**
	 * Reads the `feed` option.
	 *
	 * @throws InvalidSchema
	 * @throws InvalidContentType
	 */
	private static function feed(mixed $value, string $name): TypeFeed|false
	{
		return match (true) {
			$value === false => false,
			$value === true  => new TypeFeed(),
			is_array($value) => TypeFeed::fromArray($value, sprintf('Content type "%s" feed', $name)),
			default          => throw new InvalidSchema(sprintf('Content type "%s" "feed" must be true, false, or a map.', $name))
		};
	}

	/**
	 * Reads the `dateArchives` option.
	 *
	 * @throws InvalidSchema
	 */
	private static function dateArchives(Definition $definition, string $name): DateArchives
	{
		return DateArchives::tryFrom($definition->string('dateArchives', DateArchives::None->value))
			?? throw new InvalidSchema(sprintf(
				'Content type "%s" "dateArchives" must be one of %s.',
				$name,
				implode(', ', array_column(DateArchives::cases(), 'value'))
			));
	}

	/**
	 * Reads the `filename` option.
	 *
	 * @throws InvalidSchema
	 * @throws InvalidContentType
	 */
	private static function filename(Definition $definition, string $name): ?FileName
	{
		$pattern = $definition->nullableString('filename');

		try {
			return $pattern === null || trim($pattern) === '' ? null : new FileName(trim($pattern));
		} catch (InvalidContentType $e) {
			throw new InvalidContentType(sprintf('Content type "%s": %s', $name, $e->getMessage()), previous: $e);
		}
	}

	/**
	 * Reads a type's folder pattern (D-629), which a type keyed by folder
	 * can't have.
	 *
	 * @throws InvalidContentType
	 */
	private static function pattern(?string $pattern, string $name, bool $keysByFolder): ?FolderPattern
	{
		$pattern = trim(str_replace('\\', '/', $pattern ?? ''), '/ ');

		if ($pattern === '') {
			return null;
		}

		if ($keysByFolder) {
			throw new InvalidContentType(sprintf('Content type "%s" has the folder pattern "%s"; a tree\'s folders are its pages\', so it takes none.', $name, $pattern));
		}

		try {
			return new FolderPattern($pattern);
		} catch (InvalidContentType $e) {
			throw new InvalidContentType(sprintf('Content type "%s": %s', $name, $e->getMessage()), previous: $e);
		}
	}
}
