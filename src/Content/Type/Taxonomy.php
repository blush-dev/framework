<?php

/**
 * Taxonomy content type.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Type;

use Override;
use Blush\Content\Query\Order;
use Blush\Content\Entry\Position;
use Blush\Field\Field;
use Blush\Field\Fields\ReferenceField;

/**
 * A type whose entries are terms that group other entries: tags,
 * categories, series. Entries join a term through the
 * taxonomy's field (its name, unless `field` says otherwise, plus any
 * `aliases`). Its listing page lists the terms, and each term's page
 * lists the entries of `types` (every type when empty) that reference it:
 *
 *     new Taxonomy(
 *         'category',
 *         folder: 'topics',
 *         types: ['post'],
 *         termListing: new Listing(order: Order::Desc)
 *     );
 *
 * A term doesn't need a file; one that's referenced but missing is a
 * virtual term.
 */
final readonly class Taxonomy extends ContentType
{
	/**
	 * The front matter key entries use to reference this taxonomy's terms.
	 */
	public string $field;

	/**
	 * @param  string          $name         Lowercase letters, digits, and underscores.
	 * @param  ?string         $folder       The folder under `user/content`; defaults to `_` and the name.
	 * @param  list<string>    $types        The types a term's page lists; empty for every type.
	 * @param  ?string         $field        The term field; defaults to the name.
	 * @param  list<string>    $aliases      Other keys the term field is read from.
	 * @param  TypeUrls|false  $urls         URL settings, or `false` for no routes.
	 * @param  Listing         $listing      How the taxonomy's listing page lists its terms.
	 * @param  Listing         $termListing  How a term's page lists entries.
	 * @param  TypeFeed|false  $feed         Feed settings (term feeds too), or `false` for no feeds.
	 * @param  bool            $public       Whether the taxonomy is public at all.
	 * @param  bool            $sitemap      Whether terms are in the sitemap.
	 * @param  iterable<Field> $fields       Fields beyond the built-in ones.
	 * @param  bool            $closed       Whether undeclared front matter is an error.
	 * @param  ?TypeLabels     $labels       What people call it; defaults to labels made from the name.
	 * @param  string          $description  What the taxonomy is for, in a sentence.
	 * @param  ?string         $icon         An icon name for the admin; defaults to its kind's.
	 * @param  bool            $hierarchical Whether a term may name a `parent` term.
	 * @param  array<PeopleField>|bool $people How entries credit people (D-351): `true` for `authors`.
	 * @param  bool            $llms         Whether terms are listed in `llms.txt` (D-401).
	 * @param  ?FileName       $filename    How new files are named (D-514).
	 * @throws InvalidContentType
	 */
	public function __construct(
		string $name,
		?string $folder = null,
		public array $types = [],
		?string $field = null,
		public array $aliases = [],
		TypeUrls|false $urls = new TypeUrls(),
		Listing $listing = new Listing(),
		public Listing $termListing = new Listing(),
		TypeFeed|false $feed = false,
		bool $public = true,
		bool $sitemap = true,
		iterable $fields = [],
		bool $closed = false,
		?TypeLabels $labels = null,
		string $description = '',
		?string $icon = null,
		public bool $hierarchical = false,
		array|bool $people = false,
		bool $llms = false,
		?FileName $filename = null
	) {
		parent::__construct($name, $folder, $public, $urls, $listing, $feed, $sitemap, DateArchives::None, $fields, $closed, $labels, $description, $icon, $people, $llms, $filename);

		$this->field = $field ?? $name;
	}

	/**
	 * Returns its entries' order: by position, then title (D-412).
	 *
	 * @inheritDoc
	 */
	#[Override]
	public function order(): array
	{
		return [Position::FIELD, Order::Asc];
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function kind(): TypeKind
	{
		return TypeKind::Taxonomy;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function termField(): ReferenceField
	{
		return new ReferenceField($this->field, $this->name)->aliases(...$this->aliases);
	}

	/**
	 * Returns the field a term names its parent term through, or `null`
	 * when the taxonomy isn't hierarchical. Parents are named, not
	 * folders, so a term keeps its file and URL when it moves in the
	 * tree, and slugs stay unique across the taxonomy.
	 */
	public function parentField(): ?ReferenceField
	{
		return $this->hierarchical
			? new ReferenceField('parent', $this->name, multiple: false)->described(sprintf('The parent %s, by slug.', $this->labels->item))
			: null;
	}

	/**
	 * Returns the term a hierarchical taxonomy's term names as its
	 * `parent`. A term can't be its own parent.
	 */
	#[Override]
	public function parentKey(string $key, array $values): ?string
	{
		$parent = $this->hierarchical ? ($values['parent'] ?? null) : null;

		return is_string($parent) && $parent !== '' && $parent !== $key ? $parent : null;
	}

	/**
	 * Lists terms in their order (`order()`) unless the listing says
	 * otherwise, in its direction when it gives one (D-516).
	 *
	 * @inheritDoc
	 */
	#[Override]
	public function listingArguments(): array
	{
		[$orderBy, $order] = $this->order();

		return $this->listing->orderBy === null ? ['orderby' => $orderBy, 'order' => $this->listing->order->value ?? $order->value, ...parent::listingArguments()] : parent::listingArguments();
	}

	/**
	 * Returns a term page's 1.x query arguments, for `Query::fromArray()`,
	 * before the term itself is matched.
	 *
	 * @return array<string, mixed>
	 */
	public function termArguments(): array
	{
		return [...($this->types === [] ? [] : ['type' => $this->types]), ...$this->termListing->arguments()];
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	protected function options(): array
	{
		return [
			'types'       => $this->types,
			'field'       => $this->field === $this->name ? null : $this->field,
			'aliases'     => $this->aliases,
			'termListing'  => $this->termListing->toArray(),
			'hierarchical' => $this->hierarchical ?: null,
			'people'       => $this->peopleOption(false)
		];
	}
}
