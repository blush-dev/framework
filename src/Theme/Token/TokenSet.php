<?php

/**
 * Design token set.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Theme\Token;

use NoDiscard;

/**
 * A set of design tokens in the W3C Design Tokens (DTCG) format (D-023),
 * compiled to CSS custom properties.
 *
 * - **Groups** nest; a group's `$type` applies to the tokens below it.
 *   Keys starting with `$` are metadata, never names.
 * - **Tokens** are objects with a `$value`. As a shorthand (handy in
 *   front matter and site data), a plain scalar is a token too:
 *   `{"color": {"accent": "#a3285b"}}`.
 * - **Aliases** (`"{color.accent}"`, also inside a larger value) compile
 *   to `var(--color-accent)`, so a mode's change flows to every alias.
 * - **Overrides** replace a token in every mode unless they give their
 *   own modes (`merge()`, `over()`).
 * - **Modes** are a Blush extension:
 *   `"$extensions": {"blush": {"modes": {"dark": "#f08bb4"}}}`. `dark`
 *   compiles to a `prefers-color-scheme: dark` block (unless the page sets
 *   `data-scheme="light"`) and to `[data-scheme="dark"]`; any other mode
 *   to `[data-scheme="{mode}"]`.
 *
 * Values are formatted by shape: strings and numbers as written,
 * dimensions and durations (`{"value": 1, "unit": "rem"}`), font family
 * lists, cubic Béziers, colors given as `{"hex": …}` or color-space
 * components, shadows, and borders. A value that could break out of its
 * declaration (`;`, `{`, `}`, `<`, `>`, `\`, or a line break) is dropped
 * and reported, since tokens also come from site data and front matter.
 */
