<?php

/**
 * CommonMark parser.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Markdown;

use Override;
use Throwable;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\ExtensionInterface;
use League\CommonMark\MarkdownConverter;
use League\CommonMark\Parser\Inline\InlineParserInterface;
use Blush\Event\Dispatcher;
use Blush\Markdown\Events\MarkdownEnvironmentBuilding;

/**
 * The temporary `MarkdownParser` adapter over league/commonmark (D-045,
 * D-080). The converter is built on the first conversion, from
 * `MarkdownConfig` and then any `MarkdownEnvironmentBuilding` listeners, and
 * reused after that.
 */
final class CommonMarkParser implements MarkdownParser
{
	private ?MarkdownConverter $converter = null;

	public function __construct(
		private readonly MarkdownConfig $config,
		private readonly Dispatcher $events
	) {}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function toHtml(string $markdown): string
	{
		try {
			return $this->converter()->convert($markdown)->getContent();
		} catch (MarkdownException $e) {
			throw $e;
		} catch (Throwable $e) {
			throw new MarkdownException(sprintf('Unable to convert Markdown: %s', $e->getMessage()), previous: $e);
		}
	}

	/**
	 * Returns the converter, building it on first use.
	 *
	 * @throws MarkdownException
	 */
	private function converter(): MarkdownConverter
	{
		if ($this->converter !== null) {
			return $this->converter;
		}

		self::assertClasses('extensions', $this->config->extensions, ExtensionInterface::class);
		self::assertClasses('inlineParsers', $this->config->inlineParsers, InlineParserInterface::class);

		try {
			$environment = new Environment($this->config->options);

			foreach ($this->config->extensions as $extension) {
				$environment->addExtension(new $extension());
			}

			foreach ($this->config->inlineParsers as $parser) {
				$environment->addInlineParser(new $parser());
			}
		} catch (Throwable $e) {
			throw new MarkdownException(sprintf('Unable to build the Markdown environment: %s', $e->getMessage()), previous: $e);
		}

		$this->events->dispatch(new MarkdownEnvironmentBuilding($environment));

		return $this->converter = new MarkdownConverter($environment);
	}

	/**
	 * Checks that every configured class implements its contract.
	 *
	 * @param  list<string> $classes
	 * @throws MarkdownException
	 */
	private static function assertClasses(string $key, array $classes, string $contract): void
	{
		foreach ($classes as $class) {
			if (! is_subclass_of($class, $contract)) {
				throw new MarkdownException(sprintf(
					'MarkdownConfig "%s" must list %s classes; "%s" is not one.',
					$key,
					$contract,
					$class
				));
			}
		}
	}
}
