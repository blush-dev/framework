# The head and the foot

Blush fills in a page's `<head>` for you. `$template->head()` adds to it
and prints it, and `$template->foot()` does the same for the scripts at
the end of the page.

## What Blush puts in the head

Blush fills in the `<head>` for you: the title, the canonical URL,
OpenGraph tags (with `og:locale`, and `og:locale:alternate` for the
page's other languages), feed links, `hreflang` links to the page in your
site's other [languages](../concepts/translations.md), and on an entry's page, a description (its
`summary`, or the start of its text) and, when the entry has an `image`
field, `og:image` and a Twitter card. The image is shared at the smallest
of its sizes at least 1200 pixels wide (or as it is, when none is), with
its width, height, and alt text, and a file that isn't an image isn't
shared. A post in a collection also gets `article:published_time` and
`article:modified_time`, and an unlisted entry's page asks search
engines not to index it.

Add or replace any tag with `$template->head()`, or drop one with
`remove()`, such as your stylesheet on a page that stands alone:
`$template->head()->remove('style:' . $theme->asset('style.css'))`.

## Your base layout

`$template->head()` prints everything inside `<head>`, and
`$template->foot()` prints the scripts that load at the end of the page,
so your base layout needs only:

```php
<head>
<?= $template->head() ?>

</head>
<body>
…
<?= $template->foot() ?>
</body>
```

Print `$template->foot()` just before `</body>`, in every layout that
writes its own `<body>`. Blush, plugins, and themes put scripts there,
and a layout without it gets none of them: `bin/blush theme:check`
reports it as an error, and in development, the log names the scripts
a page lost.

## The order it prints in

It starts with `<meta charset="utf-8">` and the `<title>`, then prints
its tags grouped by kind: meta tags (including `viewport` and
`generator`, which you can replace or remove like any other), OpenGraph
tags, links, preloads and other resource hints, styles, scripts, then
inline scripts. Each group keeps the order its tags were added in, so
stylesheets and inline styles stay in your cascade order. The foot
prints its scripts before its inline scripts too. An inline script runs
where it stands, so it still runs before any `defer` or module script,
wherever it prints. Every line is indented by one
tab. `bin/blush theme:check` warns if your layout prints the charset,
`viewport`, `generator`, or `<title>` itself as well.

The head prints root-relative links and scripts (`/feed`,
`$theme->asset(...)`) as full URLs on your site's `url`, so pass
paths as they are. Meta tag values print as given, so give `og:image`
and the like a full URL.

## Preloading a file on one page

To preload a file on one page, such as the picture at the top of a
post, use `$template->head()->preload($url)`; it works out what the
file is the same way the [`preload`](theme-json.md) list does, and
attributes you give win: `->preload($url, ['fetchpriority' => 'high'])`.

## Scripts that run before the page paints

A few lines of JavaScript that must run before the page paints, such as
setting a color scheme a visitor picked, go in with
`$template->head()->inlineScript('scheme', $js)`, once per id; they run
where they stand, unlike your theme's deferred `scripts`. Like
`inlineStyle()`, the code isn't escaped, so it must be yours.

For scripts and styles registered by name, and scripts in the foot, see
[Scripts and styles](scripts-and-styles.md).
