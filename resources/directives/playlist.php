<?php

/**
 * Playlist directive (`Directive\Media\Playlist`, D-708): one audio
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
 * @var Blush\Directive\Media\Playlist $directive
 */

declare(strict_types=1);

$first = $directive->firstTrack();

?>
<?php if ($first === null) : ?>
<div <?= $directive->attributes() ?>>
<?= raw($directive->content()) ?>
</div>
<?php else : ?>
<figure <?= $directive->attributes(['class' => 'playlist']) ?>>
	<?php if (trim($directive->label) !== '') : ?>
		<figcaption class="playlist__caption"><?= e(trim($directive->label)) ?></figcaption>
	<?php endif ?>
	<blush-playlist<?= $directive->loop ? ' loop' : '' ?>>
		<?php if ($directive->isVariant('card')) : ?>
			<div class="audio-card playlist__card">
				<div class="audio-card__meta">
					<span class="audio-card__title"><?= raw($first->title) ?></span>
					<span class="audio-card__by"<?= $first->by === '' ? ' hidden' : '' ?>><?= e($first->by) ?></span>
				</div>
				<?php if ($first->art !== '') : ?>
					<img class="audio-card__art" src="<?= url($first->art) ?>" alt="" loading="lazy" decoding="async">
				<?php endif ?>
				<blush-audio-player <?= $directive->labelAttributes() ?>>
					<audio <?= $directive->playerAttributes() ?>>
						<a href="<?= url($first->src) ?>"><?= e($messages->t('media.audio_fallback')) ?></a>
					</audio>
				</blush-audio-player>
			</div>
		<?php else : ?>
			<blush-audio-player <?= $directive->labelAttributes() ?>>
				<audio <?= $directive->playerAttributes() ?>>
					<a href="<?= url($first->src) ?>"><?= e($messages->t('media.audio_fallback')) ?></a>
				</audio>
			</blush-audio-player>
		<?php endif ?>
		<ol class="<?= attr($directive->listClasses()) ?>">
<?= raw($directive->content()) ?>
		</ol>
	</blush-playlist>
</figure>
<?php endif ?>
