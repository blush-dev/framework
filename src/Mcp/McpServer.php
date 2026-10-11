<?php

/**
 * MCP server.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Mcp;

use Blush\Core\Framework;
use Blush\Tool\ToolContext;

/**
 * Answers MCP's messages (D-751, D-753): JSON-RPC 2.0, one message at a
 * time, with no sessions, so the same handler serves the web (each
 * `POST` answered as JSON) and the command line (one message per line).
 *
 * Methods: `initialize` (the protocol version, the server's name, and its
 * `instructions`), `ping`, `tools/list`, and `tools/call`; notifications
 * (no `id`) are taken and answer nothing. Tools are its only feature in
 * this version: no resources or prompts.
 *
 * A malformed message is a JSON-RPC error; a tool's own failure is its
 * result, marked as an error, so the AI can correct itself.
 */
final readonly class McpServer
{
	/**
	 * The protocol versions it speaks, newest first.
	 *
	 * @var list<string>
	 */
	public const array VERSIONS = ['2025-11-25', '2025-06-18', '2025-03-26'];

	public const int PARSE_ERROR = -32700;

	public const int INVALID_REQUEST = -32600;

	public const int METHOD_NOT_FOUND = -32601;

	public const int INVALID_PARAMS = -32602;

	public function __construct(
		private McpTools $tools,
		private McpInstructions $instructions
	) {}

	/**
	 * Answers a message, decoded, or returns `null` for a notification
	 * or a response, which answer nothing.
	 *
	 * @return ?array<string, mixed>
	 */
	public function handle(mixed $message, ToolContext $context): ?array
	{
		if (! is_array($message) || array_is_list($message)) {
			return self::error(null, self::INVALID_REQUEST, 'Send one JSON-RPC 2.0 message, as an object.');
		}

		$id     = $message['id'] ?? null;
		$method = $message['method'] ?? null;

		if (($message['jsonrpc'] ?? null) !== '2.0' || ($id !== null && ! is_string($id) && ! is_int($id))) {
			return self::error(is_string($id) || is_int($id) ? $id : null, self::INVALID_REQUEST, 'Send a JSON-RPC 2.0 message with a string or number "id".');
		}

		// A response or a notification answers nothing.
		if (! is_string($method) || ! array_key_exists('id', $message)) {
			return null;
		}

		if ($id === null) {
			return self::error(null, self::INVALID_REQUEST, 'A request\'s "id" is a string or a number, never null.');
		}

		$params = is_array($message['params'] ?? null) ? $message['params'] : [];

		return match ($method) {
			'initialize' => self::result($id, $this->initialize($params)),
			'ping'       => self::result($id, (object) []),
			'tools/list' => self::result($id, ['tools' => $this->tools->describe($context)]),
			'tools/call' => $this->call($id, $params, $context),
			default      => self::error($id, self::METHOD_NOT_FOUND, sprintf('There\'s no method "%s".', $method))
		};
	}

	/**
	 * Returns a parse error, for a message that isn't JSON.
	 *
	 * @return array<string, mixed>
	 */
	public static function parseError(): array
	{
		return self::error(null, self::PARSE_ERROR, 'That isn\'t JSON.');
	}

	/**
	 * Answers `initialize`: the client's protocol version when it's one
	 * this server speaks, else its newest.
	 *
	 * @param  array<mixed>         $params
	 * @return array<string, mixed>
	 */
	private function initialize(array $params): array
	{
		$asked = $params['protocolVersion'] ?? null;

		return [
			'protocolVersion' => is_string($asked) && in_array($asked, self::VERSIONS, true) ? $asked : self::VERSIONS[0],
			'capabilities'    => ['tools' => ['listChanged' => false]],
			'serverInfo'      => ['name' => Framework::BINARY, 'title' => Framework::NAME, 'version' => Framework::VERSION],
			'instructions'    => $this->instructions->text()
		];
	}

	/**
	 * Answers `tools/call`.
	 *
	 * @param  array<mixed>         $params
	 * @return array<string, mixed>
	 */
	private function call(string|int $id, array $params, ToolContext $context): array
	{
		$name = $params['name'] ?? null;

		if (! is_string($name)) {
			return self::error($id, self::INVALID_PARAMS, 'Send the tool\'s "name".');
		}

		$result = $this->tools->call($name, $params['arguments'] ?? null, $context);

		return $result === null
			? self::error($id, self::INVALID_PARAMS, sprintf('There\'s no tool "%s" for this connection; tools/list says which there are.', $name))
			: self::result($id, $result);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function result(string|int $id, mixed $result): array
	{
		return ['jsonrpc' => '2.0', 'id' => $id, 'result' => $result];
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function error(string|int|null $id, int $code, string $message): array
	{
		return ['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $message]];
	}
}
