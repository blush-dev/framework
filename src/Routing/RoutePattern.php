<?php

/**
 * Route pattern.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Routing;

/**
 * A parsed path pattern such as `/archives/{year:\d{4}}/{slug}`. It knows
 * the pattern's literal text and parameters, builds the regex the matcher
 * combines, and fills the parameters back in for URL generation.
 *
 * A parameter matches one segment (`[^/]+`) unless constrained, inline or
 * through the constraints map (which wins). Constraints may nest braces
 * (`\d{4}`) but not capturing groups, and must not contain `~`.
 */
final readonly class RoutePattern
{
	/**
	 * What an unconstrained parameter matches.
	 */
	public const string SEGMENT = '[^/]+';

	/**
	 * @param list<string|array{0: string, 1: string}> $parts  Literal text, or `[name, regex]` for a parameter.
	 * @param list<string>                             $params Parameter names, in order.
	 */
	public function __construct(
		public string $path,
		public array $parts,
		public array $params
	) {}

	/**
	 * Parses a pattern. `$constraints` gives regexes by parameter name and
	 * overrides inline ones.
	 *
	 * @param  array<string, string> $constraints
	 * @throws InvalidRoute
	 */
	public static function parse(string $path, array $constraints = []): self
	{
		$parts  = [];
		$params = [];
		$text   = '';
		$length = strlen($path);

		for ($i = 0; $i < $length; $i++) {
			if ($path[$i] === '}') {
				throw new InvalidRoute(sprintf('The route pattern "%s" has an unmatched "}".', $path));
			}

			if ($path[$i] !== '{') {
				$text .= $path[$i];
				continue;
			}

			[$name, $regex, $i] = self::parameter($path, $i);

			if (in_array($name, $params, true)) {
				throw new InvalidRoute(sprintf('The route pattern "%s" uses "{%s}" twice.', $path, $name));
			}

			if ($text !== '') {
				$parts[] = $text;
				$text    = '';
			}

			$regex = $constraints[$name] ?? $regex;

			self::assertRegex($path, $name, $regex);

			$parts[]  = [$name, $regex];
			$params[] = $name;
		}

		if ($text !== '') {
			$parts[] = $text;
		}

		foreach (array_keys($constraints) as $name) {
			if (! in_array($name, $params, true)) {
				throw new InvalidRoute(sprintf('The route "%s" constrains "%s", which is not in its path.', $path, $name));
			}
		}

		return new self($path, $parts, $params);
	}

	/**
	 * Rebuilds a pattern from `toArray()` output.
	 *
	 * @param array{path: string, parts: list<string|array{0: string, 1: string}>, params: list<string>} $data
	 */
	public static function fromArray(array $data): self
	{
		return new self($data['path'], $data['parts'], $data['params']);
	}

	/**
	 * Whether the pattern has no parameters.
	 */
	public function isStatic(): bool
	{
		return $this->params === [];
	}

	/**
	 * Returns the pattern as a regex body (no delimiters or anchors), with
	 * one capturing group per parameter, in order.
	 */
	public function regex(): string
	{
		return implode('', array_map(
			static fn (string|array $part): string => is_string($part) ? preg_quote($part, '~') : "({$part[1]})",
			$this->parts
		));
	}

	/**
	 * Returns the regex for a parameter.
	 */
	public function constraint(string $name): string
	{
		foreach ($this->parts as $part) {
			if (is_array($part) && $part[0] === $name) {
				return $part[1];
			}
		}

		return self::SEGMENT;
	}

	/**
	 * Returns a copy where each unconstrained parameter named in the map
	 * gets that regex instead. Used for constraints inferred from handler
	 * parameter types.
	 *
	 * @param array<string, string> $constraints
	 */
	public function withDefaultConstraints(array $constraints): self
	{
		$parts = array_map(
			static fn (string|array $part): string|array => is_array($part) && $part[1] === self::SEGMENT && isset($constraints[$part[0]])
				? [$part[0], $constraints[$part[0]]]
				: $part,
			$this->parts
		);

		return new self($this->path, $parts, $this->params);
	}

	/**
	 * Builds a path from parameter values. Each value is percent-encoded
	 * segment by segment (so a catch-all value keeps its slashes) and must
	 * satisfy its constraint.
	 *
	 * @param  array<string, string> $values
	 * @throws UrlGenerationException
	 */
	public function build(array $values): string
	{
		$path = '';

		foreach ($this->parts as $part) {
			if (is_string($part)) {
				$path .= $part;
				continue;
			}

			[$name, $regex] = $part;

			if (! isset($values[$name])) {
				throw new UrlGenerationException(sprintf('The route "%s" needs a value for "%s".', $this->path, $name));
			}

			$value = implode('/', array_map(rawurlencode(...), explode('/', $values[$name])));

			if (preg_match("~^(?:{$regex})$~", $value) !== 1) {
				throw new UrlGenerationException(sprintf(
					'The value "%s" for "%s" does not match the route "%s".',
					$values[$name],
					$name,
					$this->path
				));
			}

			$path .= $value;
		}

		return $path;
	}

	/**
	 * Returns the pattern as exportable values.
	 *
	 * @return array{path: string, parts: list<string|array{0: string, 1: string}>, params: list<string>}
	 */
	public function toArray(): array
	{
		return ['path' => $this->path, 'parts' => $this->parts, 'params' => $this->params];
	}

	/**
	 * Reads the parameter opening at `$start`, returning its name, regex,
	 * and the offset of its closing brace.
	 *
	 * @return array{0: string, 1: string, 2: int}
	 * @throws InvalidRoute
	 */
	private static function parameter(string $path, int $start): array
	{
		$depth = 0;

		for ($i = $start + 1; $i < strlen($path); $i++) {
			if ($path[$i] === '{') {
				$depth++;
			} elseif ($path[$i] === '}' && $depth-- === 0) {
				$inner = substr($path, $start + 1, $i - $start - 1);
				[$name, $regex] = [...explode(':', $inner, 2), self::SEGMENT];

				if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) !== 1) {
					throw new InvalidRoute(sprintf('The route pattern "%s" has an invalid parameter name "%s".', $path, $name));
				}

				return [$name, $regex === '' ? self::SEGMENT : $regex, $i];
			}
		}

		throw new InvalidRoute(sprintf('The route pattern "%s" has an unclosed "{".', $path));
	}

	/**
	 * Checks that a constraint compiles and has no capturing groups, which
	 * would throw off the matcher's parameter positions.
	 *
	 * @throws InvalidRoute
	 */
	private static function assertRegex(string $path, string $name, string $regex): void
	{
		$matches = [];
		$result  = @preg_match("~(?:{$regex})|~", '', $matches, PREG_UNMATCHED_AS_NULL);

		if ($result === false) {
			throw new InvalidRoute(sprintf('The constraint for "%s" in the route "%s" is not a valid regex.', $name, $path));
		}

		if (count($matches) > 1) {
			throw new InvalidRoute(sprintf(
				'The constraint for "%s" in the route "%s" has a capturing group; use "(?:...)" instead.',
				$name,
				$path
			));
		}
	}
}
