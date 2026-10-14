<?php

/**
 * Admin action controller.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin\Http;

use Throwable;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Blush\Admin\Provenance;
use Blush\Admin\ToolButtons;
use Blush\Auth\Account;
use Blush\Http\Response;
use Blush\Http\Status;
use Blush\Tool\ToolError;
use Blush\Tool\Tools;
use Blush\Translation\Messages;

/**
 * The Tools screen's buttons (D-222, D-540, D-761): each tool that needs
 * no input and is a button (`Tool::button()`), for accounts that may use
 * it. `GET {path}/api/tools`
 * lists them, grouped by where they come from (`Provenance::ofClass()`):
 * Blush's first, then the site's, then each plugin's. `POST
 * {path}/api/tools/{vendor}/{name}` runs one: an unknown tool, or one
 * that isn't a button, is a 404 and one the account may not use a 403;
 * otherwise the answer is the tool's result, whether it worked or not. A
 * tool that's `queued()` is pushed as a job instead, once however many
 * ask, and the answer is its `job`'s id, which the admin runs and
 * follows (`JobController`) to its result.
 */
final readonly class ToolController
{
	public function __construct(
		private Tools $tools,
		private ToolButtons $buttons,
		private Provenance $provenance,
		private LoggerInterface $logger,
		private Messages $messages
	) {}

	/**
	 * Lists the buttons the account may use, in groups by source.
	 */
	public function index(ServerRequestInterface $request): ResponseInterface
	{
		$account = $request->getAttribute(Account::class);

		if (! $account instanceof Account) {
			return self::json(['error' => $this->messages->t('admin.common.sign_in_first', 'Sign in first.')], Status::Unauthorized);
		}

		$groups = [];

		foreach ($this->buttons->allowed($account) as $key => $tool) {
			$source = $this->provenance->ofClass($this->tools->classOf($key) ?? $tool::class);
			$group  = "{$source['kind']}:{$source['label']}";

			$groups[$group] ??= ['source' => $source, 'tools' => []];
			$groups[$group]['tools'][] = [
				'key'         => $key,
				'title'       => (string) $tool->title(),
				'description' => (string) $tool->description(),
				'confirm'     => $this->buttons->confirm($tool)
			];
		}

		$order = ['core' => 0, 'site' => 1, 'plugin' => 2, 'other' => 3];

		uasort($groups, static fn (array $a, array $b): int => [$order[$a['source']['kind']] ?? 3, $a['source']['label']] <=> [$order[$b['source']['kind']] ?? 3, $b['source']['label']]);

		return self::json(['groups' => array_values($groups)]);
	}

	public function __invoke(ServerRequestInterface $request, string $tool): ResponseInterface
	{
		$account  = $request->getAttribute(Account::class);
		$instance = $this->buttons->get($tool);

		if ($instance === null) {
			return self::json(['error' => $this->messages->t('admin.tool_controller.no_tool', 'There\'s no "{tool}" tool.', tool: $tool)], Status::NotFound);
		}

		$context = $account instanceof Account ? $this->buttons->context($account) : null;

		if ($context === null || ! $this->tools->allows($tool, $instance, $context)) {
			return self::json(['error' => $this->messages->t('admin.tool_controller.not_allowed', 'You aren\'t allowed to do that.')], Status::Forbidden);
		}

		try {
			$result = $this->tools->run($tool, [], $context);
		} catch (ToolError $error) {
			return self::json(['successful' => false, 'message' => $error->getMessage(), 'details' => $error->details]);
		} catch (Throwable $error) {
			$this->logger->error('The tool "{key}" failed: {problem}', ['key' => $tool, 'problem' => $error->getMessage(), 'exception' => $error]);

			return self::json(['successful' => false, 'message' => $this->messages->t('admin.tool_controller.failed', 'Something went wrong; the site\'s log says what.'), 'details' => []]);
		}

		return $result->job === null
			? self::json(['successful' => true, 'message' => (string) ($result->text ?? $this->messages->t('admin.tool_controller.done', 'Done.')), 'details' => array_map(strval(...), $result->details)])
			: self::json(['job' => $result->job]);
	}

	/**
	 * Builds an uncached JSON response.
	 *
	 * @param array<string, mixed> $data
	 */
	private static function json(array $data, Status $status = Status::Ok): ResponseInterface
	{
		return Response::json($data, $status, ['Cache-Control' => 'no-store']);
	}
}
