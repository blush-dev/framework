<?php

/**
 * Video block (`Block\Media\Video`): a video file in the site's
 * player (D-573), with the browser's controls until its script loads,
 * an optional poster and captions track, and the label as its caption.
 *
 *     ::video[Launch day]{src=launch.mp4 poster=launch.jpg track=launch.vtt}
 *
 * @var Blush\Translation\Messages  $messages
 * @var Blush\Block\Media\Video $block
 */

declare(strict_types=1);

?>
<figure <?= $block->attributes() ?>>
	<blush-video-player>
		<video <?= $block->playerAttributes() ?>>
			<?php if ($block->track !== '') : ?>
				<track <?= $block->trackAttributes() ?>>
			<?php endif ?>
			<a href="<?= url($block->src) ?>"><?= e($messages->t('media.video_fallback')) ?></a>
		</video>
	</blush-video-player>
	<?php if ($block->caption() !== '') : ?>
		<figcaption><?= raw($block->caption()) ?></figcaption>
	<?php endif ?>
</figure>
