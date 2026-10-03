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
use Psr\Http\Message\ServerRequestInterface;
use Blush\Http\Response;

/**
 * Serves `/llms.txt` (`llms`) and `/llms-full.txt` (`llms.full`, D-402),
 * as plain text, as llmstxt.org asks.
 */
final readonly class LlmsTxtController
{
	public function __construct(private LlmsTxt $file)
	{}

	public function __invoke(ServerRequestInterface $request): ResponseInterface
	{
		return Response::text($request->getUri()->getPath() === LlmsRoutes::FULL ? $this->file->renderFull() : $this->file->render());
	}
}
