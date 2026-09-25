<?php

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Container;

use Blush\Container\Attributes\SingletonWhen;

#[SingletonWhen(true)]
final class SingletonWhenTrueCache implements Cache
{
}
