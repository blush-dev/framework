<?php

/**
 * Definition clash.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Extension;

/**
 * Two extensions defining a content type or a relation by one name
 * (D-597): the first definition is kept and the other is left out, so
 * the site keeps loading. Each side is named by the class of the source
 * that defined it (a `ContentTypeSource` or `RelationSource`), which
 * finds its plugin (`Plugins::owning()`).
 */
final readonly class DefinitionClash
{
	/**
	 * @param string $kind    What's defined twice: `type` or `relation`.
	 * @param string $name    Its name.
	 * @param string $kept    The class whose definition is kept.
	 * @param string $dropped The class whose definition is left out.
	 */
	public function __construct(
		public string $kind,
		public string $name,
		public string $kept,
		public string $dropped
	) {}

	/**
	 * Builds a clash from `toArray()`'s shape, or `null` for anything
	 * else.
	 */
	public static function fromArray(mixed $data): ?self
	{
		$kind    = is_array($data) ? $data['kind'] ?? null : null;
		$name    = is_array($data) ? $data['name'] ?? null : null;
		$kept    = is_array($data) ? $data['kept'] ?? null : null;
		$dropped = is_array($data) ? $data['dropped'] ?? null : null;

		return is_string($kind) && is_string($name) && is_string($kept) && is_string($dropped) ? new self($kind, $name, $kept, $dropped) : null;
	}

	/**
	 * Returns the clash as plain data, for the compiled types.
	 *
	 * @return array{kind: string, name: string, kept: string, dropped: string}
	 */
	public function toArray(): array
	{
		return ['kind' => $this->kind, 'name' => $this->name, 'kept' => $this->kept, 'dropped' => $this->dropped];
	}
}
