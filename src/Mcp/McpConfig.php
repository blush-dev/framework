<?php

/**
 * MCP config.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Mcp;

use Override;
use Blush\Config\Config;
use Blush\Config\ConfigValues;
use Blush\Config\InvalidConfig;

/**
 * The site's MCP server (D-751 to D-758), from `config/mcp.php` or
 * Settings → AI:
 *
 *     return new McpConfig(enabled: true);
 *
 * - `enabled` answers MCP over the web, at the API's `path` with `/mcp`
 *   (`/api/mcp`), with OAuth for apps to connect; off by default, and
 *   off, those addresses are the site's own 404. `blush mcp` works
 *   either way (D-754).
 * - `maxCalls` is how many tool calls a token may make in an hour, and
 *   `maxWrites` how many of them may change something (D-758).
 */
final readonly class McpConfig implements Config
{
	/**
	 * @throws InvalidConfig
	 */
	public function __construct(
		public bool $enabled = false,
		public int $maxCalls = 600,
		public int $maxWrites = 60
	) {
		if ($maxCalls < 1 || $maxWrites < 1) {
			throw new InvalidConfig('McpConfig "maxCalls" and "maxWrites" must be 1 or more.');
		}
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function fromArray(array $data): static
	{
		$values = new ConfigValues($data, self::class);
		$values->assertKnownKeys(['enabled', 'maxCalls', 'maxWrites']);

		return new static(
			enabled: $values->bool('enabled', false),
			maxCalls: $values->int('maxCalls', 600),
			maxWrites: $values->int('maxWrites', 60)
		);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function toArray(): array
	{
		return ['enabled' => $this->enabled, 'maxCalls' => $this->maxCalls, 'maxWrites' => $this->maxWrites];
	}
}
