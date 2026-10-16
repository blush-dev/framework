# Trees

A tree is a content type whose entries nest by folder, as pages do, in a
folder of its own, `_` and its name. Use one for docs, a manual, or a
handbook.

## Making a tree

In JSON, that's `user/data/types/doc.json`, as the admin's **New
Content Type** writes it:

```json
{ "kind": "tree", "icon": "book", "prefix": "docs" }
```

In a plugin, it's a `Tree` (see
[Content types from a plugin](../features/content-types.md)):

```php
use Blush\Content\Type\Tree;

new Tree('doc', icon: 'book', prefix: 'docs')
```

A tree from a plugin can be
[changed from the admin](content-types.md#changing-a-type-from-code), as
collections can; the `page` type can't.

## How a tree is served

- Each entry is served at its path under the tree's `prefix` (its name
  when it has none): `_doc/install/requirements.md` is at
  `/docs/install/requirements`.
- The folder's `index.md` is the tree's landing page, at `/docs`, and the
  admin pins it above the tree's other entries.
- An entry in a subfolder is a child of the entry the subfolder is named
  for: `install/requirements.md` is under `install.md` (or
  `install/index.md`). Themes show the tree with `$entry->parent()`,
  `$entry->ancestors()`, and `$entry->children()` (see
  [Themes](../themes/README.md)), and the admin lists it as a tree.
- A tree has no listing page, feed, or routes of its own. Its entries use
  the `single-{type}` and `single` views.

Only the `page` type sits at the content root, and it takes no
prefix. Trees order their pages by `position`, then title (see
[Front matter](front-matter.md)), and don't take a
[folder pattern](file-names-and-urls.md#folders-for-many-files), since a
tree's folders are their pages'.

A tree's options are in [All type options](content-types.md#all-type-options).
To give a tree fields, see [Giving pages fields](custom-fields.md#giving-pages-fields).
