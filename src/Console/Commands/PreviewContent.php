<?php

/**
 * Content preview command.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Console\Commands;

use DateTimeImmutable;
use Blush\Console\Attributes\Argument;
use Blush\Console\Attributes\Command;
use Blush\Console\Attributes\Option;
use Blush\Console\ExitCode;
use Blush\Console\InvalidInput;
use Blush\Console\Output;
use Blush\Console\Verbosity;
use Blush\Content\ContentRepository;
use Blush\Core\AppConfig;
use Blush\Core\Framework;
use Blush\Preview\PreviewConfig;
use Blush\Preview\PreviewLinks;

/**
 * Prints a signed preview link to an entry, whatever its status (D-226),
 * for sharing a draft without the admin.
 */
#[Command('content:preview', 'Print a signed preview link to an entry.')]
final readonly class PreviewContent
{
	public function __construct(
		private ContentRepository $content,
		private PreviewLinks $links,
		private PreviewConfig $config,
		private AppConfig $app
	) {}

	/**
	 * @throws InvalidInput
	 */
	public function __invoke(
		Output $output,
		#[Argument('The entry\'s content type, such as "post".')] string $type,
		#[Argument('The entry\'s name (its slug, or its path for pages).')] string $name,
		#[Option('How many hours the link works; defaults to the configured lifetime.')] ?int $hours = null
	): ExitCode {
		if (! $this->config->isEnabled()) {
			$output->error(sprintf('Preview links are off: the site has no APP_SECRET. Run "%s init" to add one.', Framework::BINARY));

			return ExitCode::Failure;
		}

		if ($hours !== null && $hours < 1) {
			throw new InvalidInput('--hours must be at least 1.');
		}

		$entry = $this->content->named($type, $name);

		if ($entry === null) {
			$output->error(sprintf('There\'s no "%s" entry named "%s".', $type, $name));

			return ExitCode::Failure;
		}

		$link = $this->links->make($entry, $hours === null ? null : $hours * 3600);

		$output->line($link->url, Verbosity::Quiet);
		$output->comment(sprintf(
			'Works until %s (%s).',
			DateTimeImmutable::createFromTimestamp($link->expires)->setTimezone($this->app->timezone())->format('Y-m-d H:i T'),
			$entry->status->value
		));

		return ExitCode::Success;
	}
}
