<?php

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Container\Plan;

use Blush\Container\Attributes\SingletonWhen;

/**
 * A class whose `#[SingletonWhen]` condition is a closure (legal in attribute
 * arguments as of PHP 8.5). Its plan can't be exported.
 */
#[SingletonWhen(static function (): bool {
	return true;
})]
final class ClosureCondition
{
}
