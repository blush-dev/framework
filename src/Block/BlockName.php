<?php

/**
 * Block name.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Block;

use Override;
use Stringable;

/**
 * A block's name, `{namespace}/{name}` (D-171): `core` for the core
 * blocks or a plugin's namespace (D-378); themes can't register
 * blocks (D-532), and the site has none of its own (D-617). Only core
 * blocks may be written without their namespace
 * (`callout` is `core/callout`); any other short name isn't a block.
 *
 * A block's template is `blocks/{namespace}-{name}.php`. A core
 * block's may also be `blocks/{name}.php`.
 */
final readonly class BlockName implements Stringable
{
	/**
	 * The core blocks' namespace.
	 */
	public const string CORE = 'core';

	/**
	 * The syntax of a written name, short or full, as a regex fragment
	 * without delimiters or anchors (the Markdown blocks use it).
	 */
	public const string SYNTAX = '[A-Za-z][A-Za-z0-9_-]*(?:/[A-Za-z][A-Za-z0-9_-]*)?';

	public function __construct(
		public string $namespace,
		public string $name
	) {}

	/**
	 * Returns the name a string refers to, or `null` when it isn't a
	 * valid full name or a core block's short name.
	 */
	public static function parse(string $value): ?self
	{
		if (preg_match('#^' . self::SYNTAX . '$#', $value) !== 1) {
			return null;
		}

		if (! str_contains($value, '/')) {
			return BlockType::tryFrom($value) === null ? null : new self(self::CORE, $value);
		}

		[$namespace, $name] = explode('/', $value, 2);

		return new self($namespace, $name);
	}

	/**
	 * Returns the name a template file is for (the file name without
	 * `.php`), given the namespaces it could belong to, or `null` when
	 * the file isn't named for one: a core block's short name, or
	 * `{namespace}-{name}` with the longest namespace that fits.
	 *
	 * @param list<string> $namespaces
	 */
	public static function fromFileName(string $file, array $namespaces): ?self
	{
		if (BlockType::tryFrom($file) !== null) {
			return new self(self::CORE, $file);
		}

		usort($namespaces, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));

		foreach ($namespaces as $namespace) {
			if (str_starts_with($file, "{$namespace}-")) {
				$name = self::parse($namespace . '/' . substr($file, strlen($namespace) + 1));

				if ($name !== null) {
					return $name;
				}
			}
		}

		return null;
	}

	/**
	 * Returns whether it's a core block.
	 */
	public function isCore(): bool
	{
		return $this->namespace === self::CORE;
	}

	/**
	 * Returns the view names its template may have, preferred first.
	 *
	 * @return list<string>
	 */
	public function views(): array
	{
		$views = ["blocks/{$this->namespace}-{$this->name}"];

		return $this->isCore() ? ["blocks/{$this->name}", ...$views] : $views;
	}

	/**
	 * Returns a label made from the name (`post-archives` → "Post
	 * archives"), for when no translation gives one.
	 */
	public function label(): string
	{
		return ucfirst(str_replace(['-', '_'], ' ', $this->name));
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function __toString(): string
	{
		return "{$this->namespace}/{$this->name}";
	}
}
