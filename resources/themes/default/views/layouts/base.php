<?php

/**
 * Base layout: the document every page renders in. It prints the head,
 * a skip link, the site header and footer, and the page's `content`
 * section in the `<main>` landmark.
 *
 * @var Blush\View\Template $template
 * @var Blush\View\Site     $site
 */

declare(strict_types=1);

?>
<!DOCTYPE html>
<html lang="<?= attr($site->lang) ?>" dir="<?= attr($site->dir) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="generator" content="<?= attr($site->generator) ?>">
<?= $template->head() ?>

</head>
<body class="<?= attr($template->bodyClass()) ?>">
<a class="skip-link" href="#main"><?= e($template->t('skip_to_content')) ?></a>

<?= $template->include('parts/header') ?>

<main id="main" class="site-main" tabindex="-1">
<?= $template->section('content') ?>
</main>

<?= $template->include('parts/footer') ?>
</body>
</html>
