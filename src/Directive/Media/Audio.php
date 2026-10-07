<?php

/**
 * Audio directive.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Directive\Media;

use Override;
use Blush\Asset\AssetRegistrar;
use Blush\Core\AppConfig;
use Blush\Directive\Directive;
use Blush\Directive\DirectiveContent;
use Blush\Directive\DirectiveView;
use Blush\Directive\MediaProp;
use Blush\Core\Framework;
use Blush\Markdown\MarkdownConfig;
use Blush\Media\Index\MediaRecord;
use Blush\Media\MediaArtwork;
use Blush\Media\MediaKind;
use Blush\Directive\DirectiveKind;
use Blush\View\Escaper;

/**
 * Plays an audio file with the browser's controls (D-175, D-179):
 * `::audio[A caption]{src=episode.mp3}`. `src` is resolved like an
 * image's (a file next to the entry, or in the media folder), and the
 * label is the caption. `preload` is `metadata` by default; `loop`
 * repeats it. It plays in the site's audio player (D-573), which keeps
 * the browser's controls until its script loads.
 *
 * The `card` variant (D-575) draws it as a card: the artwork beside the
 * title and who made it, over the player. Each is a prop first, then
 * the file's: `title` (else the label, else the title in its metadata
 * file, else the one it carries), `artist` and `album` (else what it
 * carries), and `art`, an image (else the file's artwork: the library
 * image it names, D-581, else the picture it carries, served at
 * `MediaConfig::artworkUrl()`; `MediaArtwork`).
 *
 *     ::audio[Episode 12]{src=episode.mp3 variant=card}
 */
final class Audio extends Directive
{
	use PlayerLabels;

	/**
	 * The player's labels (`PlayerLabels`).
	 *
	 * @var list<string>
	 */
	private const array LABELS = ['play', 'pause', 'seek', 'mute', 'unmute', 'volume'];

	/**
	 * @inheritDoc
	 */
	public const array VARIANTS = ['card'];

	/**
	 * @inheritDoc
	 */
	protected const array ASSETS = [AssetRegistrar::PLAYER];
	/**
	 * @inheritDoc
	 */
	public const DirectiveContent CONTENT = DirectiveContent::Text;

	/**
	 * @inheritDoc
	 */
	public const ?DirectiveKind KIND = DirectiveKind::Leaf;

	/**
	 * The file's record in the media library, once looked up (`false`
	 * for none).
	 */
	private MediaRecord|false|null $record = null;

	public function __construct(
		private readonly MediaArtwork $artworks,
		private readonly AppConfig $app,
		private readonly MarkdownConfig $markdown,
		#[MediaProp(MediaKind::Audio)] public readonly string $src = '',
		public readonly MediaPreload $preload = MediaPreload::Metadata,
		public readonly bool $loop = false,
		public readonly string $title = '',
		public readonly string $artist = '',
		public readonly string $album = '',
		#[MediaProp(MediaKind::Image)] public readonly string $art = '',
		public readonly string $label = ''
	) {}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function shouldRender(): bool
	{
		return $this->src !== '';
	}

	/**
	 * Returns the caption, as HTML: the content, else the label escaped.
	 */
	public function caption(): string
	{
		return $this->contentOr($this->label);
	}

	/**
	 * Returns a card's title, as HTML: the `title` prop, else the
	 * caption, else the file's title (its metadata file's, then the one
	 * it carries), escaped.
	 */
	public function trackTitle(): string
	{
		$title = trim($this->title);

		if ($title === '') {
			$caption = $this->caption();

			if ($caption !== '') {
				return $caption;
			}

			$record = $this->record();
			$title  = $record === null ? '' : $record->metadata()->title;
			$title  = $title !== '' ? $title : $this->carried('title');
		}

		return Escaper::html($title);
	}

	/**
	 * Returns who made it and from what, for a card: the `artist` and
	 * `album` props, else what the file carries, as one line
	 * ("Artist · Album").
	 */
	public function trackBy(): string
	{
		$artist = trim($this->artist);
		$album  = trim($this->album);

		return implode(' · ', array_filter([
			$artist !== '' ? $artist : $this->carried('creator'),
			$album !== '' ? $album : $this->carried('album')
		], static fn (string $part): bool => $part !== ''));
	}

	/**
	 * Returns a card's artwork URL: the `art` prop, else the file's
	 * artwork (`MediaArtwork::url()`: its library image, else the picture
	 * it carries), a full URL when Markdown's links are, as the `src` is,
	 * else `''`.
	 */
	public function artwork(): string
	{
		if ($this->art !== '') {
			return $this->art;
		}

		$record = $this->record();
		$url    = $record === null ? '' : $this->artworks->url($record);

		return $url !== '' && $this->markdown->absoluteLinks ? $this->app->absoluteUrl($url) : $url;
	}

	/**
	 * Returns the `<audio>` element's attributes, escaped: its class
	 * (`directive-audio__player`), `src`, the browser's controls,
	 * `preload`, and `loop`.
	 */
	public function playerAttributes(): string
	{
		return self::html([
			'class'    => $this->block() . '__player',
			'src'      => $this->src,
			'controls' => true,
			'preload'  => $this->preload->value,
			'loop'     => $this->loop
		]);
	}

	/**
	 * Returns a value the file carries (D-289), as text: a list is
	 * joined with commas.
	 */
	private function carried(string $key): string
	{
		$value = $this->record()?->embedded?->values[$key] ?? null;

		return match (true) {
			is_string($value)                => trim($value),
			is_int($value), is_float($value) => (string) $value,
			is_array($value)                 => implode(', ', array_filter($value, is_string(...))),
			default                          => ''
		};
	}

	/**
	 * Returns the file's record in the media library, or `null` when it
	 * isn't in `user/media` or the library can't be read.
	 */
	private function record(): ?MediaRecord
	{
		if ($this->record === null) {
			$this->record = $this->artworks->record($this->src) ?? false;
		}

		return $this->record === false ? null : $this->record;
	}

	/**
	 * Renders the framework's template for it, `resources/directives/audio.php`
	 * (D-382), when the theme chain has none of its own.
	 */
	#[Override]
	public function render(): DirectiveView
	{
		return $this->view(Framework::path('resources/directives/audio.php'));
	}
}
