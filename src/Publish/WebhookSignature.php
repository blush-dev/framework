<?php

/**
 * Webhook signature.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Publish;

/**
 * The publish webhook's signature scheme: an HMAC-SHA256, keyed by
 * `PublishConfig::$secret`, of the Unix timestamp, a `.`, and the raw
 * request body. The request carries both:
 *
 *     X-Publish-Timestamp: 1790000000
 *     X-Publish-Signature: sha256=5d41402abc4b2a76b9719d911017c592…
 *
 * A request outside `PublishConfig::$tolerance` seconds of the server's
 * clock is refused, which bounds how long a captured request could be
 * replayed; the controller also refuses any signature it has seen.
 *
 * From a shell:
 *
 *     ts=$(date +%s); body='{}'
 *     sig=$(printf '%s.%s' "$ts" "$body" | openssl dgst -sha256 -hmac "$PUBLISH_SECRET" -r | cut -d' ' -f1)
 *     curl -X POST -H "X-Publish-Timestamp: $ts" -H "X-Publish-Signature: sha256=$sig" -d "$body" https://example.com/_blush/publish
 */
final readonly class WebhookSignature
{
	public const string TIMESTAMP_HEADER = 'X-Publish-Timestamp';

	public const string SIGNATURE_HEADER = 'X-Publish-Signature';

	public function __construct(
		private string $secret,
		private int $tolerance = 300
	) {}

	/**
	 * Returns the signature header's value for a timestamp and body.
	 */
	public function sign(int $timestamp, string $body): string
	{
		return 'sha256=' . hash_hmac('sha256', "{$timestamp}.{$body}", $this->secret);
	}

	/**
	 * Returns whether a request's timestamp and signature headers are
	 * valid for its body at a time.
	 */
	public function verify(string $timestamp, string $signature, string $body, int $now): bool
	{
		if (preg_match('/^\d{1,12}$/', $timestamp) !== 1 || abs($now - (int) $timestamp) > $this->tolerance) {
			return false;
		}

		return hash_equals($this->sign((int) $timestamp, $body), trim($signature));
	}
}
