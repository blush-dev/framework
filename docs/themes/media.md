# Media in templates

A media field, such as an entry's `image`, holds the file's id. Get the
file with `$entry->media('image')`. For any other value, such as a site
setting's, every template has the media library as `$media`:
`$media->find($value)`. Both read an older value written as a path too,
return `null` for none, and give the same file, looked up once a
request.

## A file

A file has its `url`, `width`, `height`, `mime`, `kind()`, and details
(`metadata()`, with its `alt` text and `caption`).

## Printing an image

An image prints itself with `img()`: its size, the library's alt text,
and a `srcset` of the sizes the library keeps of it, lazy-loaded. Pass
attributes by name, as `link()` takes them, to add or change any:

```php
<?php if ($image = $entry->media('image')) : ?>
	<?= $image->img(class: 'hero', loading: 'eager', sizes: '100vw') ?>
<?php endif ?>

<?php if ($logo = $media->find($site->setting('logo'))) : ?>
	<?= $logo->img(class: 'site-logo', alt: $site->name) ?>
<?php endif ?>
```

Give `alt: ''` for a decorative image. Sounds, videos, and other files
print nothing from `img()`.

## Your theme's own images

Your theme's own images aren't in the library: link them with
`$theme->asset('img/logo.png')`.

For the library itself, see [Media](../concepts/media/README.md).
