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
use Blush\Extension\ExtensionAbandoned;
use Blush\Extension\ExtensionAuthor;
use Blush\Extension\ExtensionException;
use Blush\Extension\ExtensionKind;
use Blush\Extension\ExtensionLicense;
use Blush\Extension\ExtensionLinks;
use Blush\Extension\ExtensionManifest;
use Blush\Extension\ExtensionName;
use Blush\Extension\ExtensionNamespace;
use Blush\Extension\ExtensionRequire;
use Blush\Extension\ExtensionSuggest;

/**
 * Describes one plugin: its name (`vendor/name`, D-378), label (its
 * name when the manifest has none, D-423), namespace (its name,
 * hyphenated, when it has none, D-424), version, service provider, where
 * it lives, and (for local plugins) the `autoload` Blush loads it with
 * (`psr-4` and `files`, D-418). A plugin is a manifest plus, usually, a
 * service provider (D-041); without one, it can still load `files`
 * (template helpers), need other plugins through `require`, or carry a
 * `lang/` catalog, and one with none of these is allowed too (D-425).
 *
 * `require` maps a requirement to a version constraint, as Composer's
 * does (D-418): `php`, Blush as `blush-dev/framework`, `ext-{name}` for
 * PHP extensions, and other extensions of any kind by name
 * (`vendor/name`, D-431). A plugin whose requirements aren't met doesn't
 * run (D-385, `Requirements`).
 *
 * `authors` (D-384's shape) and `license` (`MIT`, or a list any of
 * which applies, D-428) say who made it and how it may be used, and
 * `homepage`, `support`, and `funding` where to learn about, get help
 * with, and fund it (`ExtensionLinks`, D-428); the finders fill each from
 * `composer.json` when the manifest leaves it out. `abandoned` is `true`,
 * or the package to use instead, as Composer's is (`ExtensionAbandoned`,
 * D-433): a warning, not a reason not to run. `conflict` names what it
 * can't run with, as Composer's does (D-435): a plugin that conflicts
 * with something that's on doesn't run. `suggest` maps packages
 * that would work well with it to why (`ExtensionSuggest`, D-434), and
 * is only shown.
 */
final readonly class PluginManifest implements ExtensionManifest
{
	/**
	 * Matches a fully qualified class name.
	 */
	private const string CLASS_PATTERN = '/^[A-Za-z_][A-Za-z0-9_]*(\\\\[A-Za-z_][A-Za-z0-9_]*)*$/';

	/**
	 * @param array<string, string> $require  Requirement => version constraint.
	 * @param array<string, string> $conflict Conflict => the versions it can't run with (D-435).
	 * @param array<string, string> $suggest  Package => why it's suggested (D-434).
	 * @param list<ExtensionAuthor> $authors  Who made it.
	 * @param ?string               $provider Fully qualified class name of the plugin's service provider, if it has one.
	 * @throws ExtensionException
	 */
	public function __construct(
		public string $name,
		public string $label,
		public string $namespace,
		public PluginSource $source,
		public string $path,
		public string $version = '0.0.0',
		public string $description = '',
		public Autoload $autoload = new Autoload(),
		public array $require = [],
		public array $conflict = [],
		public array $authors = [],
		public string $license = '',
		public ExtensionLinks $links = new ExtensionLinks(),
		public ?string $provider = null,
		public bool|string $abandoned = false,
		public array $suggest = []
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

		if ($provider !== null && preg_match(self::CLASS_PATTERN, ltrim($provider, '\\')) !== 1) {
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
	 * The provider class name, without a leading backslash, or `null` for
	 * a plugin without one.
	 *
	 * @return ?class-string
	 */
	public function providerClass(): ?string
	{
		/** @var ?class-string Validated as a class name by the constructor. */
		return $this->provider === null ? null : ltrim($this->provider, '\\');
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
			$license   = ExtensionLicense::fromManifest($data['license'] ?? '');
			$links     = ExtensionLinks::fromArray($data);
			$abandoned = ExtensionAbandoned::fromManifest($data['abandoned'] ?? false);
			$require  = ExtensionRequire::fromArray($data['require'] ?? null);
			$conflict  = ExtensionRequire::fromArray($data['conflict'] ?? null, 'conflict');
			$suggest  = ExtensionSuggest::fromManifest($data['suggest'] ?? null);
		} catch (ExtensionException $error) {
			throw new ExtensionException(sprintf('Plugin manifest%s: %s', is_string($data['name'] ?? null) ? " for \"{$data['name']}\"" : '', $error->getMessage()), previous: $error);
		}

		return new self(
			name: self::string($data, 'name'),
			label: ExtensionName::label(self::string($data, 'label', ''), self::string($data, 'name')),
			namespace: self::string($data, 'namespace', ExtensionNamespace::fromName(self::string($data, 'name'))),
			source: $source instanceof PluginSource
				? $source
				: PluginSource::tryFrom(is_string($source) ? $source : '') ?? throw new ExtensionException('Plugin "source" is invalid.'),
			path: self::string($data, 'path'),
			version: self::string($data, 'version', '0.0.0'),
			description: self::string($data, 'description', ''),
			autoload: $autoload,
			require: $require,
			conflict: $conflict,
			authors: $authors,
			license: $license,
			links: $links,
			provider: isset($data['provider']) ? self::string($data, 'provider') : null,
			abandoned: $abandoned,
			suggest: $suggest
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
			'conflict'    => $this->conflict,
			'authors'     => array_map(static fn (ExtensionAuthor $author): array => $author->toArray(), $this->authors),
			'license'     => $this->license,
			'abandoned'   => $this->abandoned,
			'suggest'     => $this->suggest,
			...$this->links->toArray()
		];
	}

	public function kind(): ExtensionKind
	{
		return ExtensionKind::Plugin;
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
}
