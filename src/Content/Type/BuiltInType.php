<?php

/**
 * Built-in content types.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Type;

use Blush\Translation\Messages;

/**
 * The content types the framework registers:
 *
 * - `page`: everything in `user/content` no other type claims, routed by
 *   the page catch-all rather than routes of its own.
 * - `profile` (D-043, D-351): entries in `user/content/_profile` (D-683,
 *   D-684), the people other types credit through credit relations (D-602), such as
 *   `authors` (read from 1.x's `author` too). Each has a page at
 *   `/profiles/{slug}`.
 *
 * The site can redefine either as a data type, and can disable `profile`.
 */
enum BuiltInType: string
{
	case Page    = 'page';
	case Profile = 'profile';

	/**
	 * Returns the type's default definition, its labels core's messages
	 * (D-841), so the admin and the site show them in the current
	 * language.
	 */
	public function type(Messages $messages = new Messages()): ContentType
	{
		return match ($this) {
			// What core's types support (D-866): pages a featured image, a
			// summary, an order among siblings, and a visibility; profiles
			// the line under a name, and a visibility.
			self::Page    => new Tree(messages: $messages, visibility: true, image: true, summary: true, position: true),
			self::Profile => new Profiles(messages: $messages, visibility: true, subtitle: true)
		};
	}

	/**
	 * Returns the type's labels as core's messages, every one written
	 * out, since the defaults made from a name are English: what a
	 * `Tree` or `Profiles` by this name gets without labels of its own,
	 * from the framework or a plugin (D-842).
	 */
	public function labels(Messages $messages): TypeLabels
	{
		return match ($this) {
			self::Page    => new TypeLabels(
				$messages->t('content.built_in_type.page.singular', 'Page'),
				$messages->t('content.built_in_type.page.plural', 'Pages'),
				$messages->t('content.built_in_type.page.menu', 'Pages'),
				$messages->t('content.built_in_type.page.item', 'page'),
				$messages->t('content.built_in_type.page.items', 'pages'),
				$messages->t('content.built_in_type.page.new_item', 'New Page'),
				$messages->t('content.built_in_type.page.edit_item', 'Edit page'),
				$messages->t('content.built_in_type.page.search_items', 'Search pages')
			),
			self::Profile => new TypeLabels(
				$messages->t('content.built_in_type.profile.singular', 'Profile'),
				$messages->t('content.built_in_type.profile.plural', 'Profiles'),
				$messages->t('content.built_in_type.profile.menu', 'Profiles'),
				$messages->t('content.built_in_type.profile.item', 'profile'),
				$messages->t('content.built_in_type.profile.items', 'profiles'),
				$messages->t('content.built_in_type.profile.new_item', 'New Profile'),
				$messages->t('content.built_in_type.profile.edit_item', 'Edit profile'),
				$messages->t('content.built_in_type.profile.search_items', 'Search profiles')
			)
		};
	}

	/**
	 * Returns the type's kind.
	 */
	public function kind(): TypeKind
	{
		return match ($this) {
			self::Page    => TypeKind::Tree,
			self::Profile => TypeKind::Profiles
		};
	}

	/**
	 * Returns core's labels for a type of a kind and name when it's one
	 * of these by its default name (a tree named `page`, profiles named
	 * `profile`), whoever defines it, else `null` (D-842).
	 */
	public static function labelsFor(TypeKind $kind, string $name, Messages $messages): ?TypeLabels
	{
		$builtIn = self::tryFrom($name);

		return $builtIn?->kind() === $kind ? $builtIn->labels($messages) : null;
	}

	/**
	 * Returns whether the site may disable the type. Pages can't be
	 * disabled; every file needs a type to fall back on.
	 */
	public function canDisable(): bool
	{
		return $this !== self::Page;
	}
}
