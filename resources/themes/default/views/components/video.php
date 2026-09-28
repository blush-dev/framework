<?php

/**
 * Video component (`View\Component\Media\Video`): a video file with the
 * browser's controls, an optional poster and captions track, and the
 * label as its caption.
 *
 *     ::video[Launch day]{src=launch.mp4 poster=launch.jpg captions=launch.vtt}
 *
 * @var Blush\View\Template                       $template
 * @var string                                    $src
 * @var string                                    $poster
 * @var string                                    $captions
 * @var string                                    $captionsLang
 * @var ?int                                      $width
 * @var ?int                                      $height
 * @var Blush\View\Component\Media\MediaPreload $preload
 * @var bool                                      $loop
 * @var bool                                      $muted
 * @var string                                    $slot
 */

declare(strict_types=1);

?>
<figure class="component-video">
	<video src="<?= url($src) ?>" controls playsinline preload="<?= attr($preload->value) ?>"<?php if ($poster !== '') : ?> poster="<?= url($poster) ?>"<?php endif ?><?php if ($width !== null) : ?> width="<?= attr((string) $width) ?>"<?php endif ?><?php if ($height !== null) : ?> height="<?= attr((string) $height) ?>"<?php endif ?><?php if ($loop) : ?> loop<?php endif ?><?php if ($muted) : ?> muted<?php endif ?>>
		<?php if ($captions !== '') : ?>
			<track kind="captions" src="<?= url($captions) ?>" srclang="<?= attr($captionsLang) ?>" label="<?= attr($template->t('media.captions')) ?>" default>
		<?php endif ?>
		<a href="<?= url($src) ?>"><?= e($template->t('media.video_fallback')) ?></a>
	</video>
	<?php if ($slot !== '') : ?>
		<figcaption><?= raw($slot) ?></figcaption>
	<?php endif ?>
</figure>
