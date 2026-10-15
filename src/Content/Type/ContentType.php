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
use Blush\Field\FieldControls;
use Blush\Field\FieldFactory;
use Blush\Field\FieldLayout;
use Blush\Field\InvalidSchema;
use Blush\Field\Schema;
use Blush\Translation\Messages;
use Blush\Translation\Text;

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
	 * errors, D-684) and the `_error` it replaces.
	 */
	public const array RESERVED = ['system', 'error'];

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
	 * block name, such as `film`), or `null` for its kind's.
	 */
	public ?string $icon;

	/**
	 * The name of the credit relation that's its entries' byline (D-602),
	 * or `null` for its only one (`ContentTypes::byline()`).
	 */
	public ?string $byline;

	/**
	 * The name of the type this one sits under in the admin's menu
	 * (D-726, `adminUnder`, D-866), or `null` for the top level. It's only
	 * where the admin shows it: no address, folder, or capability follows
	 * it.
	 */
	public ?string $adminUnder;

	/**
	 * The options that place a type in the admin's menu (D-726, D-866),
	 * which a data file may set over any type, even one it can't
	 * otherwise change: the built-in pages, say.
	 */
	public const array PLACEMENT = ['adminPosition', 'adminUnder'];

	/**
	 * The switches for core's optional entry fields (D-864, D-866), each
	 * `true` or `false`, off when it's missing: a type that's off doesn't
	 * declare the field. `visibility` and `expires` have more to them
	 * (D-855, D-852).
	 */
	public const array SUPPORTS = ['image', 'summary', 'subtitle', 'position', 'visibility', 'expires'];

	/**
	 * What a data file may set over any type (D-726, D-858): its place
	 * in the admin's menu and the library fields it carries.
	 */
	public const array OVERLAY = [...self::PLACEMENT, 'fields'];

	/**
	 * The key a definition holds a code type's own fields under (D-863),
	 * which only code and the compiled types write; a site's file lists
	 * library fields under `fields` (its layout), and can't use it.
	 */
	public const string OWN_FIELDS = 'ownFields';

	/**
	 * The name of the workflow its entries move through (D-735, D-851),
	 * or `null` for core's `standard` (`Workflows::for()`).
	 */
	public ?string $workflow;

	/**
	 * @param  string          $name         Lowercase letters, digits, and underscores; not one of `RESERVED`.
	 * @param  ?string         $folders      A folder pattern for the folders below the type's (`{year}`, D-629), or `null` for none.
	 * @param  bool            $public       Whether the type is public at all.
	 * @param  TypeUrls|false  $urls         URL settings, or `false` for no routes.
	 * @param  Listing         $listing      How the type's listing page lists entries.
	 * @param  TypeFeed|false  $feed         Feed settings, or `false` for no feed.
	 * @param  bool            $sitemap      Whether entries are in the sitemap.
	 * @param  DateArchives    $dateArchives How finely date archives go.
	 * @param  iterable<Field> $fields       Fields beyond the built-in ones.
	 * @param  bool            $closed       Whether undeclared front matter is an error.
	 * @param  ?TypeLabels     $labels       Defaults to labels made from the name.
	 * @param  string          $description  What the type is for, in a sentence.
	 * @param  ?string         $icon         An icon name for the admin.
	 * @param  ?string         $byline       The credit relation that's its byline, or `null` for its only one.
	 * @param  bool            $llms         Whether entries are listed in `llms.txt` (D-398; each kind's default, `TypeKind::inLlmsByDefault()`).
	 * @param  ?FileName       $filename     How new files are named (D-511, D-514); the slug alone by default.
	 * @param  ?int            $adminPosition Its order in the admin's menu (D-726, D-866), lowest first; `null` after those with one, by name.
	 * @param  ?string         $adminUnder   The type it sits under in the admin's menu (D-726, D-866), or `null` for the top level.
	 * @param  ?string         $workflow     The workflow its entries move through (D-851), or `null` for core's `standard`.
	 * @param  bool            $expires      Whether its entries may have an `expires` date, after which they're expired (D-852).
	 * @param  bool            $visibility   Whether its entries choose a visibility (D-855); off, every entry is public. Only a public type has it. Off unless it's on (D-866).
	 * @param  Messages        $messages     Writes why a type is refused; English without a translator, for a type in code.
	 * @param  FieldLayout     $layout       The library fields it carries, in sections (D-858), written as its definition's `fields` (D-863).
	 * @param  bool            $image        Whether entries have a featured `image` (D-866).
	 * @param  bool            $summary      Whether entries have a `summary` (D-866).
	 * @param  bool            $subtitle     Whether entries have a `subtitle` (D-866).
	 * @param  bool            $position     Whether entries have a `position` among their siblings (D-412, D-866).
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
		public ?FileName $filename = null,
		public ?int $adminPosition = null,
		?string $adminUnder = null,
		public FieldControls $controls = new FieldControls(),
		?string $workflow = null,
		public bool $expires = false,
		public bool $visibility = false,
		Messages $messages = new Messages(),
		public FieldLayout $layout = new FieldLayout(),
		public bool $image = false,
		public bool $summary = false,
		public bool $subtitle = false,
		public bool $position = false
	) {
		if (preg_match('/^[a-z][a-z0-9_]*$/', $name) !== 1) {
			throw new InvalidContentType((string) $messages->t(
				'content.content_type.bad_name',
				'Content type name "{name}" must start with a lowercase letter and use only lowercase letters, digits, and underscores.',
				name: $name
			));
		}

		if (in_array($name, self::RESERVED, true)) {
			throw new InvalidContentType((string) $messages->t(
				'content.content_type.reserved_name',
				'"{name}" can\'t name a content type: _{name} is a folder the site\'s pages keep (the reserved names are {reserved}).',
				name: $name,
				reserved: implode(', ', self::RESERVED)
			));
		}

		try {
			$this->schema = new Schema($fields, $closed);
		} catch (InvalidSchema $e) {
			throw new InvalidContentType((string) $messages->t('content.content_type.invalid_fields', 'Content type "{name}" has invalid fields: {reason}', name: $name, reason: $e->getMessage()), previous: $e);
		}

		$this->folder      = static::folderOf($name);
		$this->folders     = self::pattern($folders, $name, $this->keysByFolder(), $messages);
		$this->labels      = $labels ?? TypeLabels::named($name);
		$this->description = trim($description);
		$this->icon        = $icon === null || trim($icon) === '' ? null : trim($icon);
		$this->byline      = $byline === null || trim($byline) === '' ? null : trim($byline);
		$this->adminUnder  = $adminUnder === null || trim($adminUnder) === '' ? null : trim($adminUnder);
		$this->workflow    = $workflow === null || trim($workflow) === '' ? null : trim($workflow);

		if ($adminPosition !== null && $adminPosition < 0) {
			throw new InvalidContentType((string) $messages->t('content.content_type.negative_position', 'Content type "{name}" "adminPosition" must be 0 or more.', name: $name));
		}

		if ($this->adminUnder === $name) {
			throw new InvalidContentType((string) $messages->t('content.content_type.under_itself', 'Content type "{name}" can\'t sit under itself.', name: $name));
		}
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
	 * Returns whether its entries choose a visibility (D-855): a public
	 * type's, unless it turns it off. Without it, every entry is public.
	 */
	public function hasVisibility(): bool
	{
		return $this->public && $this->visibility;
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
	 * folders given (a relation archive's `_authors`), it's those
	 * instead: an archive's page is kept where it is, outside the pattern.
	 *
	 * @param list<string> $hidden
	 */
	public function directoryFor(string $slug, DateTimeInterface $date, array $hidden = []): string
	{
		return ltrim(implode('/', [$this->folder, ...($hidden === [] ? $this->folders?->segments($slug, $date) ?? [] : $hidden)]), '/');
	}

	/**
	 * Returns the `_` folders between the type's folder and a path's
	 * file (a relation archive's `_authors`), which keep what they mean
	 * wherever the file is kept; a folder entry's own folder isn't one
	 * of them.
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
	 * otherwise, as `Query::orderBy()` takes it: newest first by date
	 * here. Never by file, which a database doesn't have (D-516).
	 *
	 * @return array{string, Order}
	 */
	public function order(): array
	{
		return ['date', Order::Desc];
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
	 * @param  Messages                $messages Writes why a definition is refused.
	 * @throws InvalidContentType
	 */
	public static function fromArray(array $data, FieldFactory $fields, Messages $messages): self
	{
		$name = $data['name'] ?? null;

		if (! is_string($name)) {
			throw new InvalidContentType((string) $messages->t('content.content_type.no_name', 'A content type definition needs a "name".'));
		}

		$data    = self::renamed($data, $name, $messages);
		$kind    = self::kindOf($data, $name, $messages);

		if (array_key_exists('folder', $data)) {
			throw new InvalidContentType((string) self::namedFolder($name, $data['folder'], $messages));
		}

		if ($kind === TypeKind::Tree && array_key_exists('folders', $data)) {
			throw new InvalidContentType((string) $messages->t('content.content_type.tree_folders', 'Content type "{name}" has a folder pattern, but a tree\'s folders are its pages\', so it takes none.', name: $name));
		}

		$unknown = array_diff(array_map(strval(...), array_keys($data)), ['name', 'kind', ...$kind->options()]);

		if ($unknown !== []) {
			throw new InvalidContentType((string) $messages->t(
				'content.content_type.unknown_options',
				'Content type "{name}" ({kind}) has unknown options: {options}.',
				name: $name,
				kind: $kind->value,
				options: implode(', ', $unknown)
			));
		}

		$definition = new Definition($data, (string) $messages->t('content.content_type.subject', 'Content type "{name}"', name: $name));

		try {
			[$own, $controls] = $fields->withControls($definition->listOrMap(self::OWN_FIELDS));
			$schema           = new Schema($own, $definition->bool('closed'));
			$common = [
				'name'        => $name,
				'folders'     => $definition->nullableString('folders'),
				'public'      => $definition->bool('public', true),
				'sitemap'     => $definition->bool('sitemap', true),
				'fields'      => array_values($schema->fields),
				'closed'      => $schema->closed,
				'labels'      => TypeLabels::fromArray($definition->map('labels'), $name, $messages, BuiltInType::labelsFor($kind, $name, $messages)),
				'description' => $definition->nullableString('description') ?? '',
				'icon'        => $definition->nullableString('icon'),
				'filename'    => self::filename($definition, $name, $messages),
				'controls'    => $controls,
				'workflow'    => $definition->nullableString('workflow'),
				'expires'     => $definition->bool('expires'),
				'visibility'  => $definition->bool('visibility'),
				'image'       => $definition->bool('image'),
				'summary'     => $definition->bool('summary'),
				'subtitle'    => $definition->bool('subtitle'),
				'position'    => $definition->bool('position'),
				'messages'    => $messages,
				'layout'      => FieldLayout::fromArray($data['fields'] ?? null, (string) $messages->t('content.content_type.subject', 'Content type "{name}"', name: $name))
			];


			if ($kind === TypeKind::Profiles) {
				return new Profiles(...[
					...$common,
					'llms'    => $definition->bool('llms', false),
					'urls'    => self::urls($data['urls'] ?? [], $name, $messages),
					'listing' => Listing::fromArray($definition->map('listing'), $messages->t('content.content_type.listing_subject', 'Content type "{name}" listing', name: $name), $messages),
					'feed'    => self::feed($data['feed'] ?? false, $name, $messages)
				]);
			}

			$common['byline'] = is_string($data['byline'] ?? null) ? $data['byline'] : null;

			// A place in the admin's menu (D-726); profiles, listed with
			// people, have none.
			$common['adminPosition'] = self::position($definition, $name, $messages);
			$common['adminUnder']    = $definition->nullableString('adminUnder');

			if ($kind === TypeKind::Tree) {
				unset($common['folders']);

				return new Tree(...[...$common, 'llms' => $definition->bool('llms', true), 'prefix' => $definition->nullableString('prefix')]);
			}

			$common = [
				...$common,
				'urls'    => self::urls($data['urls'] ?? [], $name, $messages),
				'listing' => Listing::fromArray($definition->map('listing'), $messages->t('content.content_type.listing_subject', 'Content type "{name}" listing', name: $name), $messages),
				'feed'    => self::feed($data['feed'] ?? false, $name, $messages)
			];

			$order = TypeOrder::tryFrom($definition->nullableString('order') ?? TypeOrder::Date->value)
				?? throw new InvalidContentType((string) $messages->t(
					'content.content_type.bad_order',
					'Content type "{name}" "order" must be one of {orders}.',
					name: $name,
					orders: implode(', ', array_column(TypeOrder::cases(), 'value'))
				));

			return new Collection(...[
				...$common,
				'dateArchives' => self::dateArchives($definition, $name, $messages),
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
	 * that can't be overridden, as the key of a `{role, select, …}` in a message: `pages`
	 * for the site's pages, `profiles` for its profiles type, else its
	 * kind.
	 */
	public function role(): string
	{
		return $this->kind()->value;
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
	public function overriddenBy(array $data, FieldFactory $fields, Messages $messages): self
	{
		if (! $this->isOverridable()) {
			throw new InvalidContentType((string) $messages->t(
				'content.content_type.not_overridable',
				'The "{name}" content type is {role, select, pages {the site\'s pages} profiles {the site\'s profiles type} collection {a collection type} other {a tree type}}, which user/data/types can\'t change; define it in one place.',
				name: $this->name,
				role: $this->role()
			));
		}

		$data = self::renamed($data, $this->name, $messages);
		$kind = array_key_exists('kind', $data) || array_key_exists('taxonomy', $data) ? self::kindOf($data, $this->name, $messages) : $this->kind();

		unset($data['name'], $data['kind']);

		if ($kind !== $this->kind()) {
			throw new InvalidContentType((string) $messages->t('content.content_type.kind_changed', 'user/data/types/{name} can\'t change the type\'s kind; entries are filed by it.', name: $this->name));
		}

		return self::fromArray([...$this->toArray(), ...$data], $fields, $messages);
	}

	/**
	 * Returns the type with its labels' messages read through the site's
	 * `Messages` (D-842), for a type a plugin made in code.
	 */
	public function readBy(Messages $messages): static
	{
		return clone($this, ['labels' => $this->labels->in($messages)]);
	}

	/**
	 * Returns the type with a data file laid over it that sets only what
	 * any type takes (`OVERLAY`): its place in the admin's menu
	 * (`position` and `under`, D-726; not the profiles type's) and the
	 * library fields it carries (`fields`, D-858, D-863), even over a type the
	 * file can't otherwise change.
	 *
	 * @param  array<array-key, mixed> $data
	 * @throws InvalidContentType
	 */
	public function overlaidBy(array $data, FieldFactory $fields, Messages $messages): self
	{
		if (! self::onlyOverlays($data)) {
			throw new InvalidContentType((string) $messages->t('content.content_type.only_overlaid', '"{name}" can only be placed in the menu and given fields here, with "adminPosition", "adminUnder", and "fields".', name: $this->name));
		}

		if ($this->kind() === TypeKind::Profiles && array_intersect_key($data, array_flip(self::PLACEMENT)) !== []) {
			throw new InvalidContentType((string) $messages->t('content.content_type.profiles_not_placed', 'The "{name}" type is listed with people, so it isn\'t placed in the content menu.', name: $this->name));
		}

		return self::fromArray([...$this->toArray(), ...$data], $fields, $messages);
	}

	/**
	 * Returns whether a data file sets only what may lay over any type
	 * (`OVERLAY`).
	 *
	 * @param array<array-key, mixed> $data
	 */
	public static function onlyOverlays(array $data): bool
	{
		return $data !== [] && array_diff(array_map(strval(...), array_keys($data)), self::OVERLAY) === [];
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
			'labels'      => $this->labels->toArray($this->name, BuiltInType::labelsFor($this->kind(), $this->name, new Messages())),
			'description' => $this->description === '' ? null : $this->description,
			'icon'        => $this->icon,
			'filename'    => $this->filename?->pattern,
			'adminPosition' => $this->adminPosition,
			'adminUnder'  => $this->adminUnder,
			'workflow'    => $this->workflow,
			'expires'     => $this->expires ?: null,
			'visibility'  => $this->visibility ?: null,
			'image'       => $this->image ?: null,
			'summary'     => $this->summary ?: null,
			'subtitle'    => $this->subtitle ?: null,
			'position'    => $this->position ?: null,
			...$this->options(),
			self::OWN_FIELDS => $this->controls->definitions($this->schema->fields),
			'fields'      => $this->layout->toArray(),
			'closed'      => $this->schema->closed ?: null
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
	public static function namedFolder(string $name, mixed $folder, Messages $messages): Text
	{
		return is_string($folder)
			? $messages->t(
				'content.content_type.named_folder',
				'Content type "{name}" names its folder ("{folder}"), but every type is kept in _{name} now: move its files there, and give it a URL prefix if its addresses came from the folder. For a type in user/data/types, content:type-folders --write (or Site Health) does both.',
				name: $name,
				folder: $folder
			)
			: $messages->t(
				'content.content_type.named_folder_option',
				'Content type "{name}" names its folder (with "folder"), but every type is kept in _{name} now: move its files there, and give it a URL prefix if its addresses came from the folder. For a type in user/data/types, content:type-folders --write (or Site Health) does both.',
				name: $name
			);
	}

	/**
	 * Moves 1.x option names to their 2.x names.
	 *
	 * @param  array<array-key, mixed> $data
	 * @return array<array-key, mixed>
	 * @throws InvalidContentType
	 */
	private static function renamed(array $data, string $name, Messages $messages): array
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
				throw new InvalidContentType((string) $messages->t(
					'content.content_type.credits_moved',
					'Content type "{name}" sets "{option}", but a type credits people through a credit relation now, defined with the others (in user/data/relations or a plugin): {example}.',
					name: $name,
					option: $gone,
					example: "{\"kind\": \"credit\", \"from\": [\"{$name}\"], \"to\": [\"profile\"]}"
				));
			}
		}

		if (array_key_exists('collect', $data)) {
			$collect = $data['collect'];
			unset($data['collect']);

			if (is_string($collect)) {
				$data['listing'] = [...(is_array($data['listing'] ?? null) ? $data['listing'] : []), 'type' => $collect];
			} elseif ($collect !== false && $collect !== null) {
				throw new InvalidContentType((string) $messages->t('content.content_type.bad_collect', 'Content type "{name}" "collect" must be a type name or false.', name: $name));
			}
		}

		if (array_key_exists('date_archives', $data) || array_key_exists('time_archives', $data)) {
			$flags = new Definition($data, (string) $messages->t('content.content_type.subject', 'Content type "{name}"', name: $name));

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
	private static function kindOf(array &$data, string $name, Messages $messages): TypeKind
	{
		$taxonomy = $data['taxonomy'] ?? null;
		$kind     = $data['kind'] ?? null;
		unset($data['taxonomy']);

		if ($taxonomy === true || $kind === 'taxonomy') {
			throw new InvalidContentType((string) $messages->t(
				'content.content_type.taxonomy',
				'Content type "{name}" is a taxonomy, which Blush no longer has: make it a collection (with "order: position", and "hierarchical: true" if its terms nest) and define a classify relation named "{name}" in the content config\'s "relations" or user/data/relations. For a type in user/data/types, content:taxonomies --write does this.',
				name: $name
			));
		}

		if ($taxonomy !== null && $taxonomy !== false) {
			throw new InvalidContentType((string) $messages->t('content.content_type.bad_taxonomy', 'Content type "{name}" "taxonomy" must be true or false.', name: $name));
		}

		if ($kind === null) {
			return TypeKind::Collection;
		}

		$case = is_string($kind) ? TypeKind::tryFrom($kind) : null;

		return $case ?? throw new InvalidContentType((string) $messages->t(
			'content.content_type.bad_kind',
			'Content type "{name}" "kind" must be one of {kinds}.',
			name: $name,
			kinds: implode(', ', array_column(TypeKind::cases(), 'value'))
		));
	}

	/**
	 * Reads the `urls` option.
	 *
	 * @throws InvalidSchema
	 */
	private static function urls(mixed $value, string $name, Messages $messages): TypeUrls|false
	{
		if ($value === false) {
			return false;
		}

		if (! is_array($value)) {
			throw new InvalidSchema((string) $messages->t('content.content_type.bad_urls', 'Content type "{name}" "urls" must be false or a map.', name: $name));
		}

		return TypeUrls::fromArray($value, $messages->t('content.content_type.urls_subject', 'Content type "{name}" urls', name: $name), $messages);
	}

	/**
	 * Reads the `feed` option.
	 *
	 * @throws InvalidSchema
	 * @throws InvalidContentType
	 */
	private static function feed(mixed $value, string $name, Messages $messages): TypeFeed|false
	{
		return match (true) {
			$value === false => false,
			$value === true  => new TypeFeed(),
			is_array($value) => TypeFeed::fromArray($value, $messages->t('content.content_type.feed_subject', 'Content type "{name}" feed', name: $name), $messages),
			default          => throw new InvalidSchema((string) $messages->t('content.content_type.bad_feed', 'Content type "{name}" "feed" must be true, false, or a map.', name: $name))
		};
	}

	/**
	 * Reads the `dateArchives` option.
	 *
	 * @throws InvalidSchema
	 */
	private static function dateArchives(Definition $definition, string $name, Messages $messages): DateArchives
	{
		return DateArchives::tryFrom($definition->string('dateArchives', DateArchives::None->value))
			?? throw new InvalidSchema((string) $messages->t(
				'content.content_type.bad_date_archives',
				'Content type "{name}" "dateArchives" must be one of {values}.',
				name: $name,
				values: implode(', ', array_column(DateArchives::cases(), 'value'))
			));
	}

	/**
	 * Reads the `adminPosition` option (D-726, D-866): a whole number, 0
	 * or more.
	 *
	 * @throws InvalidSchema
	 * @throws InvalidContentType
	 */
	private static function position(Definition $definition, string $name, Messages $messages): ?int
	{
		$position = $definition->number('adminPosition');

		if ($position !== null && ! is_int($position)) {
			throw new InvalidContentType((string) $messages->t('content.content_type.position_not_whole', 'Content type "{name}" "adminPosition" must be a whole number.', name: $name));
		}

		return $position;
	}

	/**
	 * Reads the `filename` option.
	 *
	 * @throws InvalidSchema
	 * @throws InvalidContentType
	 */
	private static function filename(Definition $definition, string $name, Messages $messages): ?FileName
	{
		$pattern = $definition->nullableString('filename');

		try {
			return $pattern === null || trim($pattern) === '' ? null : new FileName(trim($pattern), $messages);
		} catch (InvalidContentType $e) {
			throw new InvalidContentType((string) $messages->t('content.content_type.problem', 'Content type "{name}": {problem}', name: $name, problem: $e->getMessage()), previous: $e);
		}
	}

	/**
	 * Reads a type's folder pattern (D-629), which a type keyed by folder
	 * can't have.
	 *
	 * @throws InvalidContentType
	 */
	private static function pattern(?string $pattern, string $name, bool $keysByFolder, Messages $messages): ?FolderPattern
	{
		$pattern = trim(str_replace('\\', '/', $pattern ?? ''), '/ ');

		if ($pattern === '') {
			return null;
		}

		if ($keysByFolder) {
			throw new InvalidContentType((string) $messages->t('content.content_type.tree_pattern', 'Content type "{name}" has the folder pattern "{pattern}"; a tree\'s folders are its pages\', so it takes none.', name: $name, pattern: $pattern));
		}

		try {
			return new FolderPattern($pattern, $messages);
		} catch (InvalidContentType $e) {
			throw new InvalidContentType((string) $messages->t('content.content_type.problem', 'Content type "{name}": {problem}', name: $name, problem: $e->getMessage()), previous: $e);
		}
	}
}
