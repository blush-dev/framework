<?php

/**
 * Recording SAPI fixture.
 */

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Http;

use Override;
use Blush\Http\Sapi;

final class RecordingSapi implements Sapi
{
	/**
	 * @var list<array{line: string, replace: bool, status: int}>
	 */
	public array $headers = [];

	public string $body = '';

	public bool $finished = false;

	public function __construct(public bool $sent = false)
	{
	}

	#[Override]
	public function headersSent(): bool
	{
		return $this->sent;
	}

	#[Override]
	public function header(string $line, bool $replace = true, int $status = 0): void
	{
		$this->headers[] = ['line' => $line, 'replace' => $replace, 'status' => $status];
	}

	#[Override]
	public function write(string $data): void
	{
		$this->body .= $data;
	}

	#[Override]
	public function finish(): void
	{
		$this->finished = true;
	}
}
