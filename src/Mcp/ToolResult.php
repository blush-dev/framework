<?php

/**
 * Tool result.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Mcp;

use JsonException;

/**
 * What a tool answers (D-753): its data, in the shape its output schema
 * gives, and the same as text for clients that read only text. A short
 * line of text may stand in for the data's copy.
 */
final readonly class ToolResult
{
	/**
	 * @param array<string, mixed> $data
	 */
	public function __construct(
		public array $data,
		public ?string $text = null
	) {}

	/**
	 * The result as MCP sends it.
	 *
	 * @return array<string, mixed>
	 */
	public function toArray(): array
	{
		try {
			$copy = json_encode($this->data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
		} catch (JsonException) {
			$copy = '{}';
		}

		return [
			'content'           => [['type' => 'text', 'text' => $this->text === null ? $copy : "{$this->text}\n\n{$copy}"]],
			'structuredContent' => (object) $this->data,
			'isError'           => false
		];
	}

	/**
	 * An error as MCP sends it: text the AI can act on.
	 *
	 * @return array<string, mixed>
	 */
	public static function error(string $message): array
	{
		return ['content' => [['type' => 'text', 'text' => $message]], 'isError' => true];
	}
}
