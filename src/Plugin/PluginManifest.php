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
use Blush\Extension\ExtensionCatalogs;
use Blush\Extension\ExtensionException;
use Blush\Extension\ExtensionKind;
use Blush\Extension\ExtensionLicense;
use Blush\Extension\ExtensionLinks;
use Blush\Extension\ExtensionManifest;
use Blush\Extension\ExtensionName;
use Blush\Extension\ExtensionNamespace;
use Blush\Extension\ExtensionRequire;
use Blush\Extension\ExtensionKeywords;
use Blush\Extension\ExtensionSuggest;
use Blush\Translation\Messages;

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
 * with something that's on doesn't run. `replace` names what it stands
 * in for (D-436): a `require` of one is met by it, and it doesn't run
 * while one is on. `provide` names what it implements (D-439): a
 * `require` of one is met by it, and any number may provide one. `suggest` maps packages
 * that would work well with it to why (`ExtensionSuggest`, D-434), and
 * is only shown. `keywords` are what the admin's filter searches
 * (`ExtensionKeywords`, D-565).
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
	 * @param array<string, string> $replace  What it replaces, each mapped to the versions it stands in for (D-436).
	 * @param array<string, string> $provide  What it provides, each mapped to the versions it provides (D-439).
	 * @param array<string, string> $suggest  Package => why it's suggested (D-434).
	 * @param list<string>          $keywords What it's about, searched by the admin (D-565).
	 * @param array<string, list<string>> $catalogs Its scripts' folders by named catalog, or `[]` for the defaults (D-831).
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
		public array $replace = [],
		public array $provide = [],
		public array $authors = [],
		public string $license = '',
		public ExtensionLinks $links = new ExtensionLinks(),
		public ?string $provider = null,
		public bool|string $abandoned = false,
		public array $suggest = [],
		public array $keywords = [],
		public array $catalogs = [],
		Messages $messages = new Messages()
	) {
		if (! ExtensionName::isValid($name)) {
			throw new ExtensionException((string) $messages->t('plugin.plugin_manifest.bad_name', 'Plugin name "{name}" is invalid; use vendor/name, such as "acme/gallery".', name: $name));
		}

		if (trim($label) === '') {
			throw new ExtensionException((string) $messages->t('plugin.plugin_manifest.no_label', 'Plugin "{name}" needs a "label".', name: $name));
		}

		if (! ExtensionNamespace::isValid($namespace) || ExtensionNamespace::isReserved($namespace)) {
			throw new ExtensionException((string) $messages->t('plugin.plugin_manifest.bad_namespace', 'Plugin "{name}" namespace "{namespace}" is invalid; use lowercase letters, digits, hyphens, and underscores, and not {reserved}.', name: $name, namespace: $namespace, reserved: implode(', ', ExtensionNamespace::RESERVED)));
		}

		if ($provider !== null && preg_match(self::CLASS_PATTERN, ltrim($provider, '\\')) !== 1) {
			throw new ExtensionException((string) $messages->t('plugin.plugin_manifest.bad_provider', 'Plugin "{name}" must name its service provider class; "{provider}" is not a class name.', name: $name, provider: $provider));
		}

		foreach (array_keys($autoload->psr4) as $prefix) {
			if (preg_match(self::CLASS_PATTERN, rtrim($prefix, '\\')) !== 1) {
				throw new ExtensionException((string) $messages->t('plugin.plugin_manifest.bad_prefix', 'Plugin "{name}" autoload prefix "{prefix}" must be a namespace ending in a backslash.', name: $name, prefix: $prefix));
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
	public static function fromArray(array $data, Messages $messages = new Messages()): self
	{
		$source = $data['source'] ?? null;

		try {
			$authors  = ExtensionAuthor::list($data['authors'] ?? [], $messages);
			$autoload = Autoload::fromArray($data['autoload'] ?? null, $messages);
			$license   = ExtensionLicense::fromManifest($data['license'] ?? '', $messages);
			$links     = ExtensionLinks::fromArray($data, $messages);
			$abandoned = ExtensionAbandoned::fromManifest($data['abandoned'] ?? false, $messages);
			$require  = ExtensionRequire::fromArray($data['require'] ?? null, messages: $messages);
			$conflict  = ExtensionRequire::fromArray($data['conflict'] ?? null, 'conflict', $messages);
			$replace  = ExtensionRequire::fromArray($data['replace'] ?? null, 'replace', $messages);
			$provide  = ExtensionRequire::fromArray($data['provide'] ?? null, 'provide', $messages);
			$suggest  = ExtensionSuggest::fromManifest($data['suggest'] ?? null, $messages);
			$keywords = ExtensionKeywords::fromManifest($data['keywords'] ?? null, $messages);
			$catalogs = ExtensionCatalogs::fromManifest($data['catalogs'] ?? null, $messages);
		} catch (ExtensionException $error) {
			throw new ExtensionException((string) (is_string($data['name'] ?? null)
				? $messages->t('plugin.plugin_manifest.invalid_named', 'Plugin manifest for "{name}": {error}', name: $data['name'], error: $error->getMessage())
				: $messages->t('plugin.plugin_manifest.invalid', 'Plugin manifest: {error}', error: $error->getMessage())), previous: $error);
		}

		return new self(
			name: self::string($data, 'name', messages: $messages),
			label: ExtensionName::label(self::string($data, 'label', '', $messages), self::string($data, 'name', messages: $messages)),
			namespace: self::string($data, 'namespace', ExtensionNamespace::fromName(self::string($data, 'name', messages: $messages)), $messages),
			source: $source instanceof PluginSource
				? $source
				: PluginSource::tryFrom(is_string($source) ? $source : '') ?? throw new ExtensionException('Plugin "source" is invalid.'),
			path: self::string($data, 'path', messages: $messages),
			version: self::string($data, 'version', '0.0.0', $messages),
			description: self::string($data, 'description', '', $messages),
			autoload: $autoload,
			require: $require,
			conflict: $conflict,
			replace: $replace,
			provide: $provide,
			authors: $authors,
			license: $license,
			links: $links,
			provider: isset($data['provider']) ? self::string($data, 'provider', messages: $messages) : null,
			abandoned: $abandoned,
			suggest: $suggest,
			keywords: $keywords,
			catalogs: $catalogs,
			messages: $messages
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
			'replace'     => $this->replace,
			'provide'     => $this->provide,
			'authors'     => array_map(static fn (ExtensionAuthor $author): array => $author->toArray(), $this->authors),
			'license'     => $this->license,
			'abandoned'   => $this->abandoned,
			'suggest'     => $this->suggest,
			'keywords'    => $this->keywords,
			'catalogs'    => $this->catalogs,
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
	private static function string(array $data, string $key, ?string $default = null, Messages $messages = new Messages()): string
	{
		$value = $data[$key] ?? $default;

		if (! is_string($value)) {
			throw new ExtensionException((string) (is_string($data['name'] ?? null)
				? $messages->t('plugin.plugin_manifest.not_string_named', 'Plugin manifest "{field}" for "{name}" must be a string.', field: $key, name: $data['name'])
				: $messages->t('plugin.plugin_manifest.not_string', 'Plugin manifest "{field}" must be a string.', field: $key)));
		}

		return $value;
	}
}
