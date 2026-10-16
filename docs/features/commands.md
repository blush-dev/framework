# Commands

A plugin adds its own `bin/blush` commands. A command is a class with a
`#[Command]` attribute and an `__invoke()` method:

```php
<?php

declare(strict_types=1);

namespace Acme\Hello\Console;

use Blush\Console\Attributes\Command;
use Blush\Console\Attributes\Option;
use Blush\Console\ExitCode;
use Blush\Console\Output;
use Blush\Content\Entries;

#[Command('stats', 'Count the published posts.')]
final readonly class Stats
{
	public function __construct(private Entries $content) {}

	public function __invoke(
		Output $output,
		#[Option('The type to count.')] string $type = 'post'
	): ExitCode {
		$count = $this->content->query()->type($type)->count();

		$output->success("{$count} published entries of type {$type}.");

		return ExitCode::Success;
	}
}
```

Tag it with `CommandRegistry::TAG` in your plugin's
[provider](../plugins/service-providers.md):

```php
use Blush\Console\CommandRegistry;

protected const array TAGS = [
	CommandRegistry::TAG => [Console\Stats::class]
];
```

Then run `bin/blush stats --type=page`. Options and arguments come from
the method's parameters, so `bin/blush help stats` is written for you.
