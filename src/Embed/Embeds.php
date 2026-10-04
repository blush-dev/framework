<?php

/**
 * Embeds.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Embed;

use JsonException;
use Blush\Cache\CacheException;
use Blush\Cache\CacheNamespace;
use Blush\Cache\Caches;

/**
 * Looks up URLs with their oEmbed providers (D-184). Each answer is kept
 * in the `embeds` store, which publishing and `cache:clear` leave alone
 * (only `cache:clear --embeds` empties it, D-448) and which works even when caching is off, so a provider is asked about
 * a URL once a month, not on every render. A failure (no answer, an error
 * status, or a response that isn't oEmbed) is kept for an hour, and the
 * embed falls back to what its provider can do from the URL alone.
 */
final class Embeds
{
	/**
	 * Answers looked up in this process, by cache key.
	 *
	 * @var array<string, ?EmbedData>
	 */
	private array $seen = [];

	public function __construct(
		private readonly EmbedProviders $providers,
		private readonly Caches $caches,
		private readonly EmbedConfig $config,
		private readonly Fetcher $fetcher
	) {}

	/**
	 * Returns the provider that embeds a URL, or `null`.
	 *
	 * @throws EmbedException When a registered provider can't be built.
	 */
	public function provider(string $url): ?EmbedProvider
	{
		return $this->providers->forUrl($url);
	}

	/**
	 * Returns what a provider says about a URL, or `null` when fetching
	 * is off or it said nothing usable.
	 */
	public function lookup(EmbedProvider $provider, string $url): ?EmbedData
	{
		if (! $this->config->fetch) {
			return null;
		}

		$key = hash('xxh128', "{$provider->name} {$url}");

		if (array_key_exists($key, $this->seen)) {
			return $this->seen[$key];
		}

		try {
			$store  = $this->caches->persistent(CacheNamespace::Embeds);
			$cached = $store->get($key);
		} catch (CacheException) {
			$store  = null;
			$cached = null;
		}

		if (is_array($cached)) {
			return $this->seen[$key] = is_array($cached['response'] ?? null) ? EmbedData::fromResponse($cached['response']) : null;
		}

		$data = $this->fetch($provider, $url);

		try {
			$store?->set($key, ['response' => $data?->toResponse()], $data === null ? $this->config->failureTtl : $this->config->ttl);
		} catch (CacheException) {
			// Unkept; the next render asks again.
		}

		return $this->seen[$key] = $data;
	}

	/**
	 * Asks the provider about a URL.
	 */
	private function fetch(EmbedProvider $provider, string $url): ?EmbedData
	{
		$body = $this->fetcher->get($provider->request($url), $this->config->timeout);

		if ($body === null) {
			return null;
		}

		try {
			$response = json_decode($body, true, 16, JSON_THROW_ON_ERROR);
		} catch (JsonException) {
			return null;
		}

		return is_array($response) ? EmbedData::fromResponse($response) : null;
	}
}
