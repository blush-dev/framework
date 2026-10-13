<?php

/**
 * Extension-style tool fixture.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Tool;

use Override;
use Blush\Tool\Tool;
use Blush\Tool\ToolAnnotations;
use Blush\Tool\ToolContext;
use Blush\Tool\ToolResult;
use Blush\Translation\Messages;
use Blush\Translation\Text;

/**
 * Writes its words with `t()` (D-816), so they reach JSON and the
 * command line as text.
 */
final class GreetTool extends Tool
{
	public function __construct(private readonly Messages $messages)
	{}

	#[Override]
	public function title(): Text
	{
		return $this->messages->t('greet.title', 'Greet');
	}

	#[Override]
	public function description(): Text
	{
		return $this->messages->t('greet.description', 'Says hello.');
	}

	#[Override]
	public function capability(): string
	{
		return 'shop.greet';
	}

	#[Override]
	public function annotations(): ToolAnnotations
	{
		return ToolAnnotations::writes();
	}

	#[Override]
	public function confirm(): Text
	{
		return $this->messages->t('greet.confirm', 'Say hello?');
	}

	#[Override]
	public function handle(array $input, ToolContext $context): ToolResult
	{
		return ToolResult::done($this->messages->t('greet.done', 'Hello.'), ['from PHP']);
	}
}
