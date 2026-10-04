<?php

/**
 * Webhook controller.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Publish;

use JsonException;
use Psr\Clock\ClockInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Blush\Cache\CacheException;
use Blush\Cache\CacheNamespace;
use Blush\Cache\Caches;
use Blush\Http\ClientIp;
use Blush\Http\Response;
use Blush\Http\Status;

/**
 * `POST {PublishConfig::$path}`: publishes on a signed request
 * (`WebhookSignature`), so a git host, a CI job, or a script run after an
 * SFTP upload can put content live without shell access (D-040). The
 * body may be `{"pull": false}` or `{"pull": true}` to override
 * `PublishConfig::$git`; anything else in it is ignored.
 *
 * Answers are JSON and never cached: 200 with the publish report, 500
 * when the pull failed, 401 for a bad or stale signature, 409 for a
 * replayed request or while another publish runs, 400 for a body
 * that isn't a JSON object, and 429 while the address is locked out for
 * too many failed signatures (`WebhookThrottle`), checked before the
 * signature is.
 */
final readonly class WebhookController
{
	public function __construct(
		private PublishConfig $config,
		private Publisher $publisher,
		private Caches $caches,
		private WebhookThrottle $throttle,
		private ClockInterface $clock
	) {}

	/**
	 * @throws CacheException
	 */
	public function __invoke(ServerRequestInterface $request): ResponseInterface
	{
		$body      = (string) $request->getBody();
		$signature = $request->getHeaderLine(WebhookSignature::SIGNATURE_HEADER);
		$now       = $this->clock->now()->getTimestamp();
		$verifier  = new WebhookSignature((string) $this->config->secret, $this->config->tolerance);
		$ip        = ClientIp::of($request);

		if ($this->throttle->isLockedOut($ip)) {
			return self::json(['error' => 'Too many failed requests. Try again later.'], Status::TooManyRequests, ['Retry-After' => (string) $this->config->lockout]);
		}

		if ($this->config->secret === null || ! $verifier->verify($request->getHeaderLine(WebhookSignature::TIMESTAMP_HEADER), $signature, $body, $now)) {
			$this->throttle->fail($ip);

			return self::json(['error' => 'The request signature is missing, invalid, or expired.'], Status::Unauthorized);
		}

		$seen = $this->caches->persistent(CacheNamespace::Webhooks);
		$key  = hash('sha256', $signature);

		if ($seen->has($key)) {
			return self::json(['error' => 'This request was already received.'], Status::Conflict);
		}

		$seen->set($key, $now, $this->config->tolerance * 2);
		$this->throttle->clear($ip);

		try {
			$options = trim($body) === '' ? [] : json_decode($body, true, 4, JSON_THROW_ON_ERROR);
		} catch (JsonException) {
			$options = null;
		}

		if (! is_array($options)) {
			return self::json(['error' => 'The request body must be empty or a JSON object.'], Status::BadRequest);
		}

		try {
			$report = $this->publisher->publish(is_bool($options['pull'] ?? null) ? $options['pull'] : null);
		} catch (PublishInProgress $e) {
			return self::json(['error' => $e->getMessage()], Status::Conflict);
		}

		return self::json($report->toArray(), $report->isPublished() ? Status::Ok : Status::InternalServerError);
	}

	/**
	 * Returns an uncached JSON response.
	 *
	 * @param array<string, mixed>  $data
	 * @param array<string, string> $headers
	 */
	private static function json(array $data, Status $status, array $headers = []): ResponseInterface
	{
		return Response::json($data, $status, ['Cache-Control' => 'no-store', ...$headers]);
	}
}
