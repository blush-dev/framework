# Markdown

Every entry's body is Markdown. Blush uses CommonMark with the GitHub
extras (tables, strikethrough, task lists, autolinks, and footnotes),
and adds a few of its own.

## What Blush adds

- **An image on its own line becomes a `<figure>`**, and its title becomes
  the caption:

  ```markdown
  ![A sunflower.](/media/sunflower.jpg "My favorite flower")
  ```

  Images on lines one after another, as in a gallery, are a figure each,
  with no line breaks between them. Images side by side on one line, or
  with text, stay in their paragraph.

  To caption something else, such as a table or a code block, wrap it in
  a [figure](blocks/README.md): `:::figure[Caption]` …
  `:::`.

- Images from your media folder get their `width` and `height`
  automatically.
- **Definition lists:** a term, then its definition on the next line
  after a `:`:

  ```markdown
  Blush
  : A flat-file CMS.
  ```

  With attributes on, `{.glossary}` on a line of its own above the list
  gives the `<dl>` a class, and `{#blush}` at the end of a term or
  `{.note}` at the end of a definition gives the `<dt>` or `<dd>` one.

- **Highlighting:** `==text==` marks text as highlighted (`<mark>`).
- **Mentions:** `@jane` links to the profile whose slug is `jane`, once
  it's published. A name that isn't anyone's, an address like
  `me@example.com`, and code stay as written. The `@` and the name are
  spans of their own, so a theme can style them apart:

  ```html
  <a class="mention" href="/profiles/jane"><span class="mention__at">@</span><span class="mention__name">jane</span></a>
  ```
- **Smart punctuation:** straight quotes become curly ones, `--` an en
  dash, `---` an em dash, and `...` an ellipsis. Code stays as written.
- **Heading anchors:** each heading gets a link to itself (`#`), shown
  by the default theme when the heading is hovered.
- **Line breaks:** a line break inside a paragraph stays one (`<br>`).
- **Spans:** text in brackets followed straight away by attributes,
  `[text]{.class #id}`, becomes a `<span>` with them, for a class or id
  on a few words. A link (`[text](/url){.class}`) or a word with a link
  reference definition stays a link, with the attributes on it.
- Links that start with `/` become full URLs, so they still work in feeds.
- Raw HTML is allowed, unless your site filters it. In the admin, who may
  add it is up to their role (see
  [Accounts and roles](accounts/README.md)).

Mentions, smart punctuation, heading anchors, figures, and raw HTML can
be changed on the admin's Writing settings screen (see
[the admin](../admin/README.md)), and more in `config/markdown.php`; see
[Configuration](../reference/configuration/README.md).

## Blocks

Blocks add richer pieces to your writing:

```markdown
:::callout[Heads up]{variant=warning}
Back up your site before updating.
:::

::embed[Our launch video]{url="https://youtu.be/..." title="Launch video"}
```

`callout`, `gallery`, `figure`, and `embed` work in every theme, and
plugins can add more. See [Blocks](blocks/README.md) for the syntax and
every built-in block, and [Making a block](../features/blocks/README.md) to make
your own.

## Only Markdown

Every entry is a `.md` file. Other files in `user/content/` aren't
entries and don't show on the site. If you have entries from before this
change in other formats, `content:lint` (and Site Health in the
admin) lists them:

- **`.markdown`:** rename it to `.md`.
- **`.html`:** rename it to `.md`. HTML in a Markdown body still
  renders, as long as raw HTML is allowed (on the admin's Writing
  settings screen).
- **`.json` or `.yaml`:** move its keys into the front matter of a `.md`
  file, with its `body` (if it has one) below.
