<?php

/**
 * Audio directive (`Directive\Media\Audio`): an audio file in the site's
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
 * @var Blush\Directive\Media\Audio $directive
 */

declare(strict_types=1);

?>
<?php if ($directive->isIn('playlist')) : ?>
<li <?= $directive->attributes(['class' => 'playlist__track', 'data-art' => $directive->artwork()]) ?>>
	<a class="playlist__link" href="<?= url($directive->src) ?>">
		<span class="playlist__title"><?= raw($directive->trackName()) ?></span>
		<?php if ($directive->trackBy() !== '') : ?>
			<span class="playlist__by"><?= e($directive->trackBy()) ?></span>
		<?php endif ?>
		<?php if ($directive->trackLength() !== '') : ?>
			<span class="playlist__time"><?= e($directive->trackLength()) ?></span>
		<?php endif ?>
	</a>
</li>
<?php elseif ($directive->isVariant('card')) : ?>
<figure <?= $directive->attributes(['class' => 'audio-card']) ?>>
	<?php if ($directive->trackTitle() !== '' || $directive->trackBy() !== '') : ?>
		<figcaption class="audio-card__meta">
			<?php if ($directive->trackTitle() !== '') : ?>
				<span class="audio-card__title"><?= raw($directive->trackTitle()) ?></span>
			<?php endif ?>
			<?php if ($directive->trackBy() !== '') : ?>
				<span class="audio-card__by"><?= e($directive->trackBy()) ?></span>
			<?php endif ?>
		</figcaption>
	<?php endif ?>
	<?php if ($directive->artwork() !== '') : ?>
		<img class="audio-card__art" src="<?= url($directive->artwork()) ?>" alt="" loading="lazy" decoding="async">
	<?php endif ?>
	<blush-audio-player <?= $directive->labelAttributes() ?>>
		<audio <?= $directive->playerAttributes() ?>>
			<a href="<?= url($directive->src) ?>"><?= e($messages->t('media.audio_fallback')) ?></a>
		</audio>
	</blush-audio-player>
</figure>
<?php else : ?>
<figure <?= $directive->attributes() ?>>
	<blush-audio-player <?= $directive->labelAttributes() ?>>
		<audio <?= $directive->playerAttributes() ?>>
			<a href="<?= url($directive->src) ?>"><?= e($messages->t('media.audio_fallback')) ?></a>
		</audio>
	</blush-audio-player>
	<?php if ($directive->caption() !== '') : ?>
		<figcaption><?= raw($directive->caption()) ?></figcaption>
	<?php endif ?>
</figure>
<?php endif ?>
