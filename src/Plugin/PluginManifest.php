<?php

/**
 * Plugin manifest.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Plugin;

use Blush\Extension\Autoload;
use Blush\Extension\ExtensionAuthor;
use Blush\Extension\ExtensionException;
use Blush\Extension\ExtensionName;
use Blush\Extension\ExtensionNamespace;

/**
 * Describes one plugin: its name (`vendor/name`, D-378), label (its
 * name when the manifest has none, D-423), namespace (its name,
 * hyphenated, when it has none, D-424), version, service provider, where it lives, and (for local
 * plugins) the `autoload` Blush loads it with (`psr-4` and `files`,
 * D-418). A plugin is a manifest plus a service provider (D-041).
 *
 * `require` maps a requirement to a version constraint, as Composer's
 * does (D-418): `php`, Blush as `blush-dev/framework`, `ext-{name}` for
 * PHP extensions, and other plugins by name (`vendor/name`). A plugin
 * whose requirements aren't met doesn't run (D-385,
 * `PluginRequirements`).
 *
 * `authors` (D-384's shape) and `license` (a string, `MIT`) say who made
 * it and how it may be used; the finders fill either from `composer.json`
 * when the manifest leaves it out.
 */
final readonly class PluginManifest
{
	/**
	 * Matches a fully qualified class name.
	 */
	private const string CLASS_PATTERN = '/^[A-Za-z_][A-Za-z0-9_]*(\\\\[A-Za-z_][A-Za-z0-9_]*)*$/';

	/**
	 * @param string                $provider Fully qualified class name of the plugin's service provider.
	 * @param array<string, string> $require  Requirement => version constraint.
	 * @param list<ExtensionAuthor> $authors  Who made it.
	 * @throws ExtensionException
	 */
	public function __construct(
		public string $name,
		public string $label,
		public string $namespace,
		public string $provider,
		public PluginSource $source,
		public string $path,
		public string $version = '0.0.0',
		public string $description = '',
		public Autoload $autoload = new Autoload(),
		public array $require = [],
		public array $authors = [],
		public string $license = ''
	) {
		if (! ExtensionName::isValid($name)) {
			throw new ExtensionException(sprintf(
				'Plugin name "%s" is invalid; use vendor/name, such as "acme/gallery".',
				$name
			));
		}

		if (trim($label) === '') {
			throw new ExtensionException(sprintf('Plugin "%s" needs a "label".', $name));
		}

		if (! ExtensionNamespace::isValid($namespace) || ExtensionNamespace::isReserved($namespace)) {
			throw new ExtensionException(sprintf(
				'Plugin "%s" namespace "%s" is invalid; use lowercase letters, digits, hyphens, and underscores, and not %s.',
				$name,
				$namespace,
				implode(', ', ExtensionNamespace::RESERVED)
			));
		}

		if (preg_match(self::CLASS_PATTERN, ltrim($provider, '\\')) !== 1) {
			throw new ExtensionException(sprintf(
				'Plugin "%s" must name its service provider class; "%s" is not a class name.',
				$name,
				$provider
			));
		}

		foreach (array_keys($autoload->psr4) as $prefix) {
			if (preg_match(self::CLASS_PATTERN, rtrim($prefix, '\\')) !== 1) {
				throw new ExtensionException(sprintf(
					'Plugin "%s" autoload prefix "%s" must be a namespace ending in a backslash.',
					$name,
					$prefix
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

		try {
			$authors  = ExtensionAuthor::list($data['authors'] ?? []);
			$autoload = Autoload::fromArray($data['autoload'] ?? null);
		} catch (ExtensionException $error) {
			throw new ExtensionException(sprintf('Plugin manifest%s: %s', is_string($data['name'] ?? null) ? " for \"{$data['name']}\"" : '', $error->getMessage()), previous: $error);
		}

		return new self(
			name: self::string($data, 'name'),
			label: ExtensionName::label(self::string($data, 'label', ''), self::string($data, 'name')),
			namespace: self::string($data, 'namespace', ExtensionNamespace::fromName(self::string($data, 'name'))),
			provider: self::string($data, 'provider'),
			source: $source instanceof PluginSource
				? $source
				: PluginSource::tryFrom(is_string($source) ? $source : '') ?? throw new ExtensionException('Plugin "source" is invalid.'),
			path: self::string($data, 'path'),
			version: self::string($data, 'version', '0.0.0'),
			description: self::string($data, 'description', ''),
			autoload: $autoload,
			require: self::map($data, 'require'),
			authors: $authors,
			license: self::string($data, 'license', '')
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
			'label'       => $this->label,
			'namespace'   => $this->namespace,
			'provider'    => $this->provider,
			'source'      => $this->source,
			'path'        => $this->path,
			'version'     => $this->version,
			'description' => $this->description,
			'autoload'    => $this->autoload->toArray(),
			'require'     => $this->require,
			'authors'     => array_map(static fn (ExtensionAuthor $author): array => $author->toArray(), $this->authors),
			'license'     => $this->license
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
				'Plugin manifest "%s"%s must be a string.',
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
			throw new ExtensionException(sprintf('Plugin manifest "%s" must be an object.', $key));
		}

		foreach ($value as $name => $item) {
			if (! is_string($name) || ! is_string($item)) {
				throw new ExtensionException(sprintf('Plugin manifest "%s" must map strings to strings.', $key));
			}

			$map[$name] = $item;
		}

		return $map;
	}
}
