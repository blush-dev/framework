<?php

/**
 * Icon pack cache.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Icon;

use Blush\Extension\ExtensionException;
use Blush\Support\PhpArrayFile;

/**
 * The compiled list of installed icon packs
 * (`storage/cache/icon-packs.php`), read everywhere but development, like
 * the theme cache.
 */
final readonly class IconPackCache
{
	public function __construct(private PhpArrayFile $file)
	{}

	/**
	 * Returns the cached packs, or `null` when there's no usable cache.
	 */
	public function read(): ?IconPacks
	{
		$data = $this->file->read();

		if (! is_array($data['packs'] ?? null) || ! is_array($data['invalid'] ?? null)) {
			return null;
		}

		$packs = [];

		try {
			foreach ($data['packs'] as $name => $pack) {
				if (! is_array($pack) || ! is_string($pack['path'] ?? null) || ! is_array($pack['data'] ?? null)) {
					return null;
				}

				$packs[(string) $name] = IconPack::fromArray(
					$pack['path'],
					$pack['data'],
					IconPackSource::tryFrom(is_string($pack['source'] ?? null) ? $pack['source'] : '') ?? IconPackSource::Local
				);
			}
		} catch (ExtensionException) {
			return null;
		}

		/** @var array<string, string> $invalid */
		$invalid = array_filter($data['invalid'], is_string(...));

		return new IconPacks($packs, $invalid);
	}

	/**
	 * Writes the packs.
	 */
	public function write(IconPacks $packs): void
	{
		$this->file->write([
			'packs'   => array_map(static fn (IconPack $pack): array => $pack->toArray(), $packs->all()),
			'invalid' => $packs->invalid()
		]);
	}
}
