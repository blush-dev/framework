<?php

/**
 * Audio directive (`Directive\Media\Audio`): an audio file with the
 * browser's controls, and the label as its caption.
 *
 *     ::audio[Episode 12]{src=episode.mp3}
 *
 * @var Blush\View\Template          $template
 * @var Blush\Directive\Media\Audio  $directive
 */

declare(strict_types=1);

?>
<figure <?= $directive->attributes() ?>>
	<audio <?= $directive->playerAttributes() ?>>
		<a href="<?= url($directive->src) ?>"><?= e($template->t('media.audio_fallback')) ?></a>
	</audio>
	<?php if ($directive->caption() !== '') : ?>
		<figcaption><?= raw($directive->caption()) ?></figcaption>
	<?php endif ?>
</figure>
