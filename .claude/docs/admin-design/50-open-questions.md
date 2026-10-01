# Open questions

> **Part of the Meridian admin design direction**, in this project under
> `design/`. Start at `design/00-project-brief.md`.

What is still undecided. **Any conversation may settle one** — when you do,
delete it here and write the decision into `60-decisions-log.md` with the
reason, plus the design file it belongs to.

This file is also where a conversation asks another area for something. If work
in your area needs a change in a file you do not own, add it under *Requests*
rather than making it.

---

## Undecided

- API conventions: auth, pagination shape, error format, and how the admin
  discovers content types at runtime.
- The editor: Markdown surface, the component inserter, and the right sidebar
  that swaps between Document fields and Component options.
- How the parser maps cursor position to the directive under it, and rewrites
  component options back into the source without disturbing the author's text.
- The content-type builder's own screens.
- How the media picker is invoked from a component option.
- Whether type provenance ("Posts addon", "Custom type") belongs in the list
  header at all — useful at three types, clutter at fifteen.
- How long a breadcrumb is allowed to get before the middle should collapse into
  a menu. Five crumbs is the realistic worst case in the prototype, and it fits;
  a deeply nested outline in real content may not.
- **Does the parser accept an attribute block on a thematic break?** The
  prototype writes `--- {.bleed-full}` and had to loosen its own rule pattern to
  keep recognizing it. Every other block hangs attributes off the end of its last
  line, so this is the consistent placement — but it is the one that has not been
  checked against the real parser. If it refuses, the fallback is the list's
  placement (a line above), and the block scan, the highlighter and the attribute
  writer all change together.
- **Where do the bleed steps actually land?** `bleed-wide` and `bleed-full` are
  named rather than measured, on the same principle as a variant: the theme owns
  the widths. Which means the theme has to define both, relative to a measure
  nobody has written down. Worth settling with whoever writes the first theme
  rather than in the admin.
- **Should a bleeding container pass width down to its children?** The bleed
  control is top-level only for now, which is the simple rule and the one the
  prototype ships. A container that is itself bleeding could reasonably let its
  direct children bleed within it. Deliberately deferred; the visibility
  predicate is a single function over outline depth, so it is one place to
  change when a theme needs it.
- **Do directives need Classes and ID fields in the element panel?**
  `30-editor.md` says the two controls are the same in every element panel, and
  they are — for Markdown blocks. A directive's panel shows Variant, Options,
  Content and Source, with no way to put a class on it except through bleed. The
  document and the prototype disagree here and one of them is wrong.
- **Should the `:::code` "Code Sample" component exist?** The same test that
  retired the divider component points at this one: Markdown already writes a
  fenced code block, and the component's extra options are a file name and line
  numbers, which an attribute block could carry. It survived the pass that
  renamed *Data Table* only because removing two components unasked was a step
  too far. Settle it deliberately or leave it.
- **Do the insertion toasts earn their place?** "Inserted a callout", "Inserted
  the map icon" — the thing appears under the caret, the breadcrumb names it and
  the element panel opens on it, so the toast is a fourth report. The rule that a
  toolbar *setting* does not toast is settled; whether an insertion counts is
  not.
- **Is ⌘⇧K the right binding for a link?** ⌘K is the command palette admin-wide
  and keeping one meaning for one shortcut won the argument, but ⌘K-for-link is
  about as settled a convention as writing software has, and a writer who tries
  it gets the palette over their sentence. The alternative is to let the editor's
  text own ⌘K and move the palette to ⌘P there — which trades the exception from
  one shortcut to the other. Worth a second opinion before anyone learns it.
- **Should there be a strikethrough button?** `~~` is in the highlighter and in
  the parser, so it is a real mark the writer can type, and it is the fourth
  thing on every toolbar that has the first three. Left out because three
  buttons is a glance and four is a row, and strikethrough is the rarest of the
  four by a wide margin. Add it if the first writer asks twice.
- **No find and replace inside an entry.** The browser's ⌘F searches the
  highlight layer rather than the source, scrolls the wrong element, and cannot
  replace. Fine at 300 words; not at 3,000. Needs a find bar that owns ⌘F
  inside the editor, steps through matches in the textarea and scrolls it.
- **The source has `spellcheck="false"`.** Presumably to stop red squiggles under
  `:::callout` and `{.bleed-wide}`. The trade is no spellcheck at all in a
  writing tool, which looks like the larger cost. Worth deciding rather than
  inheriting.
- **An empty entry says nothing.** No placeholder, so a writer opening a new post
  sees a blank sheet and no hint that `/` exists or that the toolbar's first
  button opens an inserter. The two things a new writer will never find unaided
  are exactly the two the blank canvas could name.
