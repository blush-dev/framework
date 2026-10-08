<?php

/**
 * Base layout: the document skeleton a page renders in when the theme
 * has no `layouts/base` of its own (D-632). It prints the head, a skip
 * link, the page's `content` section in the `<main>` landmark, and the
 * end of the body. A theme's own base layout replaces it whole, with its
 * header and footer.
 *
 * @var Blush\View\Template $template
 * @var Blush\View\Site     $site
 */

declare(strict_types=1);

?>
<!DOCTYPE html>
<html lang="<?= attr($site->lang) ?>" dir="<?= attr($site->dir) ?>">
<head>
<?= $template->head() ?>

</head>
<body class="<?= attr($template->bodyClass()) ?>">
<a href="#main"><?= e($template->t('skip_to_content')) ?></a>

<main id="main" tabindex="-1">
<?= $template->section('content') ?>
</main>
<?= $template->foot() ?>
</body>
</html>
