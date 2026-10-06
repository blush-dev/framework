<?php

/**
 * The welcome page's content: the next steps, and, outside production,
 * any setup problems (`Blush\Setup\Welcome`). A theme's `welcome` view
 * can include it inside its own markup.
 *
 * @var Blush\View\Template            $template
 * @var Blush\View\Site                $site
 * @var Blush\Content\Http\ContentPage $page
 */

declare(strict_types=1);

$welcome = $page->welcome;

// Translates a message whose placeholders are HTML (code or a link),
// escaping the rest: each placeholder is kept, then swapped in.
$html = static function (string $key, array $parts) use ($template): string {
	$marks = [];

	foreach ($parts as $name => $part) {
		$marks["{{$name}}"] = $part;
	}

	return strtr(e($template->t($key, ...array_combine(array_keys($parts), array_keys($marks)))), $marks);
};

$code = static fn(string $text): string => '<code>' . e($text) . '</code>';

?>
<p><?= e($template->t('welcome.running', generator: $site->generator)) ?></p>

<?php if ($welcome !== null) : ?>
	<h2><?= e($template->t('welcome.next')) ?></h2>

	<ol class="welcome-steps">
		<li><?= $html('welcome.start', ['path' => $code($welcome->homepage)]) ?></li>

		<?php if ($welcome->admin === null) : ?>
			<li><?= $html('welcome.admin.off', ['path' => $code('config/admin.php')]) ?></li>
		<?php elseif (! $welcome->accounts) : ?>
			<li><?= $html('welcome.admin.account', [
				'command' => $code("{$welcome->binary} account:add"),
				'admin'   => '<a href="' . url($welcome->admin) . '">' . e($welcome->admin) . '</a>'
			]) ?></li>
		<?php else : ?>
			<li><?= $html('welcome.admin.sign_in', [
				'admin' => '<a href="' . url($welcome->admin) . '">' . e($welcome->admin) . '</a>'
			]) ?></li>
		<?php endif ?>
	</ol>

	<?php if ($welcome->problems !== []) : ?>
		<aside class="directive-callout directive-callout--warning welcome-problems">
			<p class="directive-callout__title"><?= e($template->t('welcome.problems.title')) ?></p>
			<ul>
				<?php foreach ($welcome->problems as $problem) : ?>
					<li><?= $code($problem->label) ?>: <?= e(trim("{$problem->message} {$problem->hint}")) ?></li>
				<?php endforeach ?>
			</ul>
			<p><?= $html('welcome.problems.doctor', ['command' => $code("{$welcome->binary} doctor")]) ?></p>
		</aside>
	<?php endif ?>
<?php else : ?>
	<p><?= $html('welcome.start', ['path' => $code('user/content/index.md')]) ?></p>
<?php endif ?>
