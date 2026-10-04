<?php

/**
 * Extension kind.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Extension;

/**
 * The kinds of extension a site installs (D-378). Each kind has its own
 * manifest file and its own Composer package type, either of which says
 * a folder is one (D-432), and says whether it runs code.
 *
 * Admin themes are planned as a fourth kind, on the same pieces.
 */
enum ExtensionKind: string
{
	/** Code: a manifest plus a service provider (D-041). Many are on at once. */
	case Plugin = 'plugin';

	/** Presentation, which may run PHP (D-020). One chain is active. */
	case Theme = 'theme';

	/** SVG icons in the pack's namespace. Many are on at once (D-385). */
	case IconPack = 'icon-pack';

	/**
	 * Returns the kind's name, for messages.
	 */
	public function label(): string
	{
		return match ($this) {
			self::Plugin   => 'plugin',
			self::Theme    => 'theme',
			self::IconPack => 'icon pack'
		};
	}

	/**
	 * Returns the manifest's file name, without its format
	 * (`plugin.json`, `theme.yaml`, `icons.json`).
	 */
	public function manifest(): string
	{
		return match ($this) {
			self::Plugin   => 'plugin',
			self::Theme    => 'theme',
			self::IconPack => 'icons'
		};
	}

	/**
	 * Returns the Composer package type of the kind's packages.
	 */
	public function packageType(): string
	{
		return match ($this) {
			self::Plugin   => 'blush-plugin',
			self::Theme    => 'blush-theme',
			self::IconPack => 'blush-icons'
		};
	}

	/**
	 * Returns the kind whose Composer package type this is, or `null`
	 * when it isn't one of Blush's.
	 */
	public static function fromPackageType(mixed $type): ?self
	{
		return array_find(self::cases(), static fn (self $kind): bool => $kind->packageType() === $type);
	}

	/**
	 * Returns whether the kind's extensions run PHP.
	 */
	public function runsCode(): bool
	{
		return $this !== self::IconPack;
	}
}
