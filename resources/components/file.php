<?php

/**
 * File component (`Component\Media\File`): a download link with the
 * file's format and size. The label is the link text; without one, it's
 * the file's name.
 *
 *     ::file[The annual report]{src=report.pdf}
 *
 * @var Blush\View\Template         $template
 * @var Blush\Component\Media\File  $component
 */

declare(strict_types=1);

?>
<p <?= $component->attributes() ?>>
	<a class="component-file__link" href="<?= url($component->src) ?>" download><?= raw($component->text()) ?></a>
	<?php if ($component->details() !== '') : ?>
		<span class="component-file__details">(<?= e($component->details()) ?>)</span>
	<?php endif ?>
</p>
