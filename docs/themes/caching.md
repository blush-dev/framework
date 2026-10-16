# Caching slow parts

Some parts of a page are slow to build, such as a list of every post.
Wrap them in `$template->cache()` and they're built once, then reused until
your content changes (a publish) or you switch themes:

```php
<?= $template->cache('archives.years', fn () => $template->component('notebook/post-archives', by: 'year')) ?>
```

The key names the piece, so give each variation its own key. Only the
HTML is kept, so don't add to the head inside it. In development,
nothing is cached, so your changes always show.

A block used in an entry's Markdown (`::acme/post-list{limit=5}`)
needs no `cache()`: it's kept with the entry's rendered content, on the
same terms.

For caching whole pages, see [Caching](../running/caching.md).
