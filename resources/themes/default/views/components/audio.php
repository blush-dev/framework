<?php

/**
 * Audio component (`View\Component\Media\Audio`): an audio file with the
 * browser's controls, and the label as its caption.
 *
 *     ::audio[Episode 12]{src=episode.mp3}
 *
 * @var Blush\View\Template                       $template
 * @var string                                    $src
 * @var Blush\View\Component\Media\MediaPreload $preload
 * @var bool                                      $loop
 * @var string                                    $slot
 */

declare(strict_types=1);

?>
<figure class="component-audio">
	<audio src="<?= url($src) ?>" controls preload="<?= attr($preload->value) ?>"<?php if ($loop) : ?> loop<?php endif ?>>
		<a href="<?= url($src) ?>"><?= e($template->t('media.audio_fallback')) ?></a>
	</audio>
	<?php if ($slot !== '') : ?>
		<figcaption><?= raw($slot) ?></figcaption>
	<?php endif ?>
</figure>