- **Changing a field in the element panel drops keyboard focus.** `renderSide()`
  rebuilds the panel after every write, so the `<select>` you just changed is
  replaced and focus lands on `<body>`. Keyboard-only editing of a component's
  options is effectively impossible. Pre-existing, unrelated to how the source is
  written; the fix is for the panel to patch rather than re-render, or to restore
  focus by field id.
- **Leaving the editor with unsaved changes asks nothing.** Navigating to
  another screen from the rail just goes, and there is no `beforeunload` either,
  so a closed tab takes the draft with it. The save bar tracks the dirty state
  already; what is missing is a decision about what should happen — a confirm, a
  real autosave, or a recovered draft on the way back in. A prototype can get
  away with it; the thing it is a prototype of cannot.
- **Which editor opens by default, and who decides?** Per account is the
  obvious answer and probably not the whole one — a role may want to pin its
  people to one surface, and a content type full of structured components may
  want the visual editor regardless of who opens it. Settle before either
  surface has a toggle, because the toggle's home depends on the answer.
- **Does the visual editor show a directive's syntax at all?** A callout can be
  drawn as a callout, and the author never needs to see `:::callout{type=note}`.
  But the element panel edits those attributes by name today, and the source
  peek in the panel is one of the ways an author learns the format. The visual
  surface may want the peek and not the syntax, or neither.
- **What does an attribute block look like in the visual editor?** `{.bleed-wide}`
  is already a control in the toolbar rather than text the author types, so the
  visual answer may be that it is only ever a control. Classes a theme defines
  that the admin knows nothing about are the hard case.
- **Where does reordering live?** The operation belongs in the model, but the
  affordance does not have to be the same in both surfaces: a gutter handle
  beside the block, drag in the outline panel, keyboard commands, or all three.
  The outline already lists every element in order with its depth.
## Requests between areas

> **To: foundations — from: editor.** §6 *Layout* in `10-foundations.md` says
> "the panel collapses to nothing" without saying what collapses it, and the
> rail's own rules say only that it never navigates. The prototype now makes
> **a rail button a toggle for the panel**: pressing the section you are already
> in closes the panel, pressing it again reopens it, and pressing a different
> section always opens. At narrow widths, where the rail and panel are one
> off-canvas drawer, the same press closes that drawer. The reasoning is in the
> decisions log under *The shell*. The rule belongs in §6 — the editor's file
> should not be where someone learns how the shell behaves.

> **To: foundations — from: editor.** The top bar changed shape and §6 *Layout*
> in `10-foundations.md` describes the old one. Three things: the **collapse
> button is gone** (a rail button toggles its own panel now); the **trail starts
> at the rail section, not the site name** — `Content / Posts / Editing`, and
> the site name is nowhere in it; and **the section crumb scopes the rail panel
> rather than navigating**, always opening, never toggling, while the view
> crumbs navigate. At narrow widths the section crumb is the first to drop,
> because the burger beside it says the same thing. The reasoning is in the
> decisions log under *The shell*. This came out of the editor only because
> removing the editor's back button depended on it — the rules themselves are
> the shell's, and §6 is where someone should find them.

> **To: foundations — from: editor.** §4 says an icon can stand in for a label,
> and the sprite is Lucide as shipped — which sizes each glyph by eye, so ink
> heights across the set run 11 to 20 on the 24-unit grid. In a list that is
> right. In a toolbar it is not: the editor's row now holds ten of them, and
> `i-link` at 19.9 read as a mistake beside everything else. The editor has
> settled **14 to 18, most at 16** for its own row, and rescaled `i-link` and
> `i-shapes` in the shared sprite to get there — those two are now smaller
> everywhere they appear, which is an improvement but is not the editor's call
> to make alone. Whether the band belongs in §4 as a rule for every toolbar, and
> whether the rest of the sprite should be normalized or left as Lucide drew it,
> is foundations' question. The reasoning is in the decisions log under
> *The toolbar*.

> **To: components — from: editor.** The **gallery changed shape** and
> `20-components.md` describes the old one. It is now `block: "container"`, not
> a leaf: `:::gallery{layout=grid columns=3}` with image lines nested inside,
> and the `folder` option is gone. A gallery is a set of images arranged
> together, so the images belong in the entry rather than in an attribute
> pointing somewhere else.
>
> It also introduces **`only`** — a list of what may be nested, in the inserter's
> own keys (`["image"]` here). That is a component-model idea, not an editor
> one, and it needs a home in `20-components.md`: which components should
> declare it (card grid? columns? data table rows?), whether an `only` list may
> ever include text, and what the parser should do with a child that is not on
> the list. The editor enforces it on the way in and reports it after the fact,
> but it cannot be the place the rule is defined. Reasoning in the decisions log
> under *Components*.

Format for a new one:

> **To: foundations — from: editor.** The outline needs a row style that can
> show two lines. Currently `.comp-jump` is single-line. Not urgent.
