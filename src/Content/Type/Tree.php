<?php

/**
 * Tree content type.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Type;

use Override;
use Blush\Content\Entry\Position;
use Blush\Content\Query\Order;
use Blush\Field\Field;

/**
 * A type whose entries nest by folder (D-386). The built-in `page` type is
 * one: every file no other type claims, from the content root down.
 * Others live in a folder of their own, served under it without its `_`
 * (`_docs/install.md` at `/docs/install`), with the folder's `index` as
 * their landing page. Trees have no listing, feed, or routes of their own;
 * the page catch-all serves them. A site replaces the built-in to give
 * pages fields:
 *
 *     new Tree(fields: [new StringField('subtitle')]);
 *
 * and adds a tree of its own beside it:
 *
 *     new Tree('doc', labels: new TypeLabels('Doc'));
 */
final readonly class Tree extends ContentType
{
	/**
	 * @param  string          $name        Lowercase letters, digits, and underscores.
	 * @param  ?string         $folder      The folder under `user/content`: the content root for `page`, else `_` and the name.
	 * @param  bool            $public      Whether its entries are public at all.
	 * @param  bool            $sitemap     Whether its entries are in the sitemap.
	 * @param  iterable<Field> $fields      Fields beyond the built-in ones.
	 * @param  bool            $closed      Whether undeclared front matter is an error.
	 * @param  ?TypeLabels     $labels      What people call it; defaults to labels made from the name ("Pages" and "Page").
	 * @param  string          $description What pages are for, in a sentence.
	 * @param  ?string         $icon        An icon name for the admin; defaults to its kind's.
	 * @param  ?string        $byline      The credit relation that's its byline (D-602), or `null` for its only one.
	 * @param  bool            $llms        Whether its entries are listed in `llms.txt` (D-398).
	 * @param  ?FileName       $filename    How new files are named (D-514).
	 * @throws InvalidContentType
	 */
	public function __construct(
		string $name = 'page',
		?string $folder = null,
		bool $public = true,
		bool $sitemap = true,
		iterable $fields = [],
		bool $closed = false,
		?TypeLabels $labels = null,
		string $description = '',
		?string $icon = null,
		?string $byline = null,
		bool $llms = true,
		?FileName $filename = null
	) {
		parent::__construct($name, $folder ?? ($name === BuiltInType::Page->value ? '' : null), $public, false, new Listing(), false, $sitemap, DateArchives::None, $fields, $closed, $labels, $description, $icon, $byline, $llms, $filename);
	}

	/**
	 * Returns whether the tree is the site's pages: the type at the
	 * content root, whose root is the site. A tree in a folder has that
	 * folder's `index` as its index page (D-386).
	 */
	public function atRoot(): bool
	{
		return $this->folder === '';
	}

	/**
	 * A tree in a folder can be changed from data; the site's pages
	 * can't.
	 */
	#[Override]
	public function isOverridable(): bool
	{
		return ! $this->atRoot();
	}

	#[Override]
	public function role(): string
	{
		return $this->atRoot() ? 'the site\'s pages' : parent::role();
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
		return TypeKind::Tree;
	}

	/**
	 * A tree's folders are its pages' (D-088): `about/biography.md` is
	 * `about/biography`.
	 */
	#[Override]
	public function keysByFolder(): bool
	{
		return true;
	}

	/**
	 * Returns the entry the folder an entry is in belongs to: `about` for
	 * `about/biography`. Top-level entries have none.
	 */
	#[Override]
	public function parentKey(string $key, array $values): ?string
	{
		return str_contains($key, '/') ? dirname($key) : null;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	protected function options(): array
	{
		return ['byline' => $this->byline];
	}
}
