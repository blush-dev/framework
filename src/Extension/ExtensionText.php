<?php

/**
 * Extension text.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Extension;

use Blush\Support\RequestMemo;
use Blush\Theme\ThemeManifest;
use Blush\Translation\CurrentLocale;
use Blush\Translation\ExtractedMessage;
use Blush\Translation\Text;
use Blush\Translation\Translator;

/**
 * The text an extension's manifest shows people (D-824), translated from
 * the extension's own `lang/` catalog under `manifest.` keys, with the
 * manifest's text when the catalog has none:
 *
 * - `manifest.label` and `manifest.description`, for every kind;
 * - `manifest.menus.{location}`, a theme's menu location labels;
 * - `manifest.settings.{setting}.label` and `.description`, a theme's
 *   settings.
 *
 * Each is a `Text`, translated where it's shown (D-816), so a list of
 * themes or a menu's name prints in the language of the screen or page.
 * It's looked up in the extension's own catalog whether it's running or
 * not, so an inactive theme's and a turned-off plugin's labels translate
 * too. `lang:extract` writes these keys from the manifest
 * (`extracted()`), and the checks compare them, as they do code's text.
 */
final readonly class ExtensionText
{
	/**
	 * The prefix of every manifest key, kept for manifests in catalogs.
	 */
	public const string PREFIX = 'manifest.';

	public function __construct(
		private Translator $translator,
		private CurrentLocale $locale,
		private RequestMemo $memo = new RequestMemo()
	) {}

	/**
	 * Returns an extension's label.
	 */
	public function label(ExtensionManifest $extension): Text
	{
		return $this->text($extension, self::PREFIX . 'label', $extension->label);
	}

	/**
	 * Returns an extension's description (empty when it has none).
	 */
	public function description(ExtensionManifest $extension): Text
	{
		return $this->text($extension, self::PREFIX . 'description', $extension->description);
	}

	/**
	 * Returns the label of a menu location a theme declares.
	 */
	public function menu(ThemeManifest $theme, string $location, string $label): Text
	{
		return $this->text($theme, self::PREFIX . "menus.{$location}", $label);
	}

	/**
	 * Returns a theme setting's `label` or `description`.
	 *
	 * @param 'label'|'description' $part
	 */
	public function setting(ThemeManifest $theme, string $setting, string $part, string $text): Text
	{
		return $this->text($theme, self::PREFIX . "settings.{$setting}.{$part}", $text);
	}

	/**
	 * Returns the messages an extension's manifest writes, for
	 * `lang:extract` and the checks: what isn't empty, by key.
	 *
	 * @return array<string, ExtractedMessage>
	 */
	public static function extracted(ExtensionManifest $extension): array
	{
		$file  = $extension->kind()->manifest() . '.json';
		$kind  = match ($extension->kind()) {
			ExtensionKind::Plugin     => 'plugin',
			ExtensionKind::Theme      => 'theme',
			ExtensionKind::IconPack   => 'icon pack',
			ExtensionKind::AdminTheme => 'admin theme'
		};
		$texts = [
			self::PREFIX . 'label'       => [$extension->label, "The {$kind}'s name"],
			self::PREFIX . 'description' => [$extension->description, "What the {$kind} is, in a sentence"]
		];

		if ($extension instanceof ThemeManifest) {
			foreach ($extension->menus() as $location => $declaration) {
				$texts[self::PREFIX . "menus.{$location}"] = [is_string($declaration) ? $declaration : $declaration['label'] ?? '', 'The name of a place the theme shows a menu'];
			}

			foreach ($extension->settings() as $setting => $definition) {
				$texts[self::PREFIX . "settings.{$setting}.label"]       = [$definition['label'] ?? '', 'A theme setting\'s label'];
				$texts[self::PREFIX . "settings.{$setting}.description"] = [$definition['description'] ?? '', 'A theme setting\'s help text'];
			}
		}

		$messages = [];

		foreach ($texts as $key => [$text, $context]) {
			if (is_string($text) && trim($text) !== '') {
				$messages[$key] = new ExtractedMessage($key, trim($text), $context, $file, 1);
			}
		}

		return $messages;
	}

	/**
	 * Returns text in an extension's domain, read from its own catalog.
	 * It's made here rather than with `Messages::t()`: its key and text
	 * come from the manifest, which `lang:extract` reads for itself.
	 */
	private function text(ExtensionManifest $extension, string $key, string $text): Text
	{
		$translator = $this->memo->remember(
			"extension-text|{$extension->name}|{$extension->path}",
			fn (): Translator => $this->translator->withDomains([$extension->name => ["{$extension->path}/lang"]])
		);

		return new Text($key, trim($text), $extension->name, translator: $translator, locale: $this->locale);
	}
}
