<?php

/**
 * Activity actor.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Activity;

use NoDiscard;
use Blush\Auth\Account;
use Blush\Auth\BuiltInRole;
use Blush\Auth\Token;

/**
 * Who makes a change, and how (D-758): an account, where the change came
 * from, and, for an app or a script, the name it goes by (an OAuth
 * client's, or the token's) and the token it used, so the activity log
 * can say "Changed by Claude (Jane's account)" and an app's changes can
 * be listed apart. A change that undoes another names it (`undoes`).
 */
final readonly class Actor
{
	/**
	 * The username of the command line's stand-in account, never saved.
	 */
	public const string COMMAND_LINE = 'command-line';

	public function __construct(
		public Account $account,
		public Via $via = Via::Admin,
		public ?string $client = null,
		public ?string $token = null,
		public ?string $undoes = null
	) {}

	/**
	 * Returns a copy whose changes undo one from the log, which the log
	 * then marks as undone by them.
	 */
	#[NoDiscard]
	public function undoing(Activity $activity): self
	{
		return clone($this, ['undoes' => $activity->id]);
	}

	/**
	 * An account in the admin.
	 */
	public static function admin(Account $account): self
	{
		return new self($account);
	}

	/**
	 * An account on the command line.
	 */
	public static function cli(Account $account): self
	{
		return new self($account, Via::Cli);
	}

	/**
	 * Whoever runs a command that names no account: trusted, since they
	 * have the site already, so it may do anything, and logged as the
	 * command line, with no account and no byline.
	 */
	public static function commandLine(): self
	{
		return new self(new Account(self::COMMAND_LINE, '', [BuiltInRole::Owner->value]), Via::Cli);
	}

	/**
	 * Whether the actor is the command line, with no account.
	 */
	public function isCommandLine(): bool
	{
		return $this->account->id === '' && $this->account->username === self::COMMAND_LINE;
	}

	/**
	 * An account through a token: an app's, by its client's name, or a
	 * script's, by the token's.
	 */
	public static function token(Account $account, Via $via, Token $token, ?string $client = null): self
	{
		return new self($account, $via, $client ?? $token->name, $token->id);
	}
}
