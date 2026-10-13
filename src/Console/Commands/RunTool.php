<?php

/**
 * Run tool command.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Console\Commands;

use JsonException;
use Blush\Activity\Actor;
use Blush\Auth\Accounts;
use Blush\Console\Attributes\Argument;
use Blush\Console\Attributes\Command;
use Blush\Console\Attributes\Option;
use Blush\Console\ExitCode;
use Blush\Console\InvalidInput;
use Blush\Console\Output;
use Blush\Console\Verbosity;
use Blush\Tool\Tool;
use Blush\Tool\ToolContexts;
use Blush\Tool\ToolError;
use Blush\Tool\Tools;

/**
 * Runs a tool (D-761), or lists them, as the command line (with the
 * owner's permissions, D-760) or as an account. Input is given as
 * `name=value`, each value read by the tool's schema: numbers, `true` or
 * `false`, a list by naming it again (or as JSON), and an object as JSON.
 * A tool that runs in the background elsewhere runs straight away here.
 *
 *     blush tool core/reindex
 *     blush tool core/search-entries search=coffee type=post --json
 */
#[Command('tool', 'Run a tool, or list them.')]
final readonly class RunTool
{
	public function __construct(
		private Tools $tools,
		private ToolContexts $contexts,
		private Accounts $accounts
	) {}

	public function __invoke(
		Output $output,
		#[Argument('The tool\'s key, such as core/reindex; the tools are listed when it\'s left out.')] ?string $key = null,
		#[Option('The account it runs as, by username, with its permissions; the command line when left out.')] ?string $account = null,
		#[Option('Write only what the tool answers, as JSON.')] bool $json = false,
		#[Argument('Its input, as name=value.')] string ...$input
	): ExitCode {
		$actor   = $this->actor($account);
		$context = $this->contexts->forActor($actor);
		$allowed = $this->tools->allowed($context);

		if ($key === null) {
			return $this->list($output, $allowed);
		}

		$tool = $allowed[$key] ?? throw new InvalidInput($this->tools->get($key) === null
			? sprintf('There\'s no "%s" tool; `blush tool` lists them.', $key)
			: sprintf('%s may not use the "%s" tool.', $actor->isCommandLine() ? 'The command line' : $actor->account->username, $key));

		$values = self::values($tool, array_values($input));

		if ($values === [] && $tool->needsInput()) {
			$output->line(sprintf('%s takes:', $tool->title()));
			$output->definitions(self::fields($tool), code: true);
			$output->newLine();
		}

		try {
			$result = $this->tools->run($key, $values, $context, wait: true);
		} catch (ToolError $error) {
			$output->error(implode("\n", [$error->getMessage(), ...$error->details]));

			return ExitCode::Failure;
		}

		if ($json) {
			$output->line(self::encode($result->data), Verbosity::Quiet);

			return ExitCode::Success;
		}

		$output->success((string) ($result->text ?? 'Done.'));

		foreach ($result->details as $detail) {
			$output->line("  {$detail}");
		}

		if ($result->data !== []) {
			$output->line(self::encode($result->data), $result->text === null ? Verbosity::Normal : Verbosity::Verbose);
		}

		return ExitCode::Success;
	}

	/**
	 * Lists the tools the actor may use.
	 *
	 * @param array<string, Tool> $tools
	 */
	private function list(Output $output, array $tools): ExitCode
	{
		if ($tools === []) {
			$output->line('No tools.');

			return ExitCode::Success;
		}

		$rows = [];

		foreach ($tools as $key => $tool) {
			$rows[] = [$key, (string) $tool->title(), $tool->annotations()->readOnly ? 'reads' : 'writes', Tools::capabilityName($tool) ?? 'anyone'];
		}

		$output->table(['Tool', 'Title', 'Does', 'Needs'], $rows);
		$output->next('Run one with `blush tool {key} name=value …`; `--json` writes only what it answers.');

		return ExitCode::Success;
	}

	/**
	 * The actor a tool runs as.
	 */
	private function actor(?string $username): Actor
	{
		if ($username === null) {
			return Actor::commandLine();
		}

		$account = $this->accounts->find($username) ?? throw new InvalidInput(sprintf('There\'s no "%s" account.', $username));

		return $account->suspended
			? throw new InvalidInput(sprintf('%s is suspended, so it can\'t run tools.', $account->username))
			: Actor::cli($account);
	}

	/**
	 * Reads `name=value` pairs by the tool's schema.
	 *
	 * @param  list<string>         $pairs
	 * @return array<string, mixed>
	 */
	private static function values(Tool $tool, array $pairs): array
	{
		$properties = $tool->input()['properties'] ?? [];
		$properties = is_array($properties) ? $properties : [];
		$values     = [];

		foreach ($pairs as $pair) {
			if (! str_contains($pair, '=')) {
				throw new InvalidInput(sprintf('Give input as name=value; "%s" has no "=".', $pair));
			}

			[$name, $value] = explode('=', $pair, 2);

			$schema = is_array($properties[$name] ?? null) ? $properties[$name] : [];
			$type   = $schema['type'] ?? null;

			if ($type === 'array' && ! str_starts_with($value, '[')) {
				$items = is_array($schema['items'] ?? null) ? $schema['items'] : [];

				$values[$name]   = is_array($values[$name] ?? null) ? $values[$name] : [];
				$values[$name][] = self::cast($name, $value, $items['type'] ?? null);
				continue;
			}

			$values[$name] = self::cast($name, $value, $type);
		}

		return $values;
	}

	/**
	 * Reads one value by its schema's type.
	 */
	private static function cast(string $name, string $value, mixed $type): mixed
	{
		return match ($type) {
			'integer' => filter_var($value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE) ?? throw new InvalidInput(sprintf('%s is a whole number; "%s" isn\'t one.', $name, $value)),
			'number'  => filter_var($value, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE) ?? throw new InvalidInput(sprintf('%s is a number; "%s" isn\'t one.', $name, $value)),
			'boolean' => filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? throw new InvalidInput(sprintf('%s is true or false; "%s" is neither.', $name, $value)),
			'array',
			'object'  => self::decode($name, $value),
			default   => $value
		};
	}

	/**
	 * Reads a value given as JSON.
	 */
	private static function decode(string $name, string $value): mixed
	{
		try {
			return json_decode($value, true, 32, JSON_THROW_ON_ERROR);
		} catch (JsonException) {
			throw new InvalidInput(sprintf('%s is given as JSON, and "%s" isn\'t JSON.', $name, $value));
		}
	}

	/**
	 * The tool's input fields, with their descriptions, for a tool run
	 * without input.
	 *
	 * @return array<string, string>
	 */
	private static function fields(Tool $tool): array
	{
		$schema     = $tool->input();
		$properties = is_array($schema['properties'] ?? null) ? $schema['properties'] : [];
		$required   = is_array($schema['required'] ?? null) ? $schema['required'] : [];
		$fields     = [];

		foreach ($properties as $name => $property) {
			$property = is_array($property) ? $property : [];
			$type     = is_string($property['type'] ?? null) ? $property['type'] : 'text';
			$about    = is_string($property['description'] ?? null) ? " {$property['description']}" : '';

			$fields[(string) $name] = sprintf('%s%s.%s', $type, in_array($name, $required, true) ? ', required' : '', $about);
		}

		return $fields;
	}

	/**
	 * Writes data as JSON.
	 *
	 * @param array<string, mixed> $data
	 */
	private static function encode(array $data): string
	{
		try {
			return json_encode((object) $data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		} catch (JsonException) {
			return '{}';
		}
	}
}
