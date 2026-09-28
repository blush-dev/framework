<?php

/**
 * Audio component (`Component\Media\Audio`): an audio file with the
 * browser's controls, and the label as its caption.
 *
 *     ::audio[Episode 12]{src=episode.mp3}
 *
 * @var Blush\View\Template          $template
 * @var Blush\Component\Media\Audio  $component
 */

declare(strict_types=1);

?>
<figure <?= $component->attributes() ?>>
	<audio <?= $component->playerAttributes() ?>>
		<a href="<?= url($component->src) ?>"><?= e($template->t('media.audio_fallback')) ?></a>
	</audio>
	<?php if ($component->caption() !== '') : ?>
		<figcaption><?= raw($component->caption()) ?></figcaption>
	<?php endif ?>
</figure>
