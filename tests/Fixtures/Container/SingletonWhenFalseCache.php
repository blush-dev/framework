<?php

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Container;

use Blush\Container\Attributes\SingletonWhen;

#[SingletonWhen(false)]
final class SingletonWhenFalseCache implements Cache
{
}
