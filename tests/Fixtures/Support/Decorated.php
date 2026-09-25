<?php

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Support;

#[Marker('class')]
#[Marker('again')]
final class Decorated
{
	#[Marker('property')]
	public string $value = '';

	#[Marker('method')]
	public function run(#[Marker('parameter')] string $input): string
	{
		return $input;
	}
}
