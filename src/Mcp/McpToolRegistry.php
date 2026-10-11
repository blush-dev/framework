<?php

/**
 * MCP tool registry.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Mcp;

use Override;
use Blush\Support\Registry;
use Blush\Support\RegistrationException;

/**
 * The MCP server's tools, by name (D-753, D-757). Blush's own have bare
 * names (`get_entry`); a plugin's start with its vendor and name
 * (`acme_gallery_list_albums`). A name is lowercase letters, digits, and
 * `_`, at most 64 characters, since some clients hand tool names to
 * models that take no more; a name that's taken is refused.
 *
 *     $this->container->make(McpToolRegistry::class)->register('acme_gallery_list_albums', ListAlbums::class);
 *
 * @extends Registry<Tool>
 */
final class McpToolRegistry extends Registry
{
	/**
	 * What a name looks like.
	 */
	public const string NAME = '/^[a-z][a-z0-9_]{0,63}$/';

	/**
	 * @inheritDoc
	 */
	protected const string CONTRACT = Tool::class;

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function register(string $key, string $className): void
	{
		if (preg_match(self::NAME, $key) !== 1) {
			throw new RegistrationException(sprintf('An MCP tool\'s name is lowercase letters, digits, and "_", starting with a letter, at most 64 characters, such as "acme_gallery_list_albums"; "%s" given.', $key));
		}

		if ($this->isRegistered($key) && $this->get($key) !== $className) {
			throw new RegistrationException(sprintf('There\'s already an MCP tool named "%s".', $key));
		}

		parent::register($key, $className);
	}
}
