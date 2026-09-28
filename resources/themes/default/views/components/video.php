<?php

/**
 * Video component (`Component\Media\Video`): a video file with the
 * browser's controls, an optional poster and captions track, and the
 * label as its caption.
 *
 *     ::video[Launch day]{src=launch.mp4 poster=launch.jpg track=launch.vtt}
 *
 * @var Blush\View\Template          $template
 * @var Blush\Component\Media\Video  $component
 */

declare(strict_types=1);

?>
<figure <?= $component->attributes() ?>>
	<video <?= $component->playerAttributes() ?>>
		<?php if ($component->track !== '') : ?>
			<track <?= $component->trackAttributes() ?>>
		<?php endif ?>
		<a href="<?= url($component->src) ?>"><?= e($template->t('media.video_fallback')) ?></a>
	</video>
	<?php if ($component->caption() !== '') : ?>
		<figcaption><?= raw($component->caption()) ?></figcaption>
	<?php endif ?>
</figure>
