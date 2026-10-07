<?php

/**
 * Video directive (`Directive\Media\Video`): a video file in the site's
 * player (D-573), with the browser's controls until its script loads,
 * an optional poster and captions track, and the label as its caption.
 *
 *     ::video[Launch day]{src=launch.mp4 poster=launch.jpg track=launch.vtt}
 *
 * @var Blush\View\Template          $template
 * @var Blush\Directive\Media\Video  $directive
 */

declare(strict_types=1);

?>
<figure <?= $directive->attributes() ?>>
	<blush-video-player <?= $directive->labelAttributes() ?>>
		<video <?= $directive->playerAttributes() ?>>
			<?php if ($directive->track !== '') : ?>
				<track <?= $directive->trackAttributes() ?>>
			<?php endif ?>
			<a href="<?= url($directive->src) ?>"><?= e($template->t('media.video_fallback')) ?></a>
		</video>
	</blush-video-player>
	<?php if ($directive->caption() !== '') : ?>
		<figcaption><?= raw($directive->caption()) ?></figcaption>
	<?php endif ?>
</figure>
