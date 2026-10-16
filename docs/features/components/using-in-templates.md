# Using components in templates

A template draws a [component](README.md) by its full name:

```php
<?= $template->component('notebook/card', entry: $post) ?>
```

Props are named arguments. `->content()` fills the component's main
content, and **named slots** hold other pieces, filled with `->slot()`:

```php
<?= $template->component('notebook/card', entry: $post)
	->content('<p>The body.</p>')
	->slot('footer', '<a href="/more">More</a>') ?>
```

## Components a site may not have

A component that doesn't exist is an error. When a component comes from
a plugin the site may not have, such as one your theme
[suggests](../../plugins/requirements.md), use `componentIf()`: it renders
nothing when the component doesn't exist, and takes props and slots the
same way:

```php
<?= $template->componentIf('acme/powered-by') ?>
```

To wrap it in markup of your own, ask first with `hasComponent()`:

```php
<?php if ($template->hasComponent('acme/powered-by')) : ?>
	<div class="site-footer__credit"><?= $template->component('acme/powered-by') ?></div>
<?php endif ?>
```

A theme can draw a plugin's component with a template of its own, named
for it (`views/components/acme-powered-by.php`). That template is used
only while the plugin is on: without the plugin's class, the component
doesn't exist, and `componentIf()` prints nothing.

`componentIf()` covers only a missing component. A name that isn't
`{namespace}/{name}`, or a component that's there but fails, is still an
error.
