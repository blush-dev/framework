<?php

/**
 * Broken plugin.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Plugin;

/**
 * A plugin whose manifest can't be read (D-394): listed, never run. It's
 * known by where it was found (a Composer package's name, or its folder
 * from the site's root) and, when the manifest says so, its name.
 */
final readonly class BrokenPlugin
{
	/**
	 * @param string $where  A Composer package's name, or the folder from the site's root.
	 * @param string $reason Why it can't be read.
	 * @param string $name   Its `vendor/name`, or empty when the manifest doesn't say.
	 */
	public function __construct(
		public string $where,
		public string $reason,
		public string $name,
		public PluginSource $source
	) {}

	/**
	 * Builds one from `toArray()`'s shape.
	 *
	 * @param array<array-key, mixed> $data
	 */
	public static function fromArray(array $data): self
	{
		return new self(
			is_string($data['where'] ?? null) ? $data['where'] : '',
			is_string($data['reason'] ?? null) ? $data['reason'] : '',
			is_string($data['name'] ?? null) ? $data['name'] : '',
			PluginSource::tryFrom(is_string($data['source'] ?? null) ? $data['source'] : '') ?? PluginSource::Local
		);
	}

	/**
	 * @return array{where: string, reason: string, name: string, source: string}
	 */
	public function toArray(): array
	{
		return [
			'where'  => $this->where,
			'reason' => $this->reason,
			'name'   => $this->name,
			'source' => $this->source->value
		];
	}
}
