<?php

/**
 * Local extension.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Extension;

/**
 * A folder in `extensions/{vendor}/{name}` holding a kind's manifest
 * (D-418), found by `LocalExtensions` before its manifest is read.
 */
final readonly class LocalExtension
{
	/**
	 * @param string  $name    The name its folder gives it, `vendor/name`.
	 * @param string  $path    Its absolute folder.
	 * @param string  $where   Its folder from the site's root, for messages (`extensions/acme/hello`).
	 * @param string  $file    Its kind's winning manifest file.
	 * @param ?string $problem Why it's broken before its manifest is read
	 *                         (it holds more than one kind's manifest).
	 */
	public function __construct(
		public ExtensionKind $kind,
		public string $name,
		public string $path,
		public string $where,
		public string $file,
		public ?string $problem = null
	) {}

	/**
	 * Reads its manifest, filling in what it leaves to its
	 * `composer.json`, and checks that its name is its folder's.
	 *
	 * @return array<string, mixed>
	 * @throws ExtensionException
	 */
	public function read(): array
	{
		if ($this->problem !== null) {
			throw new ExtensionException($this->problem);
		}

		$data = ComposerJson::fill(ManifestFile::read($this->file), $this->path);
		$name = $data['name'] ?? null;

		// A name that isn't one at all is the manifest's to refuse.
		if (is_string($name) && ExtensionName::isValid($name) && $name !== $this->name) {
			throw new ExtensionException(sprintf(
				'The %s in %s is named "%s"; an extension\'s folder is its name, so move it to %s/%s.',
				$this->kind->label(),
				$this->where,
				$name,
				dirname($this->where, 2),
				$name
			));
		}

		return $data;
	}
}
