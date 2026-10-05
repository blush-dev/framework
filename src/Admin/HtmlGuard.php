<?php

/**
 * HTML guard.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin;

use Blush\Auth\Account;
use Blush\Auth\Capability;
use Blush\Auth\Permissions;
use Blush\Markdown\Html\HtmlAccess;
use Blush\Markdown\Html\HtmlRules;
use Blush\Markdown\Html\MarkupFinder;
use Blush\Markdown\MarkdownException;

/**
 * Checks the raw HTML a save adds to a body (D-495). Files edited on disk
 * are trusted; this is the admin's part. What someone may add follows
 * their capabilities (`access()`), and a save is refused only for what
 * the body didn't already have, counted thing by thing (`refusal()`), so
 * anyone may edit a page someone else put HTML in, and take it out.
 */
final readonly class HtmlGuard
{
	public function __construct(
		private Permissions $permissions,
		private MarkupFinder $finder
	) {}

	/**
	 * Returns how much HTML an account may add.
	 */
	public function access(Account $account): HtmlAccess
	{
		return match (true) {
			$this->permissions->can($account, Capability::HtmlUnfiltered) => HtmlAccess::Unfiltered,
			$this->permissions->can($account, Capability::HtmlAllowed)    => HtmlAccess::Allowed,
			default                                                       => HtmlAccess::None
		};
	}

	/**
	 * Returns why a body can't be saved, or `null` when it can.
	 */
	public function refusal(Account $account, string $before, string $after): ?string
	{
		if ($before === $after) {
			return null;
		}

		$access = $this->access($account);

		try {
			$added = self::added(
				HtmlRules::problems($this->finder->find($before), $access),
				HtmlRules::problems($this->finder->find($after), $access)
			);
		} catch (MarkdownException $e) {
			return sprintf('The body couldn\'t be read for HTML: %s', $e->getMessage());
		}

		if ($added === []) {
			return null;
		}

		$list = self::sentence(array_map(static fn (string $problem): string => "`{$problem}`", $added));

		return $access === HtmlAccess::None
			? sprintf('Your role can\'t add HTML, so %s can\'t be saved. Take it out, or ask someone who can.', $list)
			: sprintf('%s can\'t be added%s. Take it out to save.', ucfirst($list), $access === HtmlAccess::Allowed ? ' with your role' : '');
	}

	/**
	 * Returns what's in `$after` more often than in `$before`, each once.
	 *
	 * @param  list<string> $before
	 * @param  list<string> $after
	 * @return list<string>
	 */
	private static function added(array $before, array $after): array
	{
		$had   = array_count_values($before);
		$added = [];

		foreach (array_count_values($after) as $problem => $count) {
			if ($count > ($had[$problem] ?? 0)) {
				$added[] = (string) $problem;
			}
		}

		return $added;
	}

	/**
	 * Joins a list as a sentence: `a`, `a and b`, `a, b, and c`.
	 *
	 * @param list<string> $items
	 */
	private static function sentence(array $items): string
	{
		return count($items) < 3
			? implode(' and ', $items)
			: implode(', ', array_slice($items, 0, -1)) . ', and ' . end($items);
	}
}
