<?php

/**
 * Collection content type.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Type;

use Override;
use Blush\Field\Field;
use Blush\Field\Fields\ReferenceField;

/**
 * A type whose entries are listed: posts, literature, projects. Its folder
 * holds the entries, its listing page lists them, and it may have a feed
 * and date archives:
 *
 *     new Collection(
 *         'post',
 *         folder: '_posts',
 *         urls: new TypeUrls(prefix: 'archives', single: '{year}/{month}/{day}/{name}'),
 *         listing: new Listing(order: Order::Desc),
 *         feed: new TypeFeed(categories: 'category'),
 *         dateArchives: DateArchives::Day
 *     );
 *
 * Terms are collections too (D-593): one that files other entries is a
 * classify relation's target, usually ordered by `position` then title
 * and without dates, and it may nest:
 *
 *     new Collection('category', folder: 'topics', hierarchical: true, order: TypeOrder::Position);
 *
 * A collection that's `hierarchical` has a `parent` relation (D-591): an
 * entry names its parent entry by slug in `parent`, so it keeps its file
 * and URL when it moves, and slugs stay unique across the collection.
 */
final readonly class Collection extends ContentType
{
	/**
	 * @param  string          $name         Lowercase letters, digits, and underscores.
	 * @param  ?string         $folder       The folder under `user/content`; defaults to `_` and the name.
	 * @param  TypeUrls|false  $urls         URL settings, or `false` for no routes.
	 * @param  Listing         $listing      How the listing page lists entries.
	 * @param  TypeFeed|false  $feed         Feed settings, or `false` for no feed.
	 * @param  DateArchives    $dateArchives How finely date archives go.
	 * @param  bool            $public       Whether the type is public at all.
	 * @param  bool            $sitemap      Whether entries are in the sitemap.
	 * @param  iterable<Field> $fields       Fields beyond the built-in ones.
	 * @param  bool            $closed       Whether undeclared front matter is an error.
	 * @param  ?TypeLabels     $labels       What people call it; defaults to labels made from the name.
	 * @param  string          $description  What the type is for, in a sentence.
	 * @param  ?string         $icon         An icon name for the admin; defaults to its kind's.
	 * @param  ?string        $byline       The credit relation that's its byline (D-602), or `null` for its only one.
	 * @param  bool            $llms         Whether entries are listed in `llms.txt` (D-398).
	 * @param  ?FileName       $filename     How new files are named; defaults to the date and slug with date archives, else the slug.
	 * @param  bool            $hierarchical Whether an entry may name a `parent` entry.
	 * @param  TypeOrder       $order        How entries are ordered when nothing says otherwise.
	 * @throws InvalidContentType
	 */
	public function __construct(
		string $name,
		?string $folder = null,
		TypeUrls|false $urls = new TypeUrls(),
		Listing $listing = new Listing(),
		TypeFeed|false $feed = false,
		DateArchives $dateArchives = DateArchives::None,
		bool $public = true,
		bool $sitemap = true,
		iterable $fields = [],
		bool $closed = false,
		?TypeLabels $labels = null,
		string $description = '',
		?string $icon = null,
		?string $byline = null,
		bool $llms = true,
		?FileName $filename = null,
		public bool $hierarchical = false,
		public TypeOrder $order = TypeOrder::Published
	) {
		parent::__construct($name, $folder, $public, $urls, $listing, $feed, $sitemap, $dateArchives, $fields, $closed, $labels, $description, $icon, $byline, $llms, $filename);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function kind(): TypeKind
	{
		return TypeKind::Collection;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function order(): array
	{
		return $this->order->query();
	}

	/**
	 * Lists entries by position unless the listing says otherwise, in its
	 * direction when it gives one (D-516), when that's the type's order.
	 *
	 * @inheritDoc
	 */
	#[Override]
	public function listingArguments(): array
	{
		if ($this->order !== TypeOrder::Position || $this->listing->orderBy !== null) {
			return parent::listingArguments();
		}

		[$orderBy, $order] = $this->order();

		return ['orderby' => $orderBy, 'order' => $this->listing->order->value ?? $order->value, ...parent::listingArguments()];
	}

	/**
	 * Returns the entry a hierarchical collection's entry names as its
	 * `parent`. An entry can't be its own parent.
	 */
	#[Override]
	public function parentKey(string $key, array $values): ?string
	{
		$parent = $this->hierarchical ? ($values['parent'] ?? null) : null;

		return is_string($parent) && $parent !== '' && $parent !== $key ? $parent : null;
	}

	/**
	 * Returns the field an entry names its parent through, or `null` when
	 * the collection doesn't nest.
	 */
	public function parentField(): ?ReferenceField
	{
		return $this->hierarchical
			? new ReferenceField('parent', $this->name, multiple: false)->described(sprintf('The parent %s, by slug.', $this->labels->item))
			: null;
	}

	/**
	 * Returns whether entries have a `position` among their siblings: when
	 * they're ordered by it, or nest.
	 */
	public function isPositioned(): bool
	{
		return $this->hierarchical || $this->order === TypeOrder::Position;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	protected function options(): array
	{
		return [
			'dateArchives' => $this->dateArchives === DateArchives::None ? null : $this->dateArchives->value,
			'byline'       => $this->byline,
			'hierarchical' => $this->hierarchical ?: null,
			'order'        => $this->order === TypeOrder::Published ? null : $this->order->value
		];
	}
}
