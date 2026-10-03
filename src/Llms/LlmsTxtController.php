<?php

/**
 * llms.txt controller.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Llms;

use Psr\Http\Message\ResponseInterface;
use Blush\Http\Response;

/**
 * Serves `/llms.txt` (`llms`), as plain text, as llmstxt.org asks.
 */
final readonly class LlmsTxtController
{
	public function __construct(private LlmsTxt $file)
	{}

	public function __invoke(): ResponseInterface
	{
		return Response::text($this->file->render());
	}
}
