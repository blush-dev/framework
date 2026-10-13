<?php

/**
 * Playlist block.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Block\Media;

use Dom\HTMLDocument;
use Override;
use Blush\Asset\AssetRegistrar;
use Blush\Core\Framework;
use Blush\Block\Block;
use Blush\Block\BlockContent;
use Blush\Block\BlockKind;
use Blush\Block\BlockView;
use Blush\Support\Bem;
use Blush\Support\BemClass;

/**
 * Plays its `::audio` tracks one after another in one player (D-708),
 * over a numbered list of them. Each track renders as a row of the list
 * (the audio block's template, when it's in a playlist): its title,
 * who made it, and its length, its link the file. Choosing a row plays
 * it, and the next plays when one ends; `loop` starts over after the
 * last. `numbers=false` leaves out the track numbers. The label is the
 * playlist's caption.
 *
 * The `card` variant draws the player as the audio card (D-575): the
 * playing track's artwork beside its title and who made it. Without
 * its script (`<blush-playlist>`, in the site's player asset), the
 * player plays the first track and each row links to its file.
 *
 *     :::playlist[Live at the Basement]{variant=card}
 *     ::audio{src=intro.mp3}
 *     ::audio{src=river-song.mp3 title="River Song (acoustic)"}
 *     :::
 */
final class Playlist extends Block
{
	use PlayerLabels;

	/**
	 * The BEM block its rows and list share with `audio` (D-708), beside
	 * its own `block-playlist`.
	 */
	private const string SHARED = 'playlist';

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
	public const BlockContent CONTENT = BlockContent::Blocks;

	/**
	 * @inheritDoc
	 */
	public const ?BlockKind KIND = BlockKind::Container;

	/**
	 * @inheritDoc
	 */
	public const array HOLDS = ['core/audio'];

	/**
	 * The first track, once read (`false` for none).
	 */
	private PlaylistTrack|false|null $first = null;

	public function __construct(
		public readonly bool $loop = false,
		public readonly bool $numbers = true,
		public readonly string $label = ''
	) {}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function shouldRender(): bool
	{
		return trim($this->content()) !== '';
	}

	/**
	 * Returns the first track, which the player starts with, read from
	 * its row, or `null` when it has no rows (a theme's audio template
	 * that doesn't draw them, say).
	 */
	public function firstTrack(): ?PlaylistTrack
	{
		if ($this->first === null) {
			$this->first = $this->readFirst() ?? false;
		}

		return $this->first === false ? null : $this->first;
	}

	/**
	 * Returns the list's classes: `playlist__tracks`, with
	 * `playlist__tracks--plain` when it has no numbers.
	 */
	public function listClasses(): BemClass
	{
		return new Bem(self::SHARED)->element('tracks')->when(! $this->numbers, 'plain');
	}

	/**
	 * Returns the `<audio>` element's attributes, escaped: its class
	 * (`block-playlist__player`), the first track's `src`, the
	 * browser's controls, and `preload` (`metadata`).
	 */
	public function playerAttributes(): string
	{
		return self::html([
			'class'    => $this->elementClass('player'),
			'src'      => $this->firstTrack()->src ?? '',
			'controls' => true,
			'preload'  => MediaPreload::Metadata->value
		]);
	}

	/**
	 * Reads the first row's track: its link, title, who made it, and its
	 * artwork (`data-art`).
	 */
	private function readFirst(): ?PlaylistTrack
	{
		$document = HTMLDocument::createFromString('<!DOCTYPE html><meta charset="utf-8"><body>' . $this->content() . '</body>', LIBXML_NOERROR);
		$rows     = new Bem(self::SHARED);
		$row      = $document->querySelector('.' . $rows->element('track')->base);
		$link     = $row?->querySelector('a[href]');

		if ($row === null || $link === null) {
			return null;
		}

		return new PlaylistTrack(
			src: $link->getAttribute('href') ?? '',
			title: trim($row->querySelector('.' . $rows->element('title')->base)->innerHTML ?? ''),
			by: trim($row->querySelector('.' . $rows->element('by')->base)->textContent ?? ''),
			art: $row->getAttribute('data-art') ?? ''
		);
	}

	/**
	 * Renders the framework's template for it, `resources/blocks/playlist.php`,
	 * when the theme chain has none of its own.
	 */
	#[Override]
	public function render(): BlockView
	{
		return $this->view(Framework::path('resources/blocks/playlist.php'));
	}
}
