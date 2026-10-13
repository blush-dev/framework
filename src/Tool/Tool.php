<?php

/**
 * Tool.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tool;

use Stringable;
use Blush\Auth\Capability;
use Blush\Auth\ContentAction;

/**
 * Something the site can do, written once and offered everywhere it fits
 * (D-761): to apps connected to the MCP server, as a button on the Tools
 * screen when it needs no input (`button()`), and on the command line
 * (`blush tool {key}`). It has a title, a description written for people
 * and AIs alike (when to use it, what it answers), the JSON Schema of its
 * input and of what it answers, the capability it needs, and honest
 * annotations. Its key is the one it's registered under (`ToolRegistry`):
 * `vendor/name`, `acme/rebuild-thumbnails`.
 *
 * Its input is checked against its schema before `handle()` runs, so a
 * tool reads what it declared; anything else it can't do it throws as a
 * `ToolError`, which goes back in words.
 *
 * A tool that writes content uses the context's write paths
 * (`$context->entries`, `$context->media`, D-756), so it can't save what
 * the admin would refuse, and the activity log records it (D-758). Work
 * that may outlast a request says so (`queued()`), and runs as a job
 * where a request would cut it short.
 */
abstract class Tool
{
	/**
	 * A short name for people, as a button or an app shows it, in Title
	 * Case: text, or a `Text` from `t()`, translated where it's shown
	 * (D-816).
	 */
	abstract public function title(): string|Stringable;

	/**
	 * What it does, written for the AI: when to use it and what it
	 * answers.
	 */
	abstract public function description(): string|Stringable;

	/**
	 * Does what it's asked.
	 *
	 * @param  array<string, mixed> $input
	 * @throws ToolError
	 */
	abstract public function handle(array $input, ToolContext $context): ToolResult;

	/**
	 * The JSON Schema of its input: an object, by default one with
	 * nothing in it.
	 *
	 * @return array<string, mixed>
	 */
	public function input(): array
	{
		return ['type' => 'object', 'additionalProperties' => false];
	}

	/**
	 * Whether it can run with no input: nothing in its schema is
	 * required.
	 */
	public function needsInput(): bool
	{
		$required = $this->input()['required'] ?? [];

		return is_array($required) && $required !== [];
	}

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
	 * Blush's own read tools may name none, so any account may read what
	 * it may see. A content action is on any type.
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
	 * Whether apps connected to the MCP server are offered it. Tools for
	 * looking after the site (publishing, clearing caches) aren't.
	 */
	public function mcp(): bool
	{
		return true;
	}

	/**
	 * Whether it's a button on the Tools screen, when it needs no input:
	 * by default, when it writes. A tool that only reports something
	 * (counting words) says so here.
	 */
	public function button(): bool
	{
		return ! $this->annotations()->readOnly;
	}

	/**
	 * Whether it may outlast a request, so the admin and apps run it in
	 * the background as a job (`core/tool`), and the command line runs it
	 * straight away.
	 */
	public function queued(): bool
	{
		return false;
	}

	/**
	 * A question to ask before the Tools screen runs it, or `null` for
	 * none; a tool that may destroy something is asked about anyway.
	 */
	public function confirm(): string|Stringable|null
	{
		return null;
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
	public function allows(ToolContext $context): bool
	{
		$capability = $this->capability();

		return $capability === null || $context->can($capability);
	}
}
