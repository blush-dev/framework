<?php

/**
 * Tool registry.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tool;

use Override;
use Blush\Support\Registry;
use Blush\Support\RegistrationException;

/**
 * Tools by key (D-753, D-761): `vendor/name` in lowercase letters,
 * digits, and hyphens, as jobs' are, starting with a letter. Blush's own
 * are `core/…`; a plugin's start with its vendor.
 *
 *     $this->container->make(ToolRegistry::class)->register('acme/rebuild-thumbnails', RebuildThumbnails::class);
 *
 * Each key has a flat name, one word for places that take no `/`: MCP's
 * tool names (D-757). Blush's own drop `core`, `get_entry`; anyone
 * else's keep their vendor, `acme_rebuild_thumbnails`. A flat name is at
 * most 64 characters, since some clients hand tool names to models that
 * take no more, and a key whose flat name is taken is refused.
 *
 * @extends Registry<Tool>
 */
final class ToolRegistry extends Registry
{
	/**
	 * What a key looks like.
	 */
	public const string KEY = '/^[a-z][a-z0-9-]*\/[a-z][a-z0-9-]*$/';

	/**
	 * The vendor of Blush's own tools.
	 */
	public const string CORE = 'core';

	/**
	 * @inheritDoc
	 */
	protected const string CONTRACT = Tool::class;

	/**
	 * Keys by flat name.
	 *
	 * @var array<string, string>
	 */
	private array $flat = [];

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function register(string $key, string $className): void
	{
		if (preg_match(self::KEY, $key) !== 1) {
			throw new RegistrationException(sprintf('A tool\'s key is "vendor/name" in lowercase letters, digits, and hyphens, each starting with a letter, such as "acme/rebuild-thumbnails"; "%s" given.', $key));
		}

		$flat = self::flatName($key);

		if (strlen($flat) > 64) {
			throw new RegistrationException(sprintf('The tool "%s" needs a shorter key: as one word, "%s", it\'s over 64 characters.', $key, $flat));
		}

		$taken = $this->flat[$flat] ?? null;

		if (($taken !== null && $taken !== $key) || ($this->isRegistered($key) && $this->get($key) !== $className)) {
			throw new RegistrationException(sprintf('There\'s already a tool "%s".', $taken ?? $key));
		}

		parent::register($key, $className);

		$this->flat[$flat] = $key;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function unregister(string $key): void
	{
		parent::unregister($key);

		unset($this->flat[self::flatName($key)]);
	}

	/**
	 * Returns the key a flat name stands for, or `null` for none.
	 */
	public function keyOf(string $flat): ?string
	{
		return $this->flat[$flat] ?? null;
	}

	/**
	 * Returns a key as one word: `core/get-entry` as `get_entry`,
	 * `acme/rebuild-thumbnails` as `acme_rebuild_thumbnails`.
	 */
	public static function flatName(string $key): string
	{
		[$vendor, $name] = explode('/', $key, 2) + [1 => ''];

		return str_replace('-', '_', $vendor === self::CORE ? $name : "{$vendor}_{$name}");
	}
}
