<?php

/**
 * Extension schema.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Extension;

use Closure;
use JsonException;
use Blush\Translation\Messages;
use Blush\Translation\Text;

/**
 * The JSON files an extension writes against a schema Blush publishes
 * (D-908), each with its own `schemaVersion`: the version of the JSON
 * Schema it's written to, an integer, 1 when it's missing. A Composer
 * package keeps its manifest's under `extra.blush`.
 *
 * A file of an older version is upgraded as it's read, one step per
 * version, and never rewritten; one newer than Blush reads is refused, so
 * it's never read as an older shape. Each version's schema is its own
 * file (`theme.v1.schema.json`), never changed once released.
 */
enum ExtensionSchema: string
{
	case Plugin      = 'plugin';
	case Theme       = 'theme';
	case IconPack    = 'icons';
	case AdminTheme  = 'admin-theme';
	case AdminTokens = 'admin-tokens';

	/**
	 * The key a file says its version with.
	 */
	public const string KEY = 'schemaVersion';

	/**
	 * Returns the schema of a kind's manifest.
	 */
	public static function of(ExtensionKind $kind): self
	{
		return match ($kind) {
			ExtensionKind::Plugin     => self::Plugin,
			ExtensionKind::Theme      => self::Theme,
			ExtensionKind::IconPack   => self::IconPack,
			ExtensionKind::AdminTheme => self::AdminTheme
		};
	}

	/**
	 * Returns the version Blush writes and reads up to.
	 */
	public function current(): int
	{
		return 1;
	}

	/**
	 * Returns the file the JSON Schema of a version is in, in
	 * `resources/schemas` (`theme.v1.schema.json`), the current version's
	 * when none is given.
	 */
	public function file(?int $version = null): string
	{
		return sprintf('%s.v%d.schema.json', $this->value, $version ?? $this->current());
	}

	/**
	 * Returns the version a file's data says it's written to.
	 *
	 * @param  array<array-key, mixed> $data
	 * @throws ExtensionException When it isn't a whole number of 1 or more.
	 */
	public function version(array $data, string $file, Messages $messages = new Messages()): int
	{
		$version = $data[self::KEY] ?? 1;

		if (! is_int($version) || $version < 1) {
			throw new ExtensionException((string) $messages->t('extension.extension_schema.invalid', 'The "{name}" in {file} must be a whole number, 1 or more.', name: self::KEY, file: $file));
		}

		return $version;
	}

	/**
	 * Returns a file's data in the current version's shape, without its
	 * `schemaVersion`.
	 *
	 * @param  array<string, mixed> $data
	 * @return array<string, mixed>
	 * @throws ExtensionException When its version isn't one, or is newer than Blush reads.
	 */
	public function upgrade(array $data, string $file, Messages $messages = new Messages()): array
	{
		$version = $this->version($data, $file, $messages);
		$current = $this->current();

		if ($version > $current) {
			throw new ExtensionException((string) $messages->t(
				'extension.extension_schema.newer',
				'{file} is written to schema version {version}, and this Blush reads up to version {current}; it needs a newer Blush.',
				file: $file,
				version: $version,
				current: $current
			));
		}

		unset($data[self::KEY]);

		for (; $version < $current; $version++) {
			$data = ($this->steps()[$version])($data);
		}

		return $data;
	}

	/**
	 * Returns a warning when a file is written to an older version, with
	 * the file as the current version would write it; `null` when it's
	 * current, or when its version can't be read (reading it says why).
	 *
	 * @param array<string, mixed> $data
	 */
	public function outdated(array $data, string $file, Messages $messages = new Messages()): ?Text
	{
		try {
			$version  = $this->version($data, $file, $messages);
			$upgraded = $version < $this->current() ? $this->upgrade($data, $file, $messages) : null;
		} catch (ExtensionException) {
			return null;
		}

		if ($upgraded === null) {
			return null;
		}

		$schema = array_key_exists('$schema', $upgraded) ? ['$schema' => $upgraded['$schema']] : [];

		try {
			$json = json_encode([...$schema, self::KEY => $this->current(), ...$upgraded], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
		} catch (JsonException) {
			return null;
		}

		return $messages->t(
			'extension.extension_schema.outdated',
			"{file} is written to schema version {version}; the current is {current}. Blush still reads it. Written for version {current}, it's:\n{json}",
			file: $file,
			version: $version,
			current: $this->current(),
			json: $json
		);
	}

	/**
	 * Returns each version's step to the next, by the version it reads.
	 * None yet: every schema is at version 1.
	 *
	 * @return array<int, Closure(array<string, mixed>): array<string, mixed>>
	 */
	private function steps(): array
	{
		return [];
	}
}
