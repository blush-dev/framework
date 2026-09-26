<?php

/**
 * Plain host files fixture.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Export;

use Override;
use Blush\Export\Host\HostContext;
use Blush\Export\Host\HostFiles;
use Blush\Export\Host\HostOutput;

final class PlainHostFiles extends HostFiles
{
	#[Override]
	public function files(HostContext $context): HostOutput
	{
		return new HostOutput(['hosting.txt' => sprintf("redirects: %d\n", count($context->redirects))]);
	}
}
