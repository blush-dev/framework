<?php

/**
 * Response emitter.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Http;

use Psr\Http\Message\ResponseInterface;

/**
 * Sends a response to the client: the status line, the headers, and the
 * body in chunks. The body is left out for `HEAD` requests and for
 * statuses that never have one (1xx, 204, 304). When the SAPI supports
 * it, the request is finished afterward so deferred work can run without
 * holding the client.
 */
final readonly class Emitter
{
	public function __construct(
		private Sapi $sapi,
		private int $chunkSize = 8192
	) {
	}

	/**
	 * Emits the response. Pass `$withBody: false` for `HEAD` requests.
	 *
	 * @throws EmitterException When output has already started.
	 */
	public function emit(ResponseInterface $response, bool $withBody = true): void
	{
		if ($this->sapi->headersSent()) {
			throw new EmitterException('Unable to emit the response: headers have already been sent.');
		}

		$this->emitStatus($response);
		$this->emitHeaders($response);

		if ($withBody && ! (Status::tryFrom($response->getStatusCode())?->isEmpty() ?? false)) {
			$this->emitBody($response);
		}

		$this->sapi->finish();
	}

	/**
	 * Sends the status line.
	 */
	private function emitStatus(ResponseInterface $response): void
	{
		$status = $response->getStatusCode();
		$reason = $response->getReasonPhrase();

		$this->sapi->header(
			rtrim(sprintf('HTTP/%s %d %s', $response->getProtocolVersion(), $status, $reason)),
			true,
			$status
		);
	}

	/**
	 * Sends each header. Repeated values are sent as separate lines, which
	 * `Set-Cookie` requires.
	 */
	private function emitHeaders(ResponseInterface $response): void
	{
		foreach ($response->getHeaders() as $name => $values) {
			$name = (string) $name;

			foreach ($values as $index => $value) {
				$this->sapi->header("{$name}: {$value}", $index === 0);
			}
		}
	}

	/**
	 * Streams the body in chunks.
	 */
	private function emitBody(ResponseInterface $response): void
	{
		$body = $response->getBody();

		if ($body->isSeekable()) {
			$body->rewind();
		}

		if (! $body->isReadable()) {
			$this->sapi->write((string) $body);
			return;
		}

		while (! $body->eof()) {
			$chunk = $body->read($this->chunkSize);

			if ($chunk === '') {
				break;
			}

			$this->sapi->write($chunk);
		}
	}
}
