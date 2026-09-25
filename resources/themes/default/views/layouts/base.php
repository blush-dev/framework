<?php

/**
 * Base layout: the document every page renders in. It prints the head,
 * a skip link, the site header and footer, and the page's `content`
 * section in the `<main>` landmark.
 *
 * @var Blush\View\Template $this
 * @var Blush\View\Site     $site
 */

declare(strict_types=1);

?>
<!DOCTYPE html>
<html lang="<?= attr($site->lang) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="generator" content="<?= attr($site->generator) ?>">
<?= $this->head() ?>

</head>
<body class="<?= attr($this->bodyClass()) ?>">
<a class="skip-link" href="#main"><?= e($this->t('skip_to_content')) ?></a>

<?= $this->insert('parts/header') ?>

<main id="main" class="site-main" tabindex="-1">
<?= $this->section('content') ?>
</main>

<?= $this->insert('parts/footer') ?>
</body>
</html>
