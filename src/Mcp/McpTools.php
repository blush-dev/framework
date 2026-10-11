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

use Throwable;
use Psr\Log\LoggerInterface;
use Blush\Auth\Capability;
use Blush\Auth\ContentAction;
use Blush\Auth\TokenScope;
use Blush\Cache\CacheException;
use Blush\Container\Container;
use Blush\Http\RateLimiter;

/**
 * The tools the MCP server offers a context, and calling them (D-753,
 * D-755 to D-758).
 *
 * A tool is offered when its capability allows it; a plugin's tool must
 * name a capability (only Blush's own read tools may name none), and one
 * that writes is offered to a token's scope only by its annotations:
 * `account`, or `drafts` when it writes only drafts (D-757).
 *
 * A call's input is checked against the tool's schema first, and a token's
 * calls are counted against `McpConfig`'s limits per hour, writes on
 * their own too (D-758); a refusal, like a tool's own error, goes back
 * as the tool's result, in words, marked as an error.
 */
final readonly class McpTools
{
	/**
	 * The window the limits count in, in seconds.
	 */
	private const int WINDOW = 3600;

	public function __construct(
		private McpToolRegistry $registry,
		private Container $container,
		private RateLimiter $limiter,
		private McpConfig $config,
		private LoggerInterface $logger
	) {}

	/**
	 * Returns the tools a context is offered, by name.
	 *
	 * @return array<string, Tool>
	 */
	public function offered(McpContext $context): array
	{
		$offered = [];

		foreach ($this->all() as $name => $tool) {
			if ($this->offers($name, $tool, $context)) {
				$offered[$name] = $tool;
			}
		}

		return $offered;
	}

	/**
	 * Returns every tool that can be built, by name, in the order they
	 * were registered: Blush's own first.
	 *
	 * @return array<string, Tool>
	 */
	public function all(): array
	{
		$tools = [];

		foreach ($this->registry->all() as $name => $class) {
			try {
				$tools[$name] = $this->container->make($class);
			} catch (Throwable $error) {
				$this->logger->warning('The MCP tool "{name}" couldn\'t be built: {problem}', ['name' => $name, 'problem' => $error->getMessage()]);
			}
		}

		return $tools;
	}

	/**
	 * Describes the tools a context is offered, as `tools/list` answers.
	 *
	 * @return list<array<string, mixed>>
	 */
	public function describe(McpContext $context): array
	{
		$described = [];

		foreach ($this->offered($context) as $name => $tool) {
			$output = $tool->output();

			$described[] = [
				'name'        => $name,
				'title'       => $tool->title(),
				'description' => $tool->description(),
				'inputSchema' => (object) $tool->input(),
				...($output === null ? [] : ['outputSchema' => (object) $output]),
				'annotations' => ['title' => $tool->title(), ...$tool->annotations()->toArray()]
			];
		}

		return $described;
	}

	/**
	 * Calls a tool, and returns its result as MCP sends it, or `null`
	 * when the context isn't offered a tool by that name.
	 *
	 * @return ?array<string, mixed>
	 */
	public function call(string $name, mixed $arguments, McpContext $context): ?array
	{
		$tool = $this->offered($context)[$name] ?? null;

		if ($tool === null) {
			return null;
		}

		$input   = is_array($arguments) ? $arguments : [];
		$problem = $arguments !== null && (! is_array($arguments) || ($arguments !== [] && array_is_list($arguments)))
			? 'The arguments must be an object.'
			: InputCheck::problem($input, $tool->input());

		if ($problem !== null) {
			return ToolResult::error($problem);
		}

		$wait = $this->count($tool, $context);

		if ($wait !== null) {
			return ToolResult::error($wait);
		}

		try {
			/** @var array<string, mixed> $input */
			return $tool->handle($input, $context)->toArray();
		} catch (ToolError $error) {
			return ToolResult::error($error->getMessage());
		} catch (Throwable $error) {
			$this->logger->error('The MCP tool "{name}" failed: {problem}', ['name' => $name, 'problem' => $error->getMessage(), 'exception' => $error]);

			return ToolResult::error('Something went wrong on the site; the change may not have been made. Read it again before trying anything else.');
		}
	}

	/**
	 * The name a tool's capability goes by, for the AI screen.
	 */
	public static function capabilityName(Tool $tool): ?string
	{
		$capability = $tool->capability();

		return match (true) {
			$capability instanceof Capability    => $capability->value,
			$capability instanceof ContentAction => $capability->on(ContentAction::EVERY),
			default                              => $capability
		};
	}

	/**
	 * Whether a tool registered under a name is Blush's own.
	 */
	public function isBuiltIn(string $name): bool
	{
		$class = $this->registry->get($name);

		return $class !== null && ToolType::isBuiltIn($name, $class);
	}

	/**
	 * Whether a context is offered a tool.
	 */
	private function offers(string $name, Tool $tool, McpContext $context): bool
	{
		$builtIn = $this->isBuiltIn($name);

		if (! $builtIn && $tool->capability() === null) {
			return false;
		}

		$annotations = $tool->annotations();

		if (! $builtIn && ! $annotations->readOnly && ! ($context->scope === TokenScope::Account || ($context->scope === TokenScope::Drafts && $annotations->draftsSafe))) {
			return false;
		}

		return $tool->allows($context);
	}

	/**
	 * Counts a token's call, and returns why it must wait, or `null`.
	 */
	private function count(Tool $tool, McpContext $context): ?string
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
}
