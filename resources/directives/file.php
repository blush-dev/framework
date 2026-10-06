<?php

/**
 * File directive (`Directive\Media\File`): a download link with the
 * file's format and size. The label is the link text; without one, it's
 * the file's name.
 *
 *     ::file[The annual report]{src=report.pdf}
 *
 * @var Blush\View\Template         $template
 * @var Blush\Directive\Media\File  $directive
 */

declare(strict_types=1);

?>
<p <?= $directive->attributes() ?>>
	<a class="directive-file__link" href="<?= url($directive->src) ?>" download><?= raw($directive->text()) ?></a>
	<?php if ($directive->details() !== '') : ?>
		<span class="directive-file__details">(<?= e($directive->details()) ?>)</span>
	<?php endif ?>
</p>
