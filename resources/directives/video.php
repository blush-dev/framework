<?php

/**
 * Video directive (`Directive\Media\Video`): a video file with the
 * browser's controls, an optional poster and captions track, and the
 * label as its caption.
 *
 *     ::video[Launch day]{src=launch.mp4 poster=launch.jpg track=launch.vtt}
 *
 * @var Blush\View\Template          $template
 * @var Blush\Directive\Media\Video  $directive
 */

declare(strict_types=1);

?>
<figure <?= $directive->attributes() ?>>
	<video <?= $directive->playerAttributes() ?>>
		<?php if ($directive->track !== '') : ?>
			<track <?= $directive->trackAttributes() ?>>
		<?php endif ?>
		<a href="<?= url($directive->src) ?>"><?= e($template->t('media.video_fallback')) ?></a>
	</video>
	<?php if ($directive->caption() !== '') : ?>
		<figcaption><?= raw($directive->caption()) ?></figcaption>
	<?php endif ?>
</figure>
