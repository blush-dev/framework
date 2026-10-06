<?php

/**
 * Small print directive (`Directive\Inline\Small`): side comments and small print.
 *
 *     :small[Prices include tax.]
 *
 * @var Blush\View\Template           $template
 * @var Blush\Directive\Inline\Small  $directive
 */

declare(strict_types=1);

?>
<small <?= $directive->attributes() ?>><?= raw($directive->text()) ?></small>
