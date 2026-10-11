<?php

/**
 * MCP controller.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Mcp\Http;

use JsonException;
use LogicException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Blush\Auth\Account;
use Blush\Auth\AuthException;
use Blush\Auth\Token;
use Blush\Http\Status;
use Blush\Mcp\McpContexts;
use Blush\Mcp\McpServer;

/**
 * Answers MCP over the web (D-751): Streamable HTTP without sessions or
 * streams, since a PHP request on shared hosting can't hold one open.
 * Each `POST` is one JSON-RPC message, answered as JSON; a notification
 * is a `202` with nothing in it. A `GET`, which would open a stream, is
 * a `405`, as the protocol allows.
 */
final readonly class McpController
{
	public function __construct(
		private McpServer $server,
		private McpContexts $contexts
	) {}

	/**
	 * @throws AuthException For a token whose capabilities aren't names.
	 */
	public function post(ServerRequestInterface $request): ResponseInterface
	{
		$version = $request->getHeaderLine('MCP-Protocol-Version');

		if ($version !== '' && ! in_array($version, McpServer::VERSIONS, true)) {
			return McpResponses::json(['error' => sprintf('This server speaks MCP %s.', implode(', ', McpServer::VERSIONS))], Status::BadRequest);
		}

		$account = $request->getAttribute(Account::class);
		$token   = $request->getAttribute(Token::class);
		$client  = $request->getAttribute(McpAuthentication::CLIENT);

		if (! $account instanceof Account || ! $token instanceof Token) {
			throw new LogicException('The MCP server needs its authentication middleware.');
		}

		try {
			$message = json_decode((string) $request->getBody(), true, 64, JSON_THROW_ON_ERROR);
		} catch (JsonException) {
			return McpResponses::json(McpServer::parseError(), Status::BadRequest);
		}

		$answer = $this->server->handle($message, $this->contexts->forToken($account, $token, is_string($client) ? $client : null));

		return $answer === null ? McpResponses::empty(Status::Accepted) : McpResponses::json($answer);
	}

	/**
	 * Refuses a stream, and closing a session there isn't.
	 */
	public function refuse(): ResponseInterface
	{
		return McpResponses::json(['error' => 'This MCP server answers each POST as JSON; it opens no streams and keeps no sessions.'], Status::MethodNotAllowed, ['Allow' => 'POST, OPTIONS']);
	}

	public function preflight(): ResponseInterface
	{
		return McpResponses::preflight('POST, OPTIONS');
	}
}
