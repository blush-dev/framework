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
	 * @param  bool            $authors      Whether entries credit authors (D-329).
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
		bool $authors = true
	) {
		parent::__construct($name, $folder, $public, $urls, $listing, $feed, $sitemap, $dateArchives, $fields, $closed, $labels, $description, $icon, $authors);
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
	protected function options(): array
	{
		return [
			'dateArchives' => $this->dateArchives === DateArchives::None ? null : $this->dateArchives->value,
			'authors'      => $this->authors ? null : false
		];
	}
}
