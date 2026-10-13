<?php

/**
 * MCP tools.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Mcp;

use JsonException;
use Stringable;
use Throwable;
use Psr\Log\LoggerInterface;
use Blush\Auth\TokenScope;
use Blush\Cache\CacheException;
use Blush\Http\RateLimiter;
use Blush\Tool\Tool;
use Blush\Tool\ToolContext;
use Blush\Tool\ToolError;
use Blush\Tool\ToolRegistry;
use Blush\Tool\ToolResult;
use Blush\Tool\Tools;

/**
 * The tools the MCP server offers a context, and calling them (D-753,
 * D-755 to D-758, D-761), by their flat names (`get_entry`,
 * `acme_rebuild_thumbnails`).
 *
 * A tool is offered when the context may use it (`Tools::allows()`) and
 * it's for apps (`Tool::mcp()`); a plugin's tool that writes is offered
 * to a token's scope only by its annotations: `account`, or `drafts` when
 * it writes only drafts (D-757).
 *
 * A token's calls are counted against `McpConfig`'s limits per hour,
 * writes on their own too (D-758); a refusal, like a tool's own error,
 * goes back as the tool's result, in words, marked as an error.
 */
final readonly class McpTools
{
	/**
	 * The window the limits count in, in seconds.
	 */
	private const int WINDOW = 3600;

	public function __construct(
		private Tools $tools,
		private ToolRegistry $registry,
		private RateLimiter $limiter,
		private McpConfig $config,
		private LoggerInterface $logger
	) {}

	/**
	 * Returns the tools a context is offered, by flat name.
	 *
	 * @return array<string, Tool>
	 */
	public function offered(ToolContext $context): array
	{
		$offered = [];

		foreach ($this->tools->allowed($context) as $key => $tool) {
			if ($this->offers($key, $tool, $context)) {
				$offered[ToolRegistry::flatName($key)] = $tool;
			}
		}

		return $offered;
	}

	/**
	 * Returns every tool for apps, by key, whoever may use it: what the AI
	 * settings screen lists.
	 *
	 * @return array<string, Tool>
	 */
	public function all(): array
	{
		return array_filter($this->tools->all(), static fn (Tool $tool): bool => $tool->mcp());
	}

	/**
	 * Describes the tools a context is offered, as `tools/list` answers.
	 *
	 * @return list<array<string, mixed>>
	 */
	public function describe(ToolContext $context): array
	{
		$described = [];

		foreach ($this->offered($context) as $name => $tool) {
			$output = $tool->output();

			$described[] = [
				'name'        => $name,
				'title'       => (string) $tool->title(),
				'description' => (string) $tool->description(),
				'inputSchema' => (object) $tool->input(),
				...($output === null ? [] : ['outputSchema' => (object) $output]),
				'annotations' => ['title' => (string) $tool->title(), ...$tool->annotations()->toArray()]
			];
		}

		return $described;
	}

	/**
	 * Calls a tool by its flat name, and returns its result as MCP sends
	 * it, or `null` when the context isn't offered a tool by that name.
	 *
	 * @return ?array<string, mixed>
	 */
	public function call(string $name, mixed $arguments, ToolContext $context): ?array
	{
		$key  = $this->registry->keyOf($name);
		$tool = $key === null ? null : ($this->offered($context)[$name] ?? null);

		if ($key === null || $tool === null) {
			return null;
		}

		if ($arguments !== null && (! is_array($arguments) || ($arguments !== [] && array_is_list($arguments)))) {
			return self::error('The arguments must be an object.');
		}

		$wait = $this->count($tool, $context);

		if ($wait !== null) {
			return self::error($wait);
		}

		try {
			/** @var array<string, mixed> $input */
			$input = $arguments ?? [];

			return self::result($this->tools->run($key, $input, $context));
		} catch (ToolError $error) {
			return self::error(implode("\n", [$error->getMessage(), ...$error->details]));
		} catch (Throwable $error) {
			$this->logger->error('The tool "{key}" failed: {problem}', ['key' => $key, 'problem' => $error->getMessage(), 'exception' => $error]);

			return self::error('Something went wrong on the site; the change may not have been made. Read it again before trying anything else.');
		}
	}

	/**
	 * Whether a context is offered a tool it may use.
	 */
	private function offers(string $key, Tool $tool, ToolContext $context): bool
	{
		if (! $tool->mcp()) {
			return false;
		}

		$annotations = $tool->annotations();

		return $this->tools->isBuiltIn($key)
			|| $annotations->readOnly
			|| $context->scope === TokenScope::Account
			|| ($context->scope === TokenScope::Drafts && $annotations->draftsSafe);
	}

	/**
	 * Counts a token's call, and returns why it must wait, or `null`.
	 */
	private function count(Tool $tool, ToolContext $context): ?string
	{
		$token = $context->actor->token;

		if (! $context->limited || $token === null) {
			return null;
		}

		try {
			$wait = $this->limiter->hit("mcp|calls|{$token}", $this->config->maxCalls, self::WINDOW);

			if ($wait === null && ! $tool->annotations()->readOnly) {
				$wait = $this->limiter->hit("mcp|writes|{$token}", $this->config->maxWrites, self::WINDOW);
				$what = sprintf('%d changes', $this->config->maxWrites);
			} else {
				$what = sprintf('%d calls', $this->config->maxCalls);
			}
		} catch (CacheException) {
			return null;
		}

		return $wait === null ? null : sprintf('This connection has made %s this hour, the most the site allows. Try again in %d minute%s.', $what, (int) ceil($wait / 60), (int) ceil($wait / 60) === 1 ? '' : 's');
	}

	/**
	 * A tool's result as MCP sends it (D-753): its line of text and
	 * details, then its data as `structuredContent` and, for clients that
	 * read only text, as JSON after the text. A result with no data
	 * (`ToolResult::done()`) is its text alone, since a client that reads
	 * `structuredContent` first would show an empty object and lose the
	 * message.
	 *
	 * @return array<string, mixed>
	 */
	private static function result(ToolResult $result): array
	{
		$text = implode("\n", array_filter(array_map(static fn (string|Stringable|null $line): string => (string) $line, [$result->text, ...$result->details]), static fn (string $line): bool => $line !== ''));

		if ($result->data === []) {
			return ['content' => [['type' => 'text', 'text' => $text === '' ? 'Done.' : $text]], 'isError' => false];
		}

		try {
			$copy = json_encode($result->data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
		} catch (JsonException) {
			$copy = '{}';
		}

		return [
			'content'           => [['type' => 'text', 'text' => $text === '' ? $copy : "{$text}\n\n{$copy}"]],
			'structuredContent' => (object) $result->data,
			'isError'           => false
		];
	}

	/**
	 * An error as MCP sends it: text the AI can act on.
	 *
	 * @return array<string, mixed>
	 */
	private static function error(string $message): array
	{
		return ['content' => [['type' => 'text', 'text' => $message]], 'isError' => true];
	}
}
