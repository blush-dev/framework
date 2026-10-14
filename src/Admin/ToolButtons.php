<?php

/**
 * Tool buttons.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin;

use Blush\Activity\Actor;
use Blush\Auth\Account;
use Blush\Tool\Tool;
use Blush\Tool\ToolContext;
use Blush\Tool\ToolContexts;
use Blush\Tool\Tools;
use Blush\Translation\Messages;

/**
 * Which tools are buttons on the Tools screen (D-761): those that need no
 * input and say they're buttons (by default, those that write), for
 * accounts that may use them, run as the account in the admin.
 */
final readonly class ToolButtons
{
	public function __construct(
		private Tools $tools,
		private ToolContexts $contexts,
		private Messages $messages
	) {}

	/**
	 * Returns the buttons an account may use, by key.
	 *
	 * @return array<string, Tool>
	 */
	public function allowed(Account $account): array
	{
		return array_filter($this->tools->allowed($this->context($account)), self::isButton(...));
	}

	/**
	 * Returns a button, or `null` when no tool has the key or it isn't a
	 * button.
	 */
	public function get(string $key): ?Tool
	{
		$tool = $this->tools->get($key);

		return $tool !== null && self::isButton($tool) ? $tool : null;
	}

	/**
	 * The context the admin runs tools in, as the account.
	 */
	public function context(Account $account): ToolContext
	{
		return $this->contexts->forActor(Actor::admin($account));
	}

	/**
	 * Whether a tool is a button: it says so, and needs no input.
	 */
	public static function isButton(Tool $tool): bool
	{
		return $tool->button() && ! $tool->needsInput();
	}

	/**
	 * The question to ask before running a tool: its own, or, for one
	 * that may destroy something, a warning.
	 */
	public function confirm(Tool $tool): ?string
	{
		$confirm = $tool->confirm();

		return $confirm !== null ? (string) $confirm : ($tool->annotations()->destructive ? (string) $this->messages->t('admin.tool_buttons.destructive', '{tool} may remove something that can\'t be put back.', tool: (string) $tool->title()) : null);
	}
}
