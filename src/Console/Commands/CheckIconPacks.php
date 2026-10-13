<?php

/**
 * Icon pack check command.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Console\Commands;

use Blush\Console\Attributes\Argument;
use Blush\Console\Attributes\Command;
use Blush\Console\ExitCode;
use Blush\Console\Outcome;
use Blush\Console\Output;
use Blush\Console\StatusLine;
use Blush\Extension\ExtensionAbandoned;
use Blush\Extension\ExtensionState;
use Blush\Extension\ExtensionText;
use Blush\Extension\Requirements;
use Blush\Extension\VersionConstraint;
use Blush\Field\Severity;
use Blush\Field\Violation;
use Blush\Icon\IconPack;
use Blush\Icon\IconPacks;
use Blush\Translation\CatalogCheck;
use Blush\Translation\SourceCatalog;
use Blush\Translation\TranslationException;

/**
 * Checks every installed icon pack (or one, by name), as `plugin:check`
 * checks plugins (D-431): that its manifest can be read, its `require`
 * is met (checking one that's off as if it were on), and its `version`
 * is one Composer can read. A pack that's on but can't load is an error,
 * which fails the command; one that's off and couldn't be turned on, a
 * broken one, an unreadable version, an abandoned pack (D-433), a
 * `lang/` catalog whose `@@locale` or `@@domain` doesn't match (D-452),
 * and manifest text `lang/en.json` has otherwise (D-824) are warnings.
 */
#[Command('icon-pack:check', 'Check icon packs\' manifests and requirements.')]
final readonly class CheckIconPacks
{
	public function __construct(
		private IconPacks $packs,
		private ExtensionState $extensions,
		private CatalogCheck $catalogs,
		private SourceCatalog $sourceCatalog
	) {}

	public function __invoke(
		Output $output,
		#[Argument('The pack\'s name (vendor/name); defaults to every pack.')] ?string $name = null
	): ExitCode {
		$packs  = array_filter($this->packs->all(), static fn (IconPack $pack): bool => $name === null || $pack->name === $name);
		$broken = array_filter($this->packs->invalid(), static fn (string $reason, string $where): bool => $name === null || $where === $name || str_ends_with($where, "/{$name}"), ARRAY_FILTER_USE_BOTH);

		if ($name !== null && $packs === [] && $broken === []) {
			$output->error(sprintf('No icon pack named "%s" is installed.', $name));

			return ExitCode::Failure;
		}

		$lines = [];

		foreach ($packs as $pack) {
			$checked = $this->extensions->check($pack);

			// Its catalogs against what they say they translate (D-452);
			// notices (a catalog without them) are `theme:check --strict`'s.
			$catalogs = array_filter($this->catalogs->check("{$pack->path}/lang", $pack->name), static fn (Violation $violation): bool => $violation->severity !== Severity::Notice);

			// Its manifest's text against lang/en.json (D-824); a catalog
			// that can't be read is reported above.
			try {
				$catalogs = [...$catalogs, ...$this->sourceCatalog->compare(ExtensionText::extracted($pack), SourceCatalog::path("{$pack->path}/lang"))];
			} catch (TranslationException) {
				// Reported by the catalog check.
			}

			foreach ($catalogs as $violation) {
				$lines[] = new StatusLine(Outcome::of($violation->severity), $pack->name, "{$violation->field} {$violation->message}");
			}

			$abandoned = ExtensionAbandoned::warning($pack->abandoned);

			// An abandoned pack still loads, as in Composer (D-433).
			if ($abandoned !== null) {
				$lines[] = new StatusLine(Outcome::Warning, $pack->name, $abandoned);
			}

			// A version Composer can't read meets only `*` (D-429, D-430).
			if ($pack->version !== '' && VersionConstraint::normalize($pack->version) === null) {
				$lines[] = new StatusLine(Outcome::Warning, $pack->name, sprintf('Its version, "%s", isn\'t one Composer can read, so a requirement of it is met only by "*".', $pack->version));
			} elseif ($abandoned === null && $catalogs === [] && Requirements::met($checked)) {
				$lines[] = new StatusLine(Outcome::Pass, $pack->name, $pack->version);
			}

			if (Requirements::met($checked)) {
				continue;
			}

			$on      = $this->packs->isEnabled($pack->name);
			$lines[] = new StatusLine($on ? Outcome::Failure : Outcome::Warning, $pack->name, Requirements::reason($checked) . ($on ? ' It\'s turned on, but its icons don\'t load.' : ''));
		}

		foreach ($broken as $where => $reason) {
			$lines[] = new StatusLine(Outcome::Warning, $where, $reason);
		}

		$output->status($lines);
		$output->summary(
			Outcome::worst(...array_map(static fn (StatusLine $line): Outcome => $line->outcome, $lines)),
			Output::count(count($packs) + count($broken), 'icon pack', 'icon packs'),
			...Output::problems(self::count($lines, Outcome::Failure), self::count($lines, Outcome::Warning))
		);

		return array_any($lines, static fn (StatusLine $line): bool => $line->outcome === Outcome::Failure) ? ExitCode::Failure : ExitCode::Success;
	}

	/**
	 * Counts the rows with an outcome.
	 *
	 * @param list<StatusLine> $lines
	 */
	private static function count(array $lines, Outcome $outcome): int
	{
		return count(array_filter($lines, static fn (StatusLine $line): bool => $line->outcome === $outcome));
	}
}
