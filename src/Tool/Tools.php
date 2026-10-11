<?php

/**
 * Tools.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tool;

use Throwable;
use Psr\Log\LoggerInterface;
use Blush\Auth\Capability;
use Blush\Auth\ContentAction;
use Blush\Container\Container;
use Blush\Job\JobException;
use Blush\Job\JobQueue;
use Blush\Job\JobType;

/**
 * The registered tools, built through the container, and running them
 * (D-761), for every place that offers them: the MCP server, the Tools
 * screen, `blush tool`, and the `core/tool` job.
 *
 * A context may use a tool when its capability allows it; a tool that
 * isn't Blush's own must name a capability (only Blush's own read tools
 * may name none, D-757). Input is checked against the tool's schema
 * before it runs. A tool that's `queued()` is pushed as a `core/tool` job
 * unless the caller waits for it. A token's calls never queue, since a
 * job can't carry the token's narrowing, and neither does an actor with
 * no saved account (the command line's); they run straight away.
 */
final readonly class Tools
{
	public function __construct(
		private ToolRegistry $registry,
		private Container $container,
		private JobQueue $jobs,
		private LoggerInterface $logger
	) {}

	/**
	 * Returns every tool that can be built, by key, in the order they
	 * were registered: Blush's own first.
	 *
	 * @return array<string, Tool>
	 */
	public function all(): array
	{
		$tools = [];

		foreach (array_keys($this->registry->all()) as $key) {
			$tool = $this->get($key);

			if ($tool !== null) {
				$tools[$key] = $tool;
			}
		}

		return $tools;
	}

	/**
	 * Returns a tool, or `null` when none has the key or it can't be
	 * built.
	 */
	public function get(string $key): ?Tool
	{
		$class = $this->registry->get($key);

		if ($class === null) {
			return null;
		}

		try {
			return $this->container->make($class);
		} catch (Throwable $error) {
			$this->logger->warning('The tool "{key}" couldn\'t be built: {problem}', ['key' => $key, 'problem' => $error->getMessage()]);

			return null;
		}
	}

	/**
	 * Returns the tools a context may use, by key.
	 *
	 * @return array<string, Tool>
	 */
	public function allowed(ToolContext $context): array
	{
		return array_filter($this->all(), fn (Tool $tool, string $key): bool => $this->allows($key, $tool, $context), ARRAY_FILTER_USE_BOTH);
	}

	/**
	 * Whether a context may use a tool.
	 */
	public function allows(string $key, Tool $tool, ToolContext $context): bool
	{
		return ($tool->capability() !== null || $this->isBuiltIn($key)) && $tool->allows($context);
	}

	/**
	 * Runs a tool for a context, after checking its input, or queues it
	 * when it's `queued()` and the caller doesn't `wait`.
	 *
	 * @param  array<string, mixed> $input
	 * @throws ToolError When there's no such tool for the context, the
	 *                   input is wrong, or the tool can't do it.
	 */
	public function run(string $key, array $input, ToolContext $context, bool $wait = false): ToolResult
	{
		$tool = $this->get($key);

		if ($tool === null || ! $this->allows($key, $tool, $context)) {
			throw new ToolError(sprintf('There\'s no tool "%s" for you.', $key));
		}

		$problem = InputCheck::problem($input, $tool->input());

		if ($problem !== null) {
			throw new ToolError($problem);
		}

		if (! $tool->queued() || $wait || $context->actor->token !== null || $context->actor->account->id === '') {
			return $tool->handle($input, $context);
		}

		try {
			return ToolResult::queued($this->jobs->push(JobType::Tool->value, [
				'tool'   => $key,
				'input'  => $input,
				'via'    => $context->actor->via->value,
				'client' => $context->actor->client
			], $context->actor->account->id, $input === [] ? "tool:{$key}" : null));
		} catch (JobException $e) {
			throw new ToolError($e->getMessage());
		}
	}

	/**
	 * Whether a tool registered under a key is Blush's own.
	 */
	public function isBuiltIn(string $key): bool
	{
		$class = $this->registry->get($key);

		return $class !== null && ToolType::isBuiltIn($key, $class);
	}

	/**
	 * Returns the class a tool is built from, or `null` when none has the
	 * key.
	 *
	 * @return ?class-string<Tool>
	 */
	public function classOf(string $key): ?string
	{
		return $this->registry->get($key);
	}

	/**
	 * Returns the name a tool's capability goes by, or `null` for none.
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
}
