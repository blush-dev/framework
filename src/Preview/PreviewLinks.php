<?php

/**
 * Preview links.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Preview;

use LogicException;
use Psr\Clock\ClockInterface;
use Blush\Content\Entry\Entry;
use Blush\Core\AppConfig;

/**
 * Makes and checks signed preview links (D-226). A link names an entry by
 * id and carries when it expires and an HMAC-SHA256 signature of both,
 * so it can't be changed to show another entry or to last longer:
 *
 *     /_blush/preview?entry={id}&expires={time}&signature={hmac}
 *
 * Anyone with the link can see the entry until it expires; no account is
 * needed, so links can be shared with reviewers.
 */
final readonly class PreviewLinks
{
	public function __construct(
		private PreviewConfig $config,
		private AppConfig $app,
		private ClockInterface $clock
	) {}

	/**
	 * Makes a link to an entry's preview, lasting `$lifetime` seconds (the
	 * configured lifetime by default).
	 *
	 * @throws LogicException When preview links are off (no secret).
	 */
	public function make(Entry $entry, ?int $lifetime = null): PreviewLink
	{
		$expires = $this->clock->now()->getTimestamp() + max(60, $lifetime ?? $this->config->lifetime);
		$query   = http_build_query([
			'entry'     => $entry->path,
			'expires'   => $expires,
			'signature' => $this->signature($entry->path, $expires)
		], '', '&', PHP_QUERY_RFC3986);

		return new PreviewLink($this->app->absoluteUrl("{$this->config->path}?{$query}"), $expires);
	}

	/**
	 * Whether a link's parts are genuine and it hasn't expired.
	 */
	public function isValid(string $entry, int $expires, string $signature): bool
	{
		return $this->config->isEnabled()
			&& $expires >= $this->clock->now()->getTimestamp()
			&& hash_equals($this->signature($entry, $expires), $signature);
	}

	/**
	 * Returns the signature of an entry id and expiry time.
	 *
	 * @throws LogicException When preview links are off (no secret).
	 */
	private function signature(string $entry, int $expires): string
	{
		$secret = $this->config->secret ?? throw new LogicException('Preview links need a secret: set APP_SECRET (bin/blush init adds one).');

		return hash_hmac('sha256', "preview\n{$entry}\n{$expires}", $secret);
	}
}
