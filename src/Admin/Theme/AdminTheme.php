<?php

/**
 * Admin theme.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin\Theme;

use Blush\Extension\ExtensionAbandoned;
use Blush\Extension\ExtensionAuthor;
use Blush\Extension\ExtensionException;
use Blush\Extension\ExtensionKeywords;
use Blush\Extension\ExtensionKind;
use Blush\Extension\ExtensionLicense;
use Blush\Extension\ExtensionLinks;
use Blush\Extension\ExtensionManifest;
use Blush\Extension\ExtensionName;
use Blush\Extension\ExtensionRequire;
use Blush\Extension\ExtensionSuggest;

/**
 * An admin theme (D-827): a skin over the admin, never its layout or
 * behavior. It ships data, not code or CSS: its `admin-theme.json` gives
 * its name (`vendor/name`), label (its name without one, D-423), and
 * optionally a version and a description, and its `tokens.json` the
 * tokens it sets (`AdminTokens`), from which Blush writes its
 * stylesheet. It has no namespace, having no blocks, components, icons,
 * or text of its own.
 *
 *     {
 *         "name": "acme/harbor",
 *         "label": "Harbor",
 *         "description": "Sea blues and a serif for titles."
 *     }
 *
 * Its `authors`, `license`, links, `abandoned`, `suggest`, `keywords`,
 * and `require`, `conflict`, `replace`, and `provide` are an icon pack's
 * (D-385, D-431 to D-439): a theme that's on but whose requirements
 * aren't met isn't offered.
 */
final readonly class AdminTheme implements ExtensionManifest
{
	/**
	 * The file its stylesheet is served as, beside its fonts.
	 */
	public const string STYLESHEET = 'theme.css';

	/**
	 * @param list<ExtensionAuthor> $authors
	 * @param array<string, string> $require
	 * @param array<string, string> $conflict
	 * @param array<string, string> $replace
	 * @param array<string, string> $provide
	 * @param array<string, string> $suggest
	 * @param list<string>          $keywords
	 * @throws ExtensionException
	 */
	public function __construct(
		public string $name,
		public string $label,
		public string $path,
		public AdminTokens $tokens = new AdminTokens(),
		public AdminThemeSource $source = AdminThemeSource::Local,
		public string $version = '',
		public string $description = '',
		public array $authors = [],
		public string $license = '',
		public ExtensionLinks $links = new ExtensionLinks(),
		public array $require = [],
		public array $conflict = [],
		public array $replace = [],
		public array $provide = [],
		public bool|string $abandoned = false,
		public array $suggest = [],
		public array $keywords = []
	) {
		if (! ExtensionName::isValid($name)) {
			throw new ExtensionException(sprintf('The admin theme in %s needs a "name": vendor/name, such as "acme/harbor".', $path));
		}

		if (trim($label) === '') {
			throw new ExtensionException(sprintf('The "%s" admin theme needs a "label".', $name));
		}
	}

	/**
	 * Builds a theme from its manifest's data, found in a folder, reading
	 * its `tokens.json` there (or the tokens the cache kept).
	 *
	 * @param  array<array-key, mixed>  $data
	 * @param  ?array<array-key, mixed> $tokens What `AdminTokens::toArray()` gave, from the cache.
	 * @throws ExtensionException
	 */
	public static function fromArray(string $path, array $data, AdminThemeSource $source = AdminThemeSource::Local, ?array $tokens = null): self
	{
		foreach (['name', 'label', 'version', 'description'] as $key) {
			if (isset($data[$key]) && ! is_string($data[$key])) {
				throw new ExtensionException(sprintf('The admin theme in %s has a "%s" that isn\'t a string.', $path, $key));
			}
		}

		if (array_key_exists('namespace', $data)) {
			throw new ExtensionException(sprintf('The admin theme in %s has a "namespace", which admin themes don\'t: it has nothing to name by one.', $path));
		}

		try {
			$authors   = ExtensionAuthor::list($data['authors'] ?? []);
			$license   = ExtensionLicense::fromManifest($data['license'] ?? '');
			$links     = ExtensionLinks::fromArray($data);
			$require   = ExtensionRequire::fromArray($data['require'] ?? null);
			$conflict  = ExtensionRequire::fromArray($data['conflict'] ?? null, 'conflict');
			$replace   = ExtensionRequire::fromArray($data['replace'] ?? null, 'replace');
			$provide   = ExtensionRequire::fromArray($data['provide'] ?? null, 'provide');
			$abandoned = ExtensionAbandoned::fromManifest($data['abandoned'] ?? false);
			$suggest   = ExtensionSuggest::fromManifest($data['suggest'] ?? null);
			$keywords  = ExtensionKeywords::fromManifest($data['keywords'] ?? null);
			$read      = $tokens === null ? AdminTokens::load($path) : AdminTokens::fromCache($tokens, $path);
		} catch (ExtensionException $error) {
			throw new ExtensionException(sprintf('The admin theme in %s: %s', $path, $error->getMessage()), previous: $error);
		}

		/** @var array<string, string> $data Checked above. */
		return new self(
			name: $data['name'] ?? '',
			label: ExtensionName::label($data['label'] ?? null, $data['name'] ?? ''),
			path: $path,
			tokens: $read,
			source: $source,
			version: $data['version'] ?? '',
			description: $data['description'] ?? '',
			authors: $authors,
			license: $license,
			links: $links,
			require: $require,
			conflict: $conflict,
			replace: $replace,
			provide: $provide,
			abandoned: $abandoned,
			suggest: $suggest,
			keywords: $keywords
		);
	}

	/**
	 * Returns what the cache stores, as `fromArray()` (with the path,
	 * source, and tokens) reads it back.
	 *
	 * @return array{path: string, source: string, data: array<string, mixed>, tokens: array<string, mixed>}
	 */
	public function toArray(): array
	{
		return [
			'path'   => $this->path,
			'source' => $this->source->value,
			'data'   => [
				'name'        => $this->name,
				'label'       => $this->label,
				'version'     => $this->version,
				'description' => $this->description,
				'authors'     => array_map(static fn (ExtensionAuthor $author): array => $author->toArray(), $this->authors),
				'license'     => $this->license,
				'require'     => $this->require,
				'conflict'    => $this->conflict,
				'replace'     => $this->replace,
				'provide'     => $this->provide,
				'abandoned'   => $this->abandoned,
				'suggest'     => $this->suggest,
				'keywords'    => $this->keywords,
				...$this->links->toArray()
			],
			'tokens' => $this->tokens->toArray()
		];
	}

	public function kind(): ExtensionKind
	{
		return ExtensionKind::AdminTheme;
	}

	/**
	 * Returns its stylesheet: its font faces, and its tokens under
	 * `:root[data-admin-theme="{name}"]`, which outweighs the admin's own
	 * `:root` wherever the two load.
	 */
	public function stylesheet(): string
	{
		$css = $this->tokens->css(sprintf(':root[data-admin-theme="%s"]', $this->name));

		return sprintf("/* %s (%s), written from its %s. */\n\n%s", $this->label, $this->name, AdminTokens::FILE, $css);
	}

	/**
	 * Returns a font file it ships, by its path in the theme, or `null`
	 * when it ships none there.
	 */
	public function font(string $src): ?string
	{
		$face = array_find($this->tokens->faces, static fn (FontFace $face): bool => $face->src === $src);

		return $face === null ? null : "{$this->path}/{$face->src}";
	}
}
