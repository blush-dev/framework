<?php

/**
 * Admin theme check command.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Console\Commands;

use Blush\Admin\Theme\AdminTheme;
use Blush\Admin\Theme\AdminThemeContrast;
use Blush\Admin\Theme\AdminThemes;
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
use Blush\Translation\CatalogCheck;
use Blush\Translation\SourceCatalog;
use Blush\Translation\TranslationException;
use Blush\Translation\Text;

/**
 * Checks every installed admin theme (or one, by name), as
 * `icon-pack:check` checks icon packs (D-431, D-827): that its manifest
 * and `tokens.json` can be read, its `require` is met (checking one
 * that's off as if it were on), its `version` is one Composer can read,
 * and its colors' contrast, in both color schemes, with what it leaves
 * out being Neutral's. A theme that's on but can't be offered is an
 * error, which fails the command; one that's off and couldn't be turned
 * on, a broken one (its tokens included), an unreadable version, an
 * abandoned theme (D-433), low contrast, a `lang/` catalog whose
 * `@@locale` or `@@domain` doesn't match (D-452), and manifest text
 * `lang/en.json` has otherwise (D-824) are warnings.
 */
#[Command('admin-theme:check', 'Check admin themes\' manifests, tokens, requirements, and contrast.')]
final readonly class CheckAdminThemes
{
	public function __construct(
		private AdminThemes $themes,
		private ExtensionState $extensions,
		private CatalogCheck $catalogs,
		private SourceCatalog $sourceCatalog,
		private AdminThemeContrast $contrast
	) {}

	public function __invoke(
		Output $output,
		#[Argument('The theme\'s name (vendor/name); defaults to every admin theme.')] ?string $name = null
	): ExitCode {
		$themes = array_filter($this->themes->all(), static fn (AdminTheme $theme): bool => $name === null || $theme->name === $name);
		$broken = array_filter($this->themes->invalid(), static fn (string|Text $reason, string $where): bool => $name === null || $where === $name || str_ends_with($where, "/{$name}"), ARRAY_FILTER_USE_BOTH);

		if ($name !== null && $themes === [] && $broken === []) {
			$output->error(sprintf('No admin theme named "%s" is installed.', $name));

			return ExitCode::Failure;
		}

		$lines = [];

		foreach ($themes as $theme) {
			$checked = $this->extensions->check($theme);

			// Its catalogs against what they say they translate (D-452);
			// notices (a catalog without them) are `theme:check --strict`'s.
			$catalogs = array_filter($this->catalogs->check("{$theme->path}/lang", $theme->name), static fn (Violation $violation): bool => $violation->severity !== Severity::Notice);

			// Its manifest's text against lang/en.json (D-824); a catalog
			// that can't be read is reported above.
			try {
				$catalogs = [...$catalogs, ...$this->sourceCatalog->compare(ExtensionText::extracted($theme), SourceCatalog::path("{$theme->path}/lang"))];
			} catch (TranslationException) {
				// Reported by the catalog check.
			}

			foreach ($catalogs as $violation) {
				$lines[] = new StatusLine(Outcome::of($violation->severity), $theme->name, "{$violation->field} {$violation->message}");
			}

			$contrast = $this->contrast->check($this->themes->resolved($theme));

			foreach ($contrast as $warning) {
				$lines[] = new StatusLine(Outcome::Warning, $theme->name, (string) $warning);
			}

			$abandoned = ExtensionAbandoned::warning($theme->abandoned);

			// An abandoned theme is still offered, as in Composer (D-433).
			if ($abandoned !== null) {
				$lines[] = new StatusLine(Outcome::Warning, $theme->name, (string) $abandoned);
			}

			// A version Composer can't read meets only `*` (D-429, D-430).
			if ($theme->version !== '' && VersionConstraint::normalize($theme->version) === null) {
				$lines[] = new StatusLine(Outcome::Warning, $theme->name, sprintf('Its version, "%s", isn\'t one Composer can read, so a requirement of it is met only by "*".', $theme->version));
			} elseif ($abandoned === null && $catalogs === [] && $contrast === [] && Requirements::met($checked)) {
				$lines[] = new StatusLine(Outcome::Pass, $theme->name, $theme->version);
			}

			if (Requirements::met($checked)) {
				continue;
			}

			$on      = $this->themes->isEnabled($theme->name);
			$lines[] = new StatusLine($on ? Outcome::Failure : Outcome::Warning, $theme->name, Requirements::reason($checked) . ($on ? ' It\'s turned on, but no one can choose it.' : ''));
		}

		foreach ($broken as $where => $reason) {
			$lines[] = new StatusLine(Outcome::Warning, $where, (string) $reason);
		}

		$output->status($lines);
		$output->summary(
			Outcome::worst(...array_map(static fn (StatusLine $line): Outcome => $line->outcome, $lines)),
			Output::count(count($themes) + count($broken), 'admin theme', 'admin themes'),
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
