<?php

/**
 * Content actions.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Auth;

/**
 * What a role may do to the entries of a content type (D-359). Each
 * content type has a capability for each action, named
 * `content.{type}.{action}` (`content.post.edit.others`), and
 * `content.*.{action}` grants it on every type, including ones added
 * later. An `.others` action extends its base to entries the account
 * doesn't own (see `Permissions`), and is no use without it.
 */
enum ContentAction: string
{
	case Create        = 'create';
	case Edit          = 'edit';
	case EditOthers    = 'edit.others';
	case Publish       = 'publish';
	case PublishOthers = 'publish.others';
	case Delete        = 'delete';
	case DeleteOthers  = 'delete.others';

	/**
	 * The type segment that stands for every type.
	 */
	public const string EVERY = '*';

	/**
	 * Returns the action's capability on a type (or `EVERY`).
	 */
	public function on(string $type): string
	{
		return "content.{$type}.{$this->value}";
	}

	/**
	 * Returns the action without `.others`.
	 */
	public function base(): self
	{
		return match ($this) {
			self::EditOthers    => self::Edit,
			self::PublishOthers => self::Publish,
			self::DeleteOthers  => self::Delete,
			default             => $this
		};
	}

	/**
	 * Returns the action's `.others` form, or `null` for one that has
	 * none (creating).
	 */
	public function others(): ?self
	{
		return match ($this->base()) {
			self::Edit    => self::EditOthers,
			self::Publish => self::PublishOthers,
			self::Delete  => self::DeleteOthers,
			default       => null
		};
	}

	/**
	 * Whether this is an `.others` action.
	 */
	public function isOthers(): bool
	{
		return $this->base() !== $this;
	}

	/**
	 * Returns the action's label, within its type.
	 */
	public function label(): string
	{
		return match ($this) {
			self::Create        => 'Create entries',
			self::Edit          => 'Edit their own',
			self::EditOthers    => 'Edit anyone\'s',
			self::Publish       => 'Publish their own',
			self::PublishOthers => 'Publish anyone\'s',
			self::Delete        => 'Delete their own',
			self::DeleteOthers  => 'Delete anyone\'s'
		};
	}

	/**
	 * Splits a content capability into its type (or `EVERY`) and action,
	 * or returns `null` for any other capability.
	 *
	 * @return ?array{string, self}
	 */
	public static function parse(string $capability): ?array
	{
		if (preg_match('/^content\.([a-z][a-z0-9_]*|\*)\.(.+)$/', $capability, $match) !== 1) {
			return null;
		}

		$action = self::tryFrom($match[2]);

		return $action === null ? null : [$match[1], $action];
	}
}
