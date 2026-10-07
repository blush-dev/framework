<?php

/**
 * Audio directive (`Directive\Media\Audio`): an audio file in the site's
 * player (D-573), with the browser's controls until its script loads,
 * and the label as its caption.
 *
 *     ::audio[Episode 12]{src=episode.mp3}
 *
 * @var Blush\View\Template          $template
 * @var Blush\Directive\Media\Audio  $directive
 */

declare(strict_types=1);

?>
<figure <?= $directive->attributes() ?>>
	<blush-audio-player <?= $directive->labelAttributes() ?>>
		<audio <?= $directive->playerAttributes() ?>>
			<a href="<?= url($directive->src) ?>"><?= e($template->t('media.audio_fallback')) ?></a>
		</audio>
	</blush-audio-player>
	<?php if ($directive->caption() !== '') : ?>
		<figcaption><?= raw($directive->caption()) ?></figcaption>
	<?php endif ?>
</figure>
