<?php

/**
 * Site addresses.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin\Redirects;

use Blush\Content\Entries;
use Blush\Content\Entry\Entry;
use Blush\Content\Visibility;
use Blush\Content\Http\PageController;
use Blush\Content\Http\ProfileController;
use Blush\Content\Http\SingleController;
use Blush\Content\Http\TermController;
use Blush\Content\Routing\ContentUrls;
use Blush\Llms\MarkdownController;
use Blush\Routing\RouteTable;

/**
 * What answers an address on the site, as the router would find it
 * before trying redirects (D-076), for the Redirects screen (D-686): a
 * live entry at that address, or a route that isn't an entry's (a feed,
 * the sitemap, an archive by date, the admin). A route an entry answers
 * (a single entry, a term, a page, a profile) answers only when there's
 * a live entry at the address; otherwise its controller is a 404 and a
 * redirect gets its turn. Paths are compared as the router does: as
 * written, without a trailing slash.
 *
 * Entries' addresses are read once, the first time they're needed.
 */
final class SiteAddresses
{
	/**
	 * The controllers whose pages are entries.
	 *
	 * @var list<class-string>
	 */
	private const array ENTRY_CONTROLLERS = [
		SingleController::class,
		TermController::class,
		PageController::class,
		ProfileController::class
	];

	/**
	 * Live entries, by address.
	 *
	 * @var ?array<string, Entry>
	 */
	private ?array $entries = null;

	public function __construct(
		private readonly RouteTable $routes,
		private readonly Entries $content,
		private readonly ContentUrls $urls
	) {}

	/**
	 * Returns the form of a path the router looks up: without a trailing
	 * slash.
	 */
	public static function lookup(string $path): string
	{
		$trimmed = rtrim($path, '/');

		return $trimmed === '' ? '/' : $trimmed;
	}

	/**
	 * What answers a path: the live entry there, the route's name (or its
	 * handler's) for a route that isn't an entry's, or `null` for nothing,
	 * so a redirect would be tried.
	 */
	public function answer(string $path): Entry|string|null
	{
		$path  = self::lookup($path);
		$entry = $this->entries()[$path] ?? null;

		if ($entry !== null) {
			return $entry;
		}

		$match = $this->routes->find('GET', $path);

		if ($match === null || in_array($match->route->controller, self::ENTRY_CONTROLLERS, true)) {
			return null;
		}

		// A page's Markdown copy is there when the page is.
		if ($match->route->controller === MarkdownController::class) {
			return isset($this->entries()[self::lookup(substr($path, 0, -3))]) ? $match->route->name ?? $match->route->handlerName() : null;
		}

		return $match->route->name ?? $match->route->handlerName();
	}

	/**
	 * The live entry at a path, if any.
	 */
	public function entry(string $path): ?Entry
	{
		return $this->entries()[self::lookup($path)] ?? null;
	}

	/**
	 * A live entry's address, or `null` for one that isn't live.
	 */
	public function urlOf(Entry $entry): ?string
	{
		return $entry->isPublished() && $entry->isRoutable() ? $this->urls->entry($entry) : null;
	}

	/**
	 * Every live entry with an address, by address.
	 *
	 * @return array<string, Entry>
	 */
	private function entries(): array
	{
		if ($this->entries !== null) {
			return $this->entries;
		}

		$this->entries = [];

		$found = $this->content->query()
			->anyLanguage()
			->withOriginals(false)
			->withLanding()
			->visibility(Visibility::Public, Visibility::Unlisted)
			->limit(null)
			->get();

		foreach ($found as $entry) {
			$url = $this->urlOf($entry);

			if ($url !== null) {
				$this->entries[self::lookup($url)] ??= $entry;
			}
		}

		return $this->entries;
	}
}
