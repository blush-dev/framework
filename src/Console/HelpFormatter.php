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
use Blush\Core\Framework;

/**
 * Writes the command list and per-command help, all generated from command
 * signatures.
 */
final readonly class HelpFormatter
{
	/**
	 * Writes help for one command.
	 */
	public function command(Signature $signature, Output $output): void
	{
		if ($signature->description !== '') {
			$this->heading('Description:', $output);
			$output->line("  {$signature->description}");
			$output->newLine();
		}

		$this->heading('Usage:', $output);
		$output->line('  ' . Framework::BINARY . ' ' . $signature->usage());

		foreach ($signature->aliases as $alias) {
			$output->line('  ' . Framework::BINARY . " {$alias}");
		}

		$arguments = $signature->definition->arguments;

		if ($arguments !== []) {
			$output->newLine();
			$this->heading('Arguments:', $output);

			$rows = [];

			foreach ($arguments as $argument) {
				$rows[$argument->name] = $argument->description . $this->defaultHint($argument->required || $argument->variadic ? null : $argument->default, $argument->enum);
			}

			$this->rows($rows, $output);
		}

		if ($signature->definition->options() !== []) {
			$output->newLine();
			$this->heading('Options:', $output);
			$this->options($signature->definition, $output);
		}

		$output->newLine();
		$this->heading('Global options:', $output);
		$this->options(GlobalOptions::definition(), $output);
	}

	/**
	 * Writes the list of visible commands, grouped by namespace (the part
	 * of the name before `:`).
	 */
	public function commands(CommandRegistry $commands, Output $output): void
	{
		$output->line(Framework::NAME . ' ' . $output->style(Framework::VERSION, Style::Green));
		$output->newLine();

		$this->heading('Usage:', $output);
		$output->line('  ' . Framework::BINARY . ' <command> [options] [arguments]');
		$output->newLine();

		$this->heading('Options:', $output);
		$this->options(GlobalOptions::definition(), $output);
		$output->newLine();

		$this->heading('Available commands:', $output);

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
			if ($group !== '') {
				$output->line(' ' . $output->style((string) $group, Style::Yellow));
			}

			$this->rows($rows, $output, $width);
		}
	}

	/**
	 * Writes a section heading.
	 */
	private function heading(string $text, Output $output): void
	{
		$output->line($output->style($text, Style::Yellow));
	}

	/**
	 * Writes the rows for a set of options.
	 */
	private function options(InputDefinition $definition, Output $output): void
	{
		$rows = [];

		foreach ($definition->options() as $option) {
			$rows[$option->usage()] = $option->description . $this->optionHint($option);
		}

		$this->rows($rows, $output);
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

	/**
	 * Writes aligned `label  description` rows.
	 *
	 * @param array<string, string> $rows
	 */
	private function rows(array $rows, Output $output, ?int $width = null): void
	{
		$width ??= max(0, ...array_map(mb_strwidth(...), array_map(strval(...), array_keys($rows))));

		foreach ($rows as $label => $description) {
			$label = (string) $label;
			$pad   = str_repeat(' ', $width - mb_strwidth($label) + 2);

			$output->line('  ' . $output->style($label, Style::Green) . $pad . trim($description));
		}
	}
}