final readonly class TokenSet
{
	/**
	 * @param array<string, Token> $tokens   Tokens by path, in the order defined.
	 * @param list<string>         $problems What couldn't be read or compiled.
	 */
	public function __construct(
		public array $tokens = [],
		public array $problems = []
	) {}

	/**
	 * Reads a DTCG document.
	 *
	 * @param array<array-key, mixed> $data
	 */
	public static function fromArray(array $data, string $source = 'tokens'): self
	{
		$tokens   = [];
		$problems = [];

		self::walk($data, [], null, $source, $tokens, $problems);

		return new self($tokens, $problems);
	}

	/**
	 * Returns whether the set has no tokens.
	 */
	public function isEmpty(): bool
	{
		return $this->tokens === [];
	}

	/**
	 * Returns a token by path.
	 */
	public function get(string $path): ?Token
	{
		return $this->tokens[$path] ?? null;
	}

	/**
	 * Returns the set with another's tokens over it. A token in both is
	 * replaced whole, in every mode: an override without modes applies
	 * to all of them. Only its type is inherited when it has none.
	 */
	#[NoDiscard]
	public function merge(self $other): self
	{
		$tokens = $this->tokens;

		foreach ($other->tokens as $path => $token) {
			$tokens[$path] = new Token($path, $token->value, $token->type ?? ($tokens[$path] ?? null)?->type, $token->modes);
		}

		return new self($tokens, [...$this->problems, ...$other->problems]);
	}

	/**
	 * Returns this set as overrides printed after a base set (an entry's
	 * tokens over the theme's): each token also sets every mode the base
	 * gives it, so the override wins in those modes too.
	 */
	#[NoDiscard]
	public function over(self $base): self
	{
		$tokens = [];

		foreach ($this->tokens as $path => $token) {
			$modes = $token->modes;

			foreach (array_keys($base->get($path)->modes ?? []) as $mode) {
				$modes[$mode] ??= $token->value;
			}

			$tokens[$path] = new Token($path, $token->value, $token->type ?? $base->get($path)?->type, $modes);
		}

		return new self($tokens, $this->problems);
	}

	/**
	 * Returns a token's value in a mode as concrete CSS, with aliases
	 * followed, or `null` when it's missing, loops, or can't be formatted.
	 *
	 * @param array<string, true> $seen Paths already followed.
	 */
	public function value(string $path, ?string $mode = null, array $seen = []): ?string
	{
		$token = $this->tokens[$path] ?? null;

		if ($token === null || isset($seen[$path])) {
			return null;
		}

		$seen[$path] = true;
		$failed      = false;

		$css = self::format($token->valueIn($mode), $token->type, function (string $alias) use ($mode, $seen, &$failed): string {
			$value  = $this->value($alias, $mode, $seen);
			$failed = $failed || $value === null;

			return $value ?? '';
		});

		return $failed ? null : $css;
	}

	/**
	 * Returns the mode names any token defines.
	 *
	 * @return list<string>
	 */
	public function modes(): array
	{
		$modes = [];

		foreach ($this->tokens as $token) {
			foreach (array_keys($token->modes) as $mode) {
				$modes[(string) $mode] = true;
			}
		}

		return array_keys($modes);
	}

	/**
	 * Compiles the set to CSS custom properties on a selector, with a
	 * block per mode. Tokens that can't be formatted are left out (see
	 * `compileProblems()`).
	 */
	public function css(string $selector = ':root'): string
	{
		$base  = $this->declarations(null);
		$css   = $base === '' ? '' : "{$selector} {\n{$base}}\n";
		$scope = $selector === ':root' ? '' : ' ' . $selector;

		foreach ($this->modes() as $mode) {
			$declarations = $this->declarations($mode);

			if ($declarations === '') {
				continue;
			}

			if ($mode === 'dark') {
				$css .= "@media (prefers-color-scheme: dark) {\n:root:not([data-scheme=\"light\"]){$scope} {\n{$declarations}}\n}\n";
			}

			$css .= ":root[data-scheme=\"{$mode}\"]{$scope} {\n{$declarations}}\n";
		}

		return $css;
	}

	/**
	 * Returns the tokens that can't be compiled to CSS or whose aliases
	 * don't resolve, with the reason.
	 *
	 * @return list<string>
	 */
	public function compileProblems(): array
	{
		$problems = [];

		foreach ($this->tokens as $token) {
			foreach ([null, ...array_keys($token->modes)] as $mode) {
				$mode  = $mode === null ? null : (string) $mode;
				$label = $mode === null ? '' : " (mode \"{$mode}\")";

				if (self::format($token->valueIn($mode), $token->type, self::variable(...)) === null) {
					$problems[] = sprintf('Token "%s"%s has a value that can\'t be used in CSS.', $token->path, $label);
				} elseif ($this->value($token->path, $mode) === null) {
					$problems[] = sprintf('Token "%s"%s refers to a missing token, or to itself.', $token->path, $label);
				}
			}
		}

		return $problems;
	}

	/**
	 * Returns the declarations for the tokens a mode sets (all tokens for
	 * the base).
	 */
	private function declarations(?string $mode): string
	{
		$css = '';

		foreach ($this->tokens as $token) {
			if ($mode !== null && ! array_key_exists($mode, $token->modes)) {
				continue;
			}

			$value = self::format($token->valueIn($mode), $token->type, self::variable(...));

			if ($value !== null) {
				$css .= "\t{$token->property()}: {$value};\n";
			}
		}

		return $css;
	}

	/**
	 * Returns the CSS for an alias.
	 */
	private static function variable(string $path): string
	{
		return 'var(--' . str_replace('.', '-', $path) . ')';
	}

	/**
	 * Formats a raw value as CSS, turning aliases into what `$alias`
	 * returns. Returns `null` for a value that can't be used.
	 *
	 * @param callable(string): string $alias
	 */
	private static function format(mixed $value, ?string $type, callable $alias): ?string
	{
		$css = match (true) {
			is_string($value)                             => $value,
			is_int($value), is_float($value)              => (string) $value,
			is_array($value) && array_is_list($value)     => self::formatList($value, $type, $alias),
			is_array($value)                              => self::formatObject($value, $type, $alias),
			default                                       => null
		};

		if ($css === null) {
			return null;
		}

		$css = preg_replace_callback('/\{([A-Za-z0-9_.-]+)\}/', static fn (array $match): string => $alias($match[1]), $css) ?? '';

		return $css === '' || preg_match('/[;{}<>\\\\\r\n]/', $css) === 1 ? null : $css;
	}

	/**
	 * Formats a list: a cubic Bézier, several shadows, or a font family
	 * list.
	 *
	 * @param list<mixed>              $value
	 * @param callable(string): string $alias
	 */
	private static function formatList(array $value, ?string $type, callable $alias): ?string
	{
		$numbers = array_filter($value, is_numeric(...));

		if ($type === 'cubicBezier' && count($value) === 4 && count($numbers) === 4) {
			return 'cubic-bezier(' . implode(', ', $numbers) . ')';
		}

		if (array_all($value, static fn (mixed $item): bool => is_array($item))) {
			$parts = array_map(static fn (mixed $item): ?string => self::format($item, $type, $alias), $value);

			return in_array(null, $parts, true) ? null : implode(', ', $parts);
		}

		$families = [];

		foreach ($value as $family) {
			if (! is_string($family)) {
				return null;
			}

			$families[] = preg_match('/^[A-Za-z-]+$/', $family) === 1 || str_starts_with($family, '{')
				? $family
				: '"' . str_replace('"', '', $family) . '"';
		}

		return implode(', ', $families);
	}

	/**
	 * Formats an object: a dimension or duration, a color, a shadow, or a
	 * border.
	 *
	 * @param array<array-key, mixed>  $value
	 * @param callable(string): string $alias
	 */
	private static function formatObject(array $value, ?string $type, callable $alias): ?string
	{
		$part = static fn (string $key, ?string $partType = null): ?string => isset($value[$key]) ? self::format($value[$key], $partType, $alias) : null;

		if (isset($value['value'], $value['unit']) && is_numeric($value['value']) && is_string($value['unit']) && preg_match('/^[a-z%]+$/i', $value['unit']) === 1) {
			return $value['value'] . $value['unit'];
		}

		if (is_string($value['hex'] ?? null)) {
			return $value['hex'];
		}

		$components = is_array($value['components'] ?? null) ? $value['components'] : [];
		$numbers    = array_filter($components, is_numeric(...));

		if (is_string($value['colorSpace'] ?? null) && $components !== [] && count($numbers) === count($components)) {
			$alpha = isset($value['alpha']) && is_numeric($value['alpha']) ? ' / ' . $value['alpha'] : '';

			return sprintf('color(%s %s%s)', $value['colorSpace'], implode(' ', $numbers), $alpha);
		}

		if ($type === 'shadow' || isset($value['offsetX'])) {
			$parts = [$part('offsetX'), $part('offsetY'), $part('blur'), $part('spread'), $part('color')];

			return in_array(null, $parts, true) ? null : (($value['inset'] ?? false) === true ? 'inset ' : '') . implode(' ', $parts);
		}

		if ($type === 'border' || isset($value['style'], $value['width'])) {
			$parts = [$part('width'), $part('style'), $part('color')];

			return in_array(null, $parts, true) ? null : implode(' ', $parts);
		}

		return null;
	}

	/**
	 * Collects the tokens in a group.
	 *
	 * @param array<array-key, mixed> $group
	 * @param list<string>            $path
	 * @param array<string, Token>    $tokens
	 * @param list<string>            $problems
	 */
	private static function walk(array $group, array $path, ?string $type, string $source, array &$tokens, array &$problems): void
	{
		$type = is_string($group['$type'] ?? null) ? $group['$type'] : $type;

		foreach ($group as $key => $child) {
			$key = (string) $key;

			if (str_starts_with($key, '$')) {
				continue;
			}

			if (preg_match('/^[A-Za-z0-9_-]+$/', $key) !== 1) {
				$problems[] = sprintf('%s: "%s" isn\'t a usable token name.', $source, implode('.', [...$path, $key]));

				continue;
			}

			$name = implode('.', [...$path, $key]);

			if (is_array($child) && array_key_exists('$value', $child)) {
				$extensions = is_array($child['$extensions'] ?? null) ? $child['$extensions'] : [];
				$blush      = is_array($extensions['blush'] ?? null) ? $extensions['blush'] : [];
				$modes      = $blush['modes'] ?? $extensions['blush.modes'] ?? [];

				$tokens[$name] = new Token(
					$name,
					$child['$value'],
					is_string($child['$type'] ?? null) ? $child['$type'] : $type,
					is_array($modes) ? self::modesOf($modes) : []
				);
			} elseif (is_array($child)) {
				self::walk($child, [...$path, $key], $type, $source, $tokens, $problems);
			} elseif ($child !== null) {
				$tokens[$name] = new Token($name, $child, $type);
			}
		}
	}

	/**
	 * Keeps modes with usable names.
	 *
	 * @param  array<array-key, mixed> $modes
	 * @return array<string, mixed>
	 */
	private static function modesOf(array $modes): array
	{
		return array_filter(
			$modes,
			static fn (int|string $mode): bool => is_string($mode) && preg_match('/^[a-z0-9-]+$/', $mode) === 1,
			ARRAY_FILTER_USE_KEY
		);
	}
}
