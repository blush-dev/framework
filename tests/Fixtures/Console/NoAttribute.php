<?php

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Console;

use Blush\Console\ExitCode;

final readonly class NoAttribute
{
	public function __invoke(): ExitCode
	{
		return ExitCode::Success;
	}
}
