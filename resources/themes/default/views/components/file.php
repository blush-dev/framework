<?php

/**
 * File component (`Component\Media\File`): a download link with the
 * file's format and size. The label is the link text; without one, it's
 * the file's name.
 *
 *     ::file[The annual report]{src=report.pdf}
 *
 * @var Blush\View\Template $template
 * @var string              $src
 * @var string              $name
 * @var string              $format
 * @var string              $size
 * @var string              $slot
 */

declare(strict_types=1);

$details = match (true) {
	$format !== '' && $size !== '' => $template->t('media.file_details', format: $format, size: $size),
	default                        => $format . $size
};

?>
<p class="component-file">
	<a class="component-file__link" href="<?= url($src) ?>" download><?= $slot !== '' ? raw($slot) : e($name) ?></a>
	<?php if ($details !== '') : ?>
		<span class="component-file__details">(<?= e($details) ?>)</span>
	<?php endif ?>
</p>
