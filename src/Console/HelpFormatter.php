<?php

/**
 * Help formatter.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Console;

use BackedEnum;
use Blush\Console\Input\InputDefinition;
use Blush\Console\Input\OptionDefinition;
use Blush\Console\Input\Signature;
use Blush\Console\Input\ValueType;
use Blush\Core\AppConfig;
use Blush\Core\Framework;

/**
 * Writes the command list and per-command help, all generated from command
 * signatures, from `Output`'s shared pieces (D-717). A command's help
 * leaves out the global options, which `help --global` shows.
 */
final readonly class HelpFormatter
{
	public function __construct(
		private AppConfig $app
	) {}

	/**
	 * Writes help for one command.
	 */
	public function command(Signature $signature, Output $output): void
	{
		if ($signature->description !== '') {
			$output->line($signature->description);
		}

		$output->section('Usage');
		$output->line('  ' . $output->code(Framework::BINARY . ' ' . $signature->usage()));

		foreach ($signature->aliases as $alias) {
			$output->line('  ' . $output->code(Framework::BINARY . " {$alias}"));
		}

		$arguments = $signature->definition->arguments;

		if ($arguments !== []) {
			$rows = [];

			foreach ($arguments as $argument) {
				$rows[$argument->name] = $argument->description . $this->defaultHint($argument->required || $argument->variadic ? null : $argument->default, $argument->enum);
			}

			$output->section('Arguments');
			$output->definitions($rows, code: true);
		}

		if ($signature->definition->options() !== []) {
			$output->section('Options');
			$output->definitions($this->options($signature->definition), code: true);
		}

		$output->next('Run `' . Framework::BINARY . ' help --global` for the options every command takes.');
	}

	/**
	 * Writes the options every command takes.
	 */
	public function globals(Output $output): void
	{
		$output->line('Every command takes these options.');
		$output->section('Global options');
		$output->definitions($this->options(GlobalOptions::definition()), code: true);
	}

	/**
	 * Writes the list of visible commands, grouped by namespace (the part
	 * of the name before `:`), after the framework's version and the
	 * site's name and environment.
	 */
	public function commands(CommandRegistry $commands, Output $output): void
	{
		$site = $this->app->environment->value;
		$site = $this->app->name === '' || $this->app->name === Framework::NAME ? $site : "{$this->app->name} ({$site})";

		$output->line($output->join($output->tone(Framework::NAME . ' ' . Framework::VERSION, Tone::Heading), $site));

		$output->section('Usage');
		$output->line('  ' . $output->code(Framework::BINARY . ' <command> [options] [arguments]'));

		$groups = [];

		foreach ($commands->signatures() as $name => $signature) {
			if (! $signature->hidden) {
				$group = str_contains($name, ':') ? strstr($name, ':', true) : '';
				$groups[$group][$name] = $signature->description;
			}
		}

		ksort($groups);

		$width = array_merge(...array_values($groups))
			|> array_keys(...)
			|> (static fn (array $labels): array => array_map(mb_strwidth(...), $labels))
			|> (static fn (array $widths): int => max(0, ...$widths));

		foreach ($groups as $group => $rows) {
			$output->section($group === '' ? 'General' : ucfirst((string) $group));
			$output->definitions($rows, code: true, align: $width);
		}

		$output->next('Run `' . Framework::BINARY . ' help <command>` for a command\'s options, or `' . Framework::BINARY . ' help --global` for the ones every command takes.');
	}

	/**
	 * Returns the rows for a set of options.
	 *
	 * @return array<string, string>
	 */
	private function options(InputDefinition $definition): array
	{
		$rows = [];

		foreach ($definition->options() as $option) {
			$rows[$option->usage()] = $option->description . $this->optionHint($option);
		}

		return $rows;
	}
	/**
	 * Returns the default-value hint for an option.
	 */
	private function optionHint(OptionDefinition $option): string
	{
		return $option->acceptsValue() && ! $option->list ? $this->defaultHint($option->default, $option->enum) : '';
	}

	/**
	 * Returns a hint listing an enum's values and a default value.
	 *
	 * @param ?class-string<BackedEnum> $enum
	 */
	private function defaultHint(mixed $default, ?string $enum): string
	{
		$hint = $enum === null ? '' : ' [one of: ' . implode(', ', ValueType::enumValues($enum)) . ']';

		if ($default instanceof BackedEnum) {
			$default = $default->value;
		}

		return is_scalar($default) ? $hint . sprintf(' [default: %s]', var_export($default, true)) : $hint;
	}
}
