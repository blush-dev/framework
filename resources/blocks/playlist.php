<?php

/**
 * Playlist block (`Block\Media\Playlist`, D-708): one audio
 * player over a numbered list of its `::audio` tracks, each a row (the
 * audio template draws them). The player starts with the first; the
 * site's `<blush-playlist>` plays the row chosen, then the next. The
 * `card` variant draws the player as the audio card: the playing
 * track's artwork beside its title and who made it. Without rows (a
 * theme's audio template that doesn't draw them), it's its content.
 *
 *     :::playlist[Live at the Basement]{variant=card loop}
 *     ::audio{src=intro.mp3}
 *     ::audio{src=river-song.mp3}
 *     :::
 *
 * @var Blush\Translation\Messages     $messages
 * @var Blush\Block\Media\Playlist $block
 */

declare(strict_types=1);

use Blush\Support\Bem;

// The playlist and the audio card are blocks of their own that `audio`
// and `playlist` share (D-575, D-708).
$playlist = new Bem('playlist');
$card     = new Bem('audio-card');

$first = $block->firstTrack();

?>
<?php if ($first === null) : ?>
<div <?= $block->attributes() ?>>
<?= raw($block->content()) ?>
</div>
<?php else : ?>
<figure <?= $block->attributes(['class' => $playlist->block()]) ?>>
	<?php if (trim($block->label) !== '') : ?>
		<figcaption class="<?= attr($playlist->element('caption')) ?>"><?= e(trim($block->label)) ?></figcaption>
	<?php endif ?>
	<blush-playlist<?= $block->loop ? ' loop' : '' ?>>
		<?php if ($block->isVariant('card')) : ?>
			<div class="<?= attr($card->block()->with($playlist->element('card'))) ?>">
				<div class="<?= attr($card->element('meta')) ?>">
					<span class="<?= attr($card->element('title')) ?>"><?= raw($first->title) ?></span>
					<span class="<?= attr($card->element('by')) ?>"<?= $first->by === '' ? ' hidden' : '' ?>><?= e($first->by) ?></span>
				</div>
				<?php if ($first->art !== '') : ?>
					<img class="<?= attr($card->element('art')) ?>" src="<?= url($first->art) ?>" alt="" loading="lazy" decoding="async">
				<?php endif ?>
				<blush-audio-player>
					<audio <?= $block->playerAttributes() ?>>
						<a href="<?= url($first->src) ?>"><?= e($messages->t('media.audio_fallback')) ?></a>
					</audio>
				</blush-audio-player>
			</div>
		<?php else : ?>
			<blush-audio-player>
				<audio <?= $block->playerAttributes() ?>>
					<a href="<?= url($first->src) ?>"><?= e($messages->t('media.audio_fallback')) ?></a>
				</audio>
			</blush-audio-player>
		<?php endif ?>
		<ol class="<?= attr($block->listClasses()) ?>">
<?= raw($block->content()) ?>
		</ol>
	</blush-playlist>
</figure>
<?php endif ?>
