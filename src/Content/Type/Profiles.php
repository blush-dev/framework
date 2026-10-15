<?php

/**
 * Profiles content type.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Type;

use Override;
use Blush\Storage\Record\Order;
use Blush\Field\Field;
use Blush\Field\FieldControls;
use Blush\Field\FieldLayout;
use Blush\Translation\Messages;

/**
 * The type whose entries are the people other entries credit (D-351):
 * each profile's public name (its title), a line under it (its
 * `subtitle`), an `avatar`, and a bio (its body). A site has at most one.
 * Other types credit profiles through credit relations (D-602), each in
 * its own words, so one profile is a post's author and a recipe's cook
 * alike:
 *
 *     new Profiles();
 *
 * Each profile has one canonical page, `{prefix}/{slug}` (its `single`
 * key, with `.paged` and, when the type has a feed, the feed keys): the
 * bio, then every listed entry of any type that credits them. Nothing
 * answers at the prefix itself. The prefix is `profiles` unless the
 * URLs set one (D-357), though they're kept in `_` and the type's name
 * (`_profile`, D-683), with no folder pattern unless the type gives one
 * (D-684). A profile is its file (D-584): one
 * that's credited but missing is left out of the site, and
 * `content:terms` writes it. An admin account may link to one.
 */
final readonly class Profiles extends ContentType
{
	/**
	 * The URL prefix profiles' pages sit under unless the URLs set one.
	 */
	public const string BASE = 'profiles';

	/**
	 * @param  string          $name        Lowercase letters, digits, and underscores.
	 * @param  ?string         $folders     A folder pattern for the folders below its own (D-629), or `null` for none (D-684).
	 * @param  TypeUrls|false  $urls        URL settings (the prefix is the base word), or `false` for no pages of their own.
	 * @param  Listing         $listing     How a profile's page lists the entries crediting them.
	 * @param  TypeFeed|false  $feed        Feed settings for each profile's feed, or `false` for none.
	 * @param  bool            $public      Whether profiles are public at all.
	 * @param  bool            $sitemap     Whether profiles' pages are in the sitemap.
	 * @param  iterable<Field> $fields      Fields beyond the built-in ones.
	 * @param  bool            $closed      Whether undeclared front matter is an error.
	 * @param  ?TypeLabels     $labels      What people call it; defaults to core's labels for `profile` (D-842), else labels made from the name.
	 * @param  string          $description What the type is for, in a sentence.
	 * @param  ?string         $icon        An icon name for the admin; defaults to its kind's.
	 * @param  bool            $llms        Whether profiles are listed in `llms.txt` (D-401).
	 * @param  ?FileName       $filename    How new files are named (D-514).
	 * @param  ?string         $workflow    The workflow its entries move through (D-851), or `null` for core's `standard`.
	 * @param  bool            $expires     Whether its entries may have an `expires` date (D-852).
	 * @param  bool            $visibility  Whether its entries choose a visibility (D-855); off, every entry is public. Off unless it's on (D-866).
	 * @param  Messages        $messages    Writes why it's refused; English without a translator, for a type in code.
	 * @param  FieldLayout     $layout      The library fields it carries, in sections (D-858).
	 * @param  bool            $image       Whether entries have a featured `image` (D-866).
	 * @param  bool            $summary     Whether entries have a `summary` (D-866).
	 * @param  bool            $subtitle    Whether entries have a `subtitle` (D-866).
	 * @param  bool            $position    Whether entries have a `position` among their siblings (D-412, D-866).
	 * @throws InvalidContentType
	 */
	public function __construct(
		string $name = 'profile',
		?string $folders = null,
		TypeUrls|false $urls = new TypeUrls(),
		Listing $listing = new Listing(),
		TypeFeed|false $feed = false,
		bool $public = true,
		bool $sitemap = true,
		iterable $fields = [],
		bool $closed = false,
		?TypeLabels $labels = null,
		string $description = '',
		?string $icon = null,
		bool $llms = false,
		?FileName $filename = null,
		FieldControls $controls = new FieldControls(),
		?string $workflow = null,
		bool $expires = false,
		bool $visibility = false,
		Messages $messages = new Messages(),
		FieldLayout $layout = new FieldLayout(),
		bool $image = false,
		bool $summary = false,
		bool $subtitle = false,
		bool $position = false
	) {
		$labels ??= BuiltInType::labelsFor(TypeKind::Profiles, $name, $messages);

		parent::__construct($name, $folders, $public, $urls, $listing, $feed, $sitemap, DateArchives::None, $fields, $closed, $labels, $description, $icon, null, $llms, $filename, null, null, $controls, $workflow, $expires, $visibility, $messages, $layout, $image, $summary, $subtitle, $position);
	}

	/**
	 * Returns its entries' order: by name.
	 *
	 * @inheritDoc
	 */
	#[Override]
	public function order(): array
	{
		return ['title', Order::Asc];
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function kind(): TypeKind
	{
		return TypeKind::Profiles;
	}

	/**
	 * Returns the URL prefix: the URLs' own, else `profiles`, not the
	 * folder's.
	 */
	#[Override]
	public function prefix(): string
	{
		return $this->urls === false ? '' : $this->urls->prefix ?? self::BASE;
	}

	/**
	 * The site's one profiles type stays as the code defines it.
	 */
	#[Override]
	public function isOverridable(): bool
	{
		return false;
	}

	#[Override]
	public function role(): string
	{
		return 'profiles';
	}

	/**
	 * Profiles aren't pages at their folder paths; a profile's page is
	 * its `single` route, when the type has URLs.
	 */
	#[Override]
	public function servedAsPages(): bool
	{
		return false;
	}
}
