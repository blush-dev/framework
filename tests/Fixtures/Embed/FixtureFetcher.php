<?php

/**
 * Fixture fetcher.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Embed;

use Override;
use Blush\Embed\Fetcher;

final class FixtureFetcher implements Fetcher
{
	/**
	 * @var list<string>
	 */
	public array $requests = [];

	/**
	 * @param array<string, string> $responses Bodies, by the start of the request URL.
	 */
	public function __construct(private readonly array $responses = [])
	{}

	#[Override]
	public function get(string $url, int $timeout): ?string
	{
		$this->requests[] = $url;

		foreach ($this->responses as $prefix => $body) {
			if (str_starts_with($url, $prefix)) {
				return $body;
			}
		}

		return null;
	}
}
