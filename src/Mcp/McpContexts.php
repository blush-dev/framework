<?php

/**
 * MCP contexts.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Mcp;

use Blush\Activity\Actor;
use Blush\Activity\Via;
use Blush\Auth\Account;
use Blush\Auth\AuthException;
use Blush\Auth\Permissions;
use Blush\Auth\Token;
use Blush\Auth\TokenScope;
use Blush\Content\Editing\EntryEditor;
use Blush\Media\MediaEditor;

/**
 * Makes the context a tool call runs in (D-751): for a token, over the
 * web, narrowed to its capabilities and counted against the limits; for
 * an account on the command line (`blush mcp`, D-754), as the account,
 * uncounted.
 */
final readonly class McpContexts
{
	public function __construct(
		private Permissions $permissions,
		private EntryEditor $entries,
		private MediaEditor $media
	) {}

	/**
	 * A token's calls, by an app (its client's name) or a script.
	 *
	 * @throws AuthException For a token whose capabilities aren't names.
	 */
	public function forToken(Account $account, Token $token, ?string $client = null): McpContext
	{
		return new McpContext(
			actor: Actor::token($account, Via::Mcp, $token, $client),
			permissions: $this->permissions->limitedTo($token->capabilities),
			entries: $this->entries->limitedTo($token->capabilities),
			media: $this->media->limitedTo($token->capabilities),
			scope: TokenScope::of($token->capabilities),
			limited: true
		);
	}

	/**
	 * An account's calls on the command line.
	 */
	public function forAccount(Account $account): McpContext
	{
		return new McpContext(
			actor: new Actor($account, Via::Mcp, 'Command line'),
			permissions: $this->permissions,
			entries: $this->entries,
			media: $this->media
		);
	}
}
