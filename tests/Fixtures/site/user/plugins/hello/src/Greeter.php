<?php

/**
 * Fixture local extension service.
 */

declare(strict_types=1);

namespace Fixture\Hello;

use Blush\Core\AppConfig;

final readonly class Greeter
{
	public function __construct(private AppConfig $config)
	{
	}

	public function greet(): string
	{
		return "Hello from {$this->config->name}";
	}
}
