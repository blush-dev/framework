<?php

/**
 * Recording puller fixture.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Publish;

use Override;
use Blush\Publish\Puller;
use Blush\Publish\PullResult;

final class RecordingPuller implements Puller
{
	/**
	 * @var list<string>
	 */
	public array $pulled = [];

	public function __construct(private readonly PullResult $result = new PullResult(true, 'Already up to date.'))
	{}

	#[Override]
	public function pull(string $directory): PullResult
	{
		$this->pulled[] = $directory;

		return $this->result;
	}
}
