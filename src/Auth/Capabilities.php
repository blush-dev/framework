<?php

/**
 * Capability registry.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Auth;

use Stringable;
use Blush\Content\Type\ContentTypes;
use Blush\Media\MediaKind;
use Blush\Translation\Messages;

/**
 * Every capability the site knows, with its label and group: the site
 * capabilities (`Capability`), uploading each kind of media
 * (`MediaKind::uploadCapability()`, D-407), each kind of extension's
 * (`ExtensionAction`, D-389), plus any a plugin registers from its
 * provider's `boot()`, and each content type's (`ContentAction`, D-359),
 * which follow the site's types. The owner's `*` (and the
 * administrator's, until an owner changes it) grants all of them, and
 * the admin lists them by group.
 */
final class Capabilities
{
	/**
	 * What a capability name may be: dotted lowercase words. A `*` word
	 * after the first stands for any word (`content.*.edit`).
	 */
	public const string NAME = '/^[a-z][a-z0-9_-]*(\.([a-z0-9_-]+|\*))*$/';

	/**
	 * Labels by capability name, translated where they're shown (D-816).
	 *
	 * @var array<string, string|Stringable>
	 */
	private array $labels = [];

	/**
	 * Groups by capability name.
	 *
	 * @var array<string, string|Stringable>
	 */
	private array $groups = [];

	/**
	 * The content capabilities, once worked out.
	 *
	 * @var ?array<string, array{Stringable, string|Stringable}>
	 */
	private ?array $content = null;

	public function __construct(
		private readonly ?ContentTypes $types = null,
		private readonly Messages $messages = new Messages()
	) {}

	/**
	 * Builds the registry with the built-in capabilities, and the content
	 * capabilities of the site's types.
	 */
	public static function withBuiltIns(?ContentTypes $types = null, Messages $messages = new Messages()): self
	{
		$capabilities = new self($types, $messages);
		$media        = $messages->t('auth.capability_group.media', 'Media');

		$capabilities->register(MediaKind::UPLOAD_EVERY, $messages->t('auth.capabilities.upload_every', 'Upload every kind'), $media);

		foreach (MediaKind::cases() as $kind) {
			$capabilities->register($kind->uploadCapability(), $messages->t('auth.capabilities.upload', 'Upload {kind, select, image {images} video {videos} audio {audio} document {documents} other {other files}}', kind: $kind->value), $media);
		}

		foreach (Capability::cases() as $capability) {
			$capabilities->register($capability->value, $capability->label($messages), $capability->group($messages));
		}

		foreach (ExtensionAction::kinds() as $kind) {
			foreach (ExtensionAction::cases() as $action) {
				$capabilities->register($action->on($kind), $action->label($kind, $messages), ExtensionAction::group($kind, $messages));
			}
		}

		return $capabilities;
	}

	/**
	 * Registers a capability, or relabels one. Its group defaults to its
	 * first word ("shop.orders.edit" is Shop).
	 *
	 * @throws AuthException For an invalid name.
	 */
	public function register(string $name, string|Stringable $label, string|Stringable|null $group = null): void
	{
		if (preg_match(self::NAME, $name) !== 1) {
			throw new AuthException(sprintf('"%s" can\'t be a capability; use dotted lowercase words such as "shop.orders.edit".', $name));
		}

		$this->labels[$name] = $label;
		$this->groups[$name] = $group ?? ucfirst(explode('.', $name)[0]);
	}

	/**
	 * Whether a capability is registered.
	 */
	public function has(string $name): bool
	{
		return isset($this->labels[$name]) || isset($this->content()[$name]);
	}

	/**
	 * Returns every capability's label, by name: the registered ones,
	 * then every type's content capabilities, then each type's.
	 *
	 * @return array<string, string>
	 */
	public function all(): array
	{
		return [...array_map(static fn (string|Stringable $label): string => (string) $label, $this->labels), ...array_map(static fn (array $item): string => (string) $item[0], $this->content())];
	}

	/**
	 * Returns the group a capability is shown in: a content type's plural
	 * label for its content capabilities, and "Every type" for those of
	 * every type.
	 */
	public function group(string $name): string
	{
		return (string) ($this->groups[$name] ?? $this->content()[$name][1] ?? ucfirst(explode('.', $name)[0]));
	}

	/**
	 * Returns the content capabilities, by name, each with its label and
	 * group.
	 *
	 * @return array<string, array{Stringable, string|Stringable}>
	 */
	private function content(): array
	{
		if ($this->content !== null) {
			return $this->content;
		}

		$groups = [ContentAction::EVERY => $this->messages->t('auth.capabilities.every_type', 'Every type')];

		foreach ($this->types?->all() ?? [] as $type) {
			$groups[$type->name] = $type->labels->plural;
		}

		$content = [];

		foreach ($groups as $type => $group) {
			foreach (ContentAction::cases() as $action) {
				$content[$action->on($type)] = [$this->messages->t('auth.capabilities.content', '{type}: {action}', type: $group, action: $action->label($this->messages)), $group];
			}
		}

		return $this->content = $content;
	}
}
