<?php

/**
 * Icon pack.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Icon;

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
 * An icon pack (D-378): SVG icons in its own namespace, with no code. Its
 * `icons.json` (or `.yaml`) gives its name (`vendor/name`), label (its
 * name without one, D-423), namespace (its name, hyphenated, without
 * one, D-424), and optionally a version, a description, and the `folder`
 * inside the pack its `*.svg` files are in (the pack's own folder by
 * default):
 *
 *     {
 *         "name": "acme/brands",
 *         "label": "Brand Logos",
 *         "namespace": "brands",
 *         "folder": "svg"
 *     }
 *
 * Each `{icon}.svg` is `{namespace}/{icon}` (`brands/github`), its label
 * from the pack's `lang/` catalog (`icons.{icon}.label`). Its `authors`
 * are in D-384's shape, its `license` a string or a list (D-427, D-428), and its
 * `homepage`, `support`, and `funding` (D-428), each from its
 * `composer.json` when the manifest has none (D-385). Its `abandoned`
 * (`true`, or the package to use instead) only warns (D-433), and its
 * `suggest` is only shown (D-434). Its `require` and `conflict` (D-435) are
 * checked as a plugin's is (D-431): a pack that's on but whose
 * requirements aren't met doesn't load.
 */
final readonly class IconPack implements ExtensionManifest
{
	/**
	 * @param string $path   The pack's absolute folder.
	 * @param string $folder The folder its SVGs are in, relative to `$path` (`''` for the pack's own).
	 * @param list<ExtensionAuthor> $authors Who made it.
	 * @param array<string, string> $require What it needs, each mapped to a version constraint (D-431).
	 * @param array<string, string> $conflict What it can't run with, each mapped to the versions it can't (D-435).
	 * @param bool|string           $abandoned Whether it's abandoned, or the package to use instead (D-433).
	 * @param array<string, string> $suggest   Package => why it's suggested (D-434).
	 * @throws ExtensionException
	 */
	public function __construct(
		public string $name,
		public string $label,
		public string $namespace,
		public string $path,
		public IconPackSource $source = IconPackSource::Local,
		public string $version = '',
		public string $description = '',
		public string $folder = '',
		public array $authors = [],
		public string $license = '',
		public ExtensionLinks $links = new ExtensionLinks(),
		public array $require = [],
		public array $conflict = [],
		public bool|string $abandoned = false,
		public array $suggest = []
	) {
		if (! ExtensionName::isValid($name)) {
			throw new ExtensionException(sprintf('The icon pack in %s needs a "name": vendor/name, such as "acme/brands".', $path));
		}

		if (trim($label) === '') {
			throw new ExtensionException(sprintf('The "%s" icon pack needs a "label".', $name));
		}

		if (! ExtensionNamespace::isValid($namespace) || ExtensionNamespace::isReserved($namespace)) {
			throw new ExtensionException(sprintf(
				'The "%s" icon pack needs a "namespace": lowercase letters, digits, hyphens, and underscores, and not %s.',
				$name,
				implode(', ', ExtensionNamespace::RESERVED)
			));
		}

		if ($folder !== '' && (str_starts_with($folder, '/') || in_array('..', explode('/', $folder), true))) {
			throw new ExtensionException(sprintf('The "%s" icon pack\'s "folder" must be a folder inside the pack.', $name));
		}
	}

	/**
	 * Builds a pack from its manifest's data, found in a folder.
	 *
	 * @param  array<array-key, mixed> $data
	 * @throws ExtensionException
	 */
	public static function fromArray(string $path, array $data, IconPackSource $source = IconPackSource::Local): self
	{
		foreach (['name', 'label', 'namespace', 'version', 'description', 'folder'] as $key) {
			if (isset($data[$key]) && ! is_string($data[$key])) {
				throw new ExtensionException(sprintf('The icon pack in %s has a "%s" that isn\'t a string.', $path, $key));
			}
		}

		try {
			$authors = ExtensionAuthor::list($data['authors'] ?? []);
			$license = ExtensionLicense::fromManifest($data['license'] ?? '');
			$links   = ExtensionLinks::fromArray($data);
			$require = ExtensionRequire::fromArray($data['require'] ?? null);
			$conflict = ExtensionRequire::fromArray($data['conflict'] ?? null, 'conflict');
			$abandoned = ExtensionAbandoned::fromManifest($data['abandoned'] ?? false);
			$suggest   = ExtensionSuggest::fromManifest($data['suggest'] ?? null);
		} catch (ExtensionException $error) {
			throw new ExtensionException(sprintf('The icon pack in %s: %s', $path, $error->getMessage()), previous: $error);
		}

		/** @var array<string, string> $data Checked above. */
		return new self(
			name: $data['name'] ?? '',
			label: ExtensionName::label($data['label'] ?? null, $data['name'] ?? ''),
			namespace: $data['namespace'] ?? ExtensionNamespace::fromName($data['name'] ?? ''),
			path: $path,
			source: $source,
			version: $data['version'] ?? '',
			description: $data['description'] ?? '',
			folder: trim($data['folder'] ?? '', '/'),
			authors: $authors,
			license: $license,
			links: $links,
			require: $require,
			conflict: $conflict,
			abandoned: $abandoned,
			suggest: $suggest
		);
	}

	/**
	 * Returns what the cache stores, as `fromArray()` (with the path and
	 * source) reads it back.
	 *
	 * @return array{path: string, source: string, data: array<string, mixed>}
	 */
	public function toArray(): array
	{
		return [
			'path'   => $this->path,
			'source' => $this->source->value,
			'data'   => [
				'name'        => $this->name,
				'label'       => $this->label,
				'namespace'   => $this->namespace,
				'version'     => $this->version,
				'description' => $this->description,
				'folder'      => $this->folder,
				'authors'     => array_map(static fn (ExtensionAuthor $author): array => $author->toArray(), $this->authors),
				'license'     => $this->license,
				'require'     => $this->require,
				'conflict'    => $this->conflict,
				'abandoned'   => $this->abandoned,
				'suggest'     => $this->suggest,
				...$this->links->toArray()
			]
		];
	}

	public function kind(): ExtensionKind
	{
		return ExtensionKind::IconPack;
	}

	/**
	 * Returns the folder the pack's SVGs are in.
	 */
	public function iconsPath(): string
	{
		return $this->folder === '' ? $this->path : "{$this->path}/{$this->folder}";
	}

	/**
	 * Returns the pack's message catalog folder.
	 */
	public function langPath(): string
	{
		return "{$this->path}/lang";
	}
}
