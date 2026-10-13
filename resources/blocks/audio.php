<?php

/**
 * Audio block (`Block\Media\Audio`): an audio file in the site's
 * player (D-573), with the browser's controls until its script loads,
 * and the label as its caption. The `card` variant (D-575) is a card:
 * the artwork beside the title and who made it, over the player. In a
 * playlist (D-708), it's a row of the playlist's list: the title, who
 * made it, and the length, linked to the file, with the artwork's URL
 * in `data-art` for the playlist's card.
 *
 *     ::audio[Episode 12]{src=episode.mp3}
 *     ::audio{src=song.mp3 variant=card}
 *
 * @var Blush\Translation\Messages  $messages
 * @var Blush\Block\Media\Audio $block
 */

declare(strict_types=1);

use Blush\Support\Bem;

// The playlist's rows and the audio card are blocks of their own that
// `audio` and `playlist` share (D-575, D-708).
$playlist = new Bem('playlist');
$card     = new Bem('audio-card');

?>
<?php if ($block->isIn('playlist')) : ?>
<li <?= $block->attributes(['class' => $playlist->element('track'), 'data-art' => $block->artwork()]) ?>>
	<a class="<?= attr($playlist->element('link')) ?>" href="<?= url($block->src) ?>">
		<span class="<?= attr($playlist->element('title')) ?>"><?= raw($block->trackName()) ?></span>
		<?php if ($block->trackBy() !== '') : ?>
			<span class="<?= attr($playlist->element('by')) ?>"><?= e($block->trackBy()) ?></span>
		<?php endif ?>
		<?php if ($block->trackLength() !== '') : ?>
			<span class="<?= attr($playlist->element('time')) ?>"><?= e($block->trackLength()) ?></span>
		<?php endif ?>
	</a>
</li>
<?php elseif ($block->isVariant('card')) : ?>
<figure <?= $block->attributes(['class' => $card->block()]) ?>>
	<?php if ($block->trackTitle() !== '' || $block->trackBy() !== '') : ?>
		<figcaption class="<?= attr($card->element('meta')) ?>">
			<?php if ($block->trackTitle() !== '') : ?>
				<span class="<?= attr($card->element('title')) ?>"><?= raw($block->trackTitle()) ?></span>
			<?php endif ?>
			<?php if ($block->trackBy() !== '') : ?>
				<span class="<?= attr($card->element('by')) ?>"><?= e($block->trackBy()) ?></span>
			<?php endif ?>
		</figcaption>
	<?php endif ?>
	<?php if ($block->artwork() !== '') : ?>
		<img class="<?= attr($card->element('art')) ?>" src="<?= url($block->artwork()) ?>" alt="" loading="lazy" decoding="async">
	<?php endif ?>
	<blush-audio-player <?= $block->labelAttributes() ?>>
		<audio <?= $block->playerAttributes() ?>>
			<a href="<?= url($block->src) ?>"><?= e($messages->t('media.audio_fallback')) ?></a>
		</audio>
	</blush-audio-player>
</figure>
<?php else : ?>
<figure <?= $block->attributes() ?>>
	<blush-audio-player <?= $block->labelAttributes() ?>>
		<audio <?= $block->playerAttributes() ?>>
			<a href="<?= url($block->src) ?>"><?= e($messages->t('media.audio_fallback')) ?></a>
		</audio>
	</blush-audio-player>
	<?php if ($block->caption() !== '') : ?>
		<figcaption><?= raw($block->caption()) ?></figcaption>
	<?php endif ?>
</figure>
<?php endif ?>
