# Components

A component is a reusable piece of a template: a card, a post header, a
byline, a list of archives. A theme builds its pages from components so
it never writes the same markup twice, and a child theme can change one
piece everywhere at once.

Components belong to templates, never to content. What your content
says, such as a callout or a gallery, is a
[block](../../concepts/blocks/README.md). A template can draw either one.

## Component names

A component's name has two parts, `{namespace}/{name}`, and is always
written in full:

| Namespace              | Whose components | Example             |
|------------------------|------------------|---------------------|
| The theme's namespace  | A theme's own    | `notebook/card`     |
| The plugin's namespace | A plugin's own   | `newsletter/signup` |

A theme or [plugin](../../plugins/README.md) declares its namespace in its
manifest (`"namespace": "notebook"`). There are no built-in components,
and a site has none of its own: components come from themes and
plugins.

To see every component the active theme can draw, run:

```sh
bin/blush component:list
```

## Components from a plugin

A plugin can offer components for themes to use in their templates,
such as a newsletter plugin's `newsletter/signup`, registered with
`ComponentRegistry` and drawn by their own `render()`, which a theme
replaces with a template of its own (see
[A component with a class](component-classes.md)).

## Where components live

| Where                                                                                          | Available                                                                        |
|------------------------------------------------------------------------------------------------|----------------------------------------------------------------------------------|
| A theme's `views/components/` (and classes in its `src/`, registered by its provider)          | While that theme or a child of it is active. See [Blocks and components in themes](../../themes/blocks-and-components.md). |
| A plugin (its classes, registered by its provider)                                             | While it's on, for themes to use. See [Plugins](../../plugins/README.md). |

The active theme's files come first, then its parents', and last the
component's own `render()`. To change a theme's component, make a
[child theme](../../themes/child-themes.md) with its own template.
`bin/blush theme:why components/notebook-card` shows which file is used
and what it overrides.

## Pages

| Page | What it covers |
|---|---|
| [Using components in templates](using-in-templates.md) | Props, content, slots, and components a site may not have |
| [A template-only component](template-only.md) | A component that's just a template |
| [A component with a class](component-classes.md) | Typed props, services, and registering |
