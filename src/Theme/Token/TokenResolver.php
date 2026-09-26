<?php

/**
 * Token resolver.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Theme\Token;

use Blush\Content\Entry\Entry;
use Blush\Data\DataLoader;
use Blush\Data\InvalidData;
use Blush\Theme\SiteThemeData;
use Blush\Theme\ThemeChain;
use Blush\Theme\ThemeException;

/**
 * Builds the design tokens a page uses (D-023), merged in order: the
 * default theme, each ancestor, the active theme (each theme's
 * `tokens.json`, or `.yaml`), then the site's overrides in
 * `user/data/theme.json`. An entry's `tokens` front matter is a separate
 * set, printed after the theme's so it wins on that page (D-027).
 */
final class TokenResolver
{
	/**
	 * Token sets built so far, by active theme slug.
	 *
	 * @var array<string, TokenSet>
	 */
	private array $sets = [];

	public function __construct(
		private readonly DataLoader $loader,
		private readonly SiteThemeData $data
	) {}

	/**
	 * Returns a chain's tokens, with the site's overrides.
	 *
	 * @throws ThemeException When a tokens file can't be read.
	 * @throws InvalidData When the site's theme data can't be read.
	 */
	public function for(ThemeChain $chain): TokenSet
	{
		return $this->sets[$chain->active()->slug] ??= $this->themeTokens($chain)
			->merge(TokenSet::fromArray($this->data->tokens(), 'user/data/theme'));
	}

	/**
	 * Returns only the chain's own tokens, without the site's. A theme
	 * with `inheritTokens: false` cuts off the tokens of the themes it
	 * builds on, the default theme's included (D-148).
	 *
	 * @throws ThemeException
	 */
	public function themeTokens(ThemeChain $chain): TokenSet
	{
		$set    = new TokenSet();
		$themes = [];

		foreach ($chain->themes as $theme) {
			$themes[] = $theme;

			if (! $theme->inheritTokens) {
				break;
			}
		}

		foreach (array_reverse($themes) as $theme) {
			try {
				$data = $this->loader->load($theme->path, 'tokens');
			} catch (InvalidData $error) {
				throw new ThemeException(sprintf('The "%s" theme\'s tokens are invalid: %s', $theme->slug, $error->getMessage()), 0, $error);
			}

			if ($data !== null) {
				$set = $set->merge(TokenSet::fromArray($data, "{$theme->slug}/tokens"));
			}
		}

		return $set;
	}

	/**
	 * Returns an entry's own tokens, or `null` when it has none.
	 */
	public function forEntry(Entry $entry): ?TokenSet
	{
		$tokens = $entry->field('tokens');

		if (! is_array($tokens) || $tokens === []) {
			return null;
		}

		$set = TokenSet::fromArray($tokens, $entry->id);

		return $set->isEmpty() ? null : $set;
	}
}
