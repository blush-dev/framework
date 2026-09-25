<?php

/**
 * Extension manifest.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Extension;

/**
 * Describes one extension: its name, version, service provider, where it
 * lives, and (for local extensions) the PSR-4 map Blush autoloads it with.
 * An extension is a manifest plus a service provider (D-041).
 *
 * `requires` maps a requirement to a version constraint, Composer style:
 * `php`, `blush`, `ext-{name}` for PHP extensions, and other extensions by
 * name. It's recorded now and checked by `extension:check` and `doctor`.
 */
final readonly class ExtensionManifest
{
	/**
	 * Matches an extension name: a Composer-style `vendor/name`, or a bare
	 * slug for local extensions.
	 */
	private const string NAME_PATTERN = '#^[a-z0-9]([_.-]?[a-z0-9]+)*(/[a-z0-9](([_.]|-{1,2})?[a-z0-9]+)*)?$#';

	/**
	 * Matches a fully qualified class name.
	 */
	private const string CLASS_PATTERN = '/^[A-Za-z_][A-Za-z0-9_]*(\\\\[A-Za-z_][A-Za-z0-9_]*)*$/';

	/**
	 * @param string                $provider Fully qualified class name of the extension's service provider.
	 * @param array<string, string> $autoload PSR-4 namespace prefix => directory relative to `$path`.
	 * @param array<string, string> $requires Requirement => version constraint.
	 * @throws ExtensionException
	 */
	public function __construct(
		public string $name,
		public string $provider,
		public ExtensionSource $source,
		public string $path,
		public string $version = '0.0.0',
		public string $description = '',
		public array $autoload = [],
		public array $requires = []
	) {
		if (preg_match(self::NAME_PATTERN, $name) !== 1) {
			throw new ExtensionException(sprintf(
				'Extension name "%s" is invalid; use a lowercase slug or vendor/name.',
				$name
			));
		}

		if (preg_match(self::CLASS_PATTERN, ltrim($provider, '\\')) !== 1) {
			throw new ExtensionException(sprintf(
				'Extension "%s" must name its service provider class; "%s" is not a class name.',
				$name,
				$provider
			));
		}

		foreach ($autoload as $prefix => $directory) {
			if (! str_ends_with($prefix, '\\') || preg_match(self::CLASS_PATTERN, rtrim($prefix, '\\')) !== 1) {
				throw new ExtensionException(sprintf(
					'Extension "%s" autoload prefix "%s" must be a namespace ending in a backslash.',
					$name,
					$prefix
				));
			}

			if ($directory === '' || str_starts_with($directory, '/') || str_contains($directory, '..')) {
				throw new ExtensionException(sprintf(
					'Extension "%s" autoload directory "%s" must be a relative path inside the extension.',
					$name,
					$directory
				));
			}
		}
	}

	/**
	 * The provider class name, without a leading backslash.
	 *
	 * @return class-string
	 */
	public function providerClass(): string
	{
		/** @var class-string Validated as a class name by the constructor. */
		return ltrim($this->provider, '\\');
	}

	/**
	 * Builds a manifest from an array (a decoded manifest file, or the
	 * cached form).
	 *
	 * @param  array<array-key, mixed> $data
	 * @throws ExtensionException
	 */
	public static function fromArray(array $data): self
	{
		$source = $data['source'] ?? null;

		return new self(
			name: self::string($data, 'name'),
			provider: self::string($data, 'provider'),
			source: $source instanceof ExtensionSource
				? $source
				: ExtensionSource::tryFrom(is_string($source) ? $source : '') ?? throw new ExtensionException('Extension "source" is invalid.'),
			path: self::string($data, 'path'),
			version: self::string($data, 'version', '0.0.0'),
			description: self::string($data, 'description', ''),
			autoload: self::map($data, 'autoload'),
			requires: self::map($data, 'requires')
		);
	}

	/**
	 * Returns the manifest as an array `fromArray()` accepts.
	 *
	 * @return array<string, mixed>
	 */
	public function toArray(): array
	{
		return [
			'name'        => $this->name,
			'provider'    => $this->provider,
			'source'      => $this->source,
			'path'        => $this->path,
			'version'     => $this->version,
			'description' => $this->description,
			'autoload'    => $this->autoload,
			'requires'    => $this->requires
		];
	}

	/**
	 * Reads a required (or defaulted) string.
	 *
	 * @param  array<array-key, mixed> $data
	 * @throws ExtensionException
	 */
	private static function string(array $data, string $key, ?string $default = null): string
	{
		$value = $data[$key] ?? $default;

		if (! is_string($value)) {
			throw new ExtensionException(sprintf(
				'Extension manifest "%s"%s must be a string.',
				$key,
				is_string($data['name'] ?? null) ? " for \"{$data['name']}\"" : ''
			));
		}

		return $value;
	}

	/**
	 * Reads an optional string-to-string map.
	 *
	 * @param  array<array-key, mixed> $data
	 * @return array<string, string>
	 * @throws ExtensionException
	 */
	private static function map(array $data, string $key): array
	{
		$value = $data[$key] ?? [];
		$map   = [];

		if (! is_array($value)) {
			throw new ExtensionException(sprintf('Extension manifest "%s" must be an object.', $key));
		}

		foreach ($value as $name => $item) {
			if (! is_string($name) || ! is_string($item)) {
				throw new ExtensionException(sprintf('Extension manifest "%s" must map strings to strings.', $key));
			}

			$map[$name] = $item;
		}

		return $map;
	}
}
