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

use Blush\Translation\Messages;
use Blush\Translation\Text;

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
	public function label(Messages $messages): Text
	{
		return match ($this) {
			self::Create        => $messages->t('auth.content_action.create', 'Create entries'),
			self::Edit          => $messages->t('auth.content_action.edit', 'Edit their own'),
			self::EditOthers    => $messages->t('auth.content_action.edit_others', 'Edit anyone\'s'),
			self::Publish       => $messages->t('auth.content_action.publish', 'Publish their own'),
			self::PublishOthers => $messages->t('auth.content_action.publish_others', 'Publish anyone\'s'),
			self::Delete        => $messages->t('auth.content_action.delete', 'Delete their own'),
			self::DeleteOthers  => $messages->t('auth.content_action.delete_others', 'Delete anyone\'s')
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
