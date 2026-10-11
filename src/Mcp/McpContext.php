<?php

/**
 * MCP context.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Mcp;

use Blush\Activity\Actor;
use Blush\Auth\Account;
use Blush\Auth\Capability;
use Blush\Auth\ContentAction;
use Blush\Auth\Permissions;
use Blush\Auth\TokenScope;
use Blush\Content\Editing\EntryEditor;
use Blush\Content\Entry\Entry;
use Blush\Media\MediaEditor;

/**
 * Who a tool call is for (D-751): the actor (an account, how it came,
 * and an app's name and token), its permissions and the write paths,
 * narrowed to the token's capabilities, the token's scope, and whether
 * its calls are counted against the limits (D-758; a token's are, the
 * command line's aren't).
 */
final readonly class McpContext
{
	public function __construct(
		public Actor $actor,
		public Permissions $permissions,
		public EntryEditor $entries,
		public MediaEditor $media,
		public TokenScope $scope = TokenScope::Account,
		public bool $limited = false
	) {}

	/**
	 * The account the calls act as.
	 */
	public function account(): Account
	{
		return $this->actor->account;
	}

	/**
	 * Whether the account may use a capability, as `Permissions::can()`
	 * answers.
	 */
	public function can(string|Capability|ContentAction $capability, Entry|string|null $on = null): bool
	{
		return $this->permissions->can($this->actor->account, $capability, $on);
	}
}
