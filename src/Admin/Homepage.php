<?php

/**
 * Homepage.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin;

use Blush\Content\Entry\Entry;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Type\Tree;
use Blush\Settings\Setting;

/**
 * Which entry is the site's homepage (D-420): the root `index.md`
 * (the **root page**), unless the homepage setting names a collection,
 * whose index page is then the homepage and the root page isn't shown.
 * The admin pins the root page on Pages and marks the homepage, wherever
 * it's listed, with a house and a **Homepage** chip.
 */
final readonly class Homepage
{
	public function __construct(private ContentTypes $types)
	{
	}

	/**
	 * Whether an entry is the root tree's landing page, `index.md` at the
	 * top of the content folder.
	 */
	public static function isRootPage(Entry $entry): bool
	{
		return $entry->landing && $entry->type instanceof Tree && $entry->type->atRoot();
	}

	/**
	 * Whether an entry is what the site shows at `/`.
	 */
	public function is(Entry $entry): bool
	{
		$home = $this->types->homeType();

		return $home === null ? self::isRootPage($entry) : $entry->landing && $entry->type->name === $home->name;
	}

	/**
	 * Describes an entry's part in the homepage for the admin: whether
	 * it's the `homepage`, whether it's the `rootPage`, and, for a root
	 * page that isn't shown, what the homepage shows instead
	 * (`homeInstead`).
	 *
	 * @return array{homepage: bool, rootPage: bool, homeInstead: ?string}
	 */
	public function describe(Entry $entry): array
	{
		$root = self::isRootPage($entry);
		$home = $this->is($entry);

		return ['homepage' => $home, 'rootPage' => $root, 'homeInstead' => $root && ! $home ? $this->instead() : null];
	}

	/**
	 * What the homepage shows instead of the root page, such as "The
	 * latest posts", or `null` when it shows the root page.
	 */
	public function instead(): ?string
	{
		$home = $this->types->homeType();

		return $home === null ? null : (Setting::homeChoices($this->types)[$home->name] ?? $home->labels->plural);
	}
}
