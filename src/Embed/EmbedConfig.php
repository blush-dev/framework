<?php

/**
 * Embed config.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Embed;

use Override;
use Blush\Config\Config;
use Blush\Config\ConfigValues;
use Blush\Config\InvalidConfig;

/**
 * The site's embed settings, from `config/embed.php` (D-184):
 *
 *     return new EmbedConfig(providers: [
 *         new OEmbedProvider('streamable', 'Streamable', ['https://streamable.com/*'], 'https://api.streamable.com/oembed.json')
 *     ]);
 *
 * A provider can also be an array with `name`, `label` (optional),
 * `schemes`, and `endpoint`.
 *
 * - `providers` adds oEmbed providers (YouTube, Vimeo, TED, and
 *   CodePen are built in, D-633); one
 *   named like a built-in replaces it.
 * - `off` names the providers turned off, built in or not, whose links
 *   are links (D-633); Settings → Writing saves its own over it.
 * - `fetch: false` never asks providers anything; built-in providers
 *   still embed from the URL alone, at 16:9.
 * - `timeout` is how many seconds a request may take; `ttl` how long an
 *   answer is kept (30 days), and `failureTtl` how long a failure is (an
 *   hour), so a provider that's down isn't asked on every page.
 */
final readonly class EmbedConfig implements Config
{
	/**
	 * The configured providers.
	 *
	 * @var list<OEmbedProvider>
	 */
	public array $providers;

	/**
	 * @param  array<array-key, mixed> $providers `OEmbedProvider` objects, or arrays with
	 *         `name`, `label`, `schemes`, and `endpoint`.
	 * @param  list<string>            $off       Names of providers turned off.
	 * @throws InvalidConfig
	 */
	public function __construct(
		array $providers = [],
		public array $off = [],
		public bool $fetch = true,
		public int $timeout = 3,
		public int $ttl = 2_592_000,
		public int $failureTtl = 3_600
	) {
		if ($timeout < 1 || $ttl < 1 || $failureTtl < 1) {
			throw new InvalidConfig('EmbedConfig "timeout", "ttl", and "failureTtl" must be at least 1 second.');
		}

		foreach ($off as $name) {
			if (! self::isName($name)) {
				throw new InvalidConfig(sprintf('EmbedConfig "off" names providers, such as "codepen"; "%s" isn\'t a provider\'s name.', $name));
			}
		}

		$this->providers = array_values(array_map(self::provider(...), $providers));
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function fromArray(array $data): static
	{
		$values = new ConfigValues($data, self::class);
		$values->assertKnownKeys(['providers', 'off', 'fetch', 'timeout', 'ttl', 'failureTtl']);

		return new static(
			providers: is_array($data['providers'] ?? null) ? $data['providers'] : [],
			off: $values->stringList('off'),
			fetch: $values->bool('fetch', true),
			timeout: $values->int('timeout', 3),
			ttl: $values->int('ttl', 2_592_000),
			failureTtl: $values->int('failureTtl', 3_600)
		);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function toArray(): array
	{
		return [
			'providers'  => array_map(static fn (OEmbedProvider $provider): array => $provider->toArray(), $this->providers),
			'off'        => $this->off,
			'fetch'      => $this->fetch,
			'timeout'    => $this->timeout,
			'ttl'        => $this->ttl,
			'failureTtl' => $this->failureTtl
		];
	}

	/**
	 * Builds a provider from its config data.
	 *
	 * @throws InvalidConfig
	 */
	/**
	 * Returns whether a provider is on (not named in `off`).
	 */
	public function isOn(string $name): bool
	{
		return ! in_array($name, $this->off, true);
	}

	/**
	 * Returns whether a string is a provider's name: lowercase letters,
	 * digits, `-`, and `_`, starting with a letter or digit.
	 */
	public static function isName(string $name): bool
	{
		return preg_match('/^[a-z0-9][a-z0-9_-]*$/', $name) === 1;
	}

	private static function provider(mixed $provider): OEmbedProvider
	{
		if ($provider instanceof OEmbedProvider) {
			return $provider;
		}

		if (! is_array($provider)) {
			throw new InvalidConfig('EmbedConfig "providers" must be OEmbedProvider objects or arrays.');
		}

		$values = new ConfigValues($provider, self::class);
		$name   = $values->string('name', '');

		try {
			return new OEmbedProvider($name, $values->string('label', ucfirst($name)), $values->stringList('schemes'), $values->string('endpoint', ''));
		} catch (EmbedException $error) {
			throw new InvalidConfig($error->getMessage(), 0, $error);
		}
	}
}
