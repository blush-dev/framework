<?php

/**
 * MCP tool.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Mcp;

use Blush\Auth\Capability;
use Blush\Auth\ContentAction;

/**
 * A tool an app connected to the MCP server can call (D-753): a title, a
 * description written for the AI (when to use it, what it answers), the
 * JSON Schema of its input and of what it answers, the capability it
 * needs, and honest annotations. Its name is the one it's registered
 * under (`McpToolRegistry`): a plugin's start with its vendor and name,
 * `acme_gallery_list_albums` (D-757).
 *
 * Its input is checked against its schema before `handle()` runs, so a
 * tool reads what it declared; anything else it can't do it throws as a
 * `ToolError`, which the app gets back in words.
 *
 * A tool that writes content uses the context's write paths
 * (`$context->entries`, `$context->media`, D-756), so it can't save what
 * the admin would refuse, and the activity log records it (D-758).
 */
abstract class Tool
{
	/**
	 * A short name for people, as an app shows it.
	 */
	abstract public function title(): string;

	/**
	 * What it does, written for the AI: when to use it and what it
	 * answers.
	 */
	abstract public function description(): string;

	/**
	 * The JSON Schema of its input: an object.
	 *
	 * @return array<string, mixed>
	 */
	abstract public function input(): array;

	/**
	 * Does what it's asked.
	 *
	 * @param  array<string, mixed> $input
	 * @throws ToolError
	 */
	abstract public function handle(array $input, McpContext $context): ToolResult;

	/**
	 * The JSON Schema of what it answers, or `null` for none declared.
	 *
	 * @return ?array<string, mixed>
	 */
	public function output(): ?array
	{
		return null;
	}

	/**
	 * The capability it needs. Every plugin's tool names one (D-757); only
	 * Blush's own read tools may name none, so any connected account may
	 * read what it may see. A content action is on any type.
	 */
	public function capability(): Capability|ContentAction|string|null
	{
		return null;
	}

	/**
	 * What it says of itself: whether it only reads, may destroy, or is
	 * safe to repeat, and whether a tool that writes writes only drafts.
	 */
	public function annotations(): ToolAnnotations
	{
		return ToolAnnotations::reads();
	}

	/**
	 * Returns a text input, or `''` when it isn't one; the schema has
	 * checked the input already.
	 *
	 * @param array<string, mixed> $input
	 */
	protected static function text(array $input, string $key): string
	{
		$value = $input[$key] ?? null;

		return is_string($value) ? $value : '';
	}

	/**
	 * Returns a list of text from the input, without anything else.
	 *
	 * @param  array<string, mixed> $input
	 * @return list<string>
	 */
	protected static function strings(array $input, string $key): array
	{
		$value = $input[$key] ?? [];

		return is_array($value) ? array_values(array_filter($value, is_string(...))) : [];
	}

	/**
	 * Returns a text input, or `null` when it wasn't sent.
	 *
	 * @param array<string, mixed> $input
	 */
	protected static function optional(array $input, string $key): ?string
	{
		$value = $input[$key] ?? null;

		return is_string($value) ? $value : null;
	}

	/**
	 * Whether the context may use it: by default, its capability.
	 */
	public function allows(McpContext $context): bool
	{
		$capability = $this->capability();

		return $capability === null || $context->can($capability);
	}
}
