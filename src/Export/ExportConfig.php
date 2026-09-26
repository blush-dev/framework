<?php

/**
 * Export config.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Export;

use Override;
use Uri\Rfc3986\Uri;
use Blush\Config\Config;
use Blush\Config\ConfigValues;
use Blush\Config\InvalidConfig;
use Blush\Export\Host\HostFormat;

/**
 * Static export settings, from `config/export.php`:
 *
 *     return new ExportConfig(url: 'https://example.com', exclude: ['/drafts/*']);
 *
 * - `url` is the origin the exported site is served from, when it isn't
 *   `AppConfig::$url` (a mirror or a staging host). Absolute URLs in the
 *   output use it. `build --base-url` overrides it. Only an origin works
 *   (no path), since sites are served from the root of their host.
 * - `crawl` follows the links on exported HTML pages to find URLs the
 *   sources don't list, such as an extension's pages.
 * - `paths` are more URL paths to export.
 * - `exclude` are path patterns (`fnmatch()` globs, such as `/drafts/*`)
 *   never exported.
 * - `hosts` are the host file formats written with the export (D-140):
 *   `apache` (`.htaccess`) and `netlify` (`_redirects` and `_headers`,
 *   which Cloudflare Pages reads too), or an extension's.
 * - `redirectPages` writes a page that redirects in the browser at each
 *   redirected path, for hosts that read neither format.
 *
 * The output folder is `Paths::$export` (`storage/export` by default).
 */
final readonly class ExportConfig implements Config
{
	/**
	 * @param list<string> $paths
	 * @param list<string> $exclude
	 * @param list<string> $hosts
	 * @throws InvalidConfig
	 */
	public function __construct(
		public ?string $url = null,
		public bool $crawl = true,
		public array $paths = [],
		public array $exclude = [],
		public array $hosts = [HostFormat::Apache->value, HostFormat::Netlify->value],
		public bool $redirectPages = true
	) {
		if ($url !== null && ! self::isOrigin($url)) {
			throw new InvalidConfig(sprintf('ExportConfig "url" must be an http(s) origin, such as "https://example.com"; "%s" given.', $url));
		}

		foreach ([...$paths, ...$exclude] as $path) {
			if (! str_starts_with($path, '/')) {
				throw new InvalidConfig(sprintf('ExportConfig "paths" and "exclude" must start with "/"; "%s" given.', $path));
			}
		}

		foreach ($hosts as $host) {
			if (preg_match('/^[a-z0-9][a-z0-9_.-]*$/', $host) !== 1) {
				throw new InvalidConfig(sprintf('ExportConfig "hosts" must be host format names, such as "apache"; "%s" given.', $host));
			}
		}
	}

	/**
	 * Returns whether a URL is an http(s) origin: a scheme and host, and
	 * at most a `/` for a path.
	 */
	public static function isOrigin(string $url): bool
	{
		$uri = Uri::parse($url);

		return $uri !== null
			&& in_array(strtolower((string) $uri->getScheme()), ['http', 'https'], true)
			&& ($uri->getHost() ?? '') !== ''
			&& in_array($uri->getPath(), ['', '/'], true)
			&& $uri->getQuery() === null
			&& $uri->getFragment() === null
			&& $uri->getUserInfo() === null;
	}

	/**
	 * Returns whether a path is excluded.
	 */
	public function excludes(string $path): bool
	{
		return array_any($this->exclude, static fn (string $pattern): bool => fnmatch($pattern, $path));
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function fromArray(array $data): static
	{
		$values = new ConfigValues($data, self::class);
		$values->assertKnownKeys(['url', 'crawl', 'paths', 'exclude', 'hosts', 'redirectPages']);

		return new static(
			url: $values->nullableString('url'),
			crawl: $values->bool('crawl', true),
			paths: $values->stringList('paths'),
			exclude: $values->stringList('exclude'),
			hosts: $values->stringList('hosts', [HostFormat::Apache->value, HostFormat::Netlify->value]),
			redirectPages: $values->bool('redirectPages', true)
		);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function toArray(): array
	{
		return [
			'url'           => $this->url,
			'crawl'         => $this->crawl,
			'paths'         => $this->paths,
			'exclude'       => $this->exclude,
			'hosts'         => $this->hosts,
			'redirectPages' => $this->redirectPages
		];
	}
}
