# The editor

> **Part of the Meridian admin design direction**, in this project under
> `design/`. Start at `design/00-project-brief.md`. The other parts:
> `10-foundations.md` · `20-components.md` · `30-editor.md` · `40-screens.md` ·
> `90-conventions.md`, with `50-open-questions.md`, `60-decisions-log.md` and
> `70-build-runbook.md` alongside them. Section numbers are the original
> document's, so a cross-reference like "§8" still finds its target wherever it
> now lives.

The entry editor: the writing surface, how the source is marked, the element
model behind the panels, the document panel, the inserters, the outline and the
breadcrumb. This is the largest part of the design and the one that changes
most; it is also the part with the most rules that only make sense together.

---

## Two editors, one source

The admin will ship **two writing surfaces**: the Markdown editor described in
this document, and a visual editor that shows the document rather than its
source. The visual one was prototyped and then put on hold; the Markdown editor
is what ships today, and the architecture below is what the second surface will
be built back onto. Not a choice between them and not a migration — both, because they
answer to different people. Someone who writes in Markdown every day is slowed
down by a surface that hides it. Someone who has never seen a fence should
never be shown one.

Everything below depends on getting the relationship between them right, so it
is stated first.

### Markdown is the document. Both editors are views of it.

Not "Markdown is the export format". The file on disk is the entry, the visual
editor reads and writes that file, and a parse tree is something either surface
may build to do its job and throw away afterwards. The moment a tree becomes
the thing that is saved, the Markdown starts drifting: an author's own line
breaks get normalized, an attribute block moves, a piece of hand-written HTML is
rewritten into something equivalent-but-not-the-same. Authors notice, because
it happens to text they wrote and did not touch.

Having two editors is what makes this enforceable rather than aspirational.
**The other surface is always one click away**, so any damage the visual editor
does to the source is visible immediately, by the person it happened to. A
single-surface editor can be lossy for years without anyone finding out.

### The round-trip is an invariant, and it is testable

> Open an entry in the visual editor, change nothing, switch back: the source
> is **byte-identical**. Change one thing, switch back: the diff touches only
> that thing.

That is the contract, and it is the first thing to build — before the visual
editor, as the test the visual editor has to pass. Every element in the
prototype's sample content, round-tripped, compared byte for byte. It is cheap
to write now and impossible to retrofit, because by then the failures are
spread across a hundred small normalizations nobody can attribute.

The second half of the invariant is the one people forget. An editor that
rewrites the whole document on every save passes the first test and fails the
second, and the damage shows up in version history rather than on screen.

### What the visual editor does with what it cannot model

It does not have to model everything, and should not wait until it does. Any
block the visual editor has no view for is shown **as its Markdown, in place,
in a box** — editable as text, untouched on save. That single escape hatch is
what lets the visual surface ship with paragraphs, headings, lists, images and
four components, and grow from there without ever risking the invariant.

It is also the honest answer for the things that are genuinely source: a raw
HTML embed, an attribute block the parser does not recognize, a construct from
a future version of the format.

### The split already exists in the code, and most of it is model

The work is smaller than a rewrite because most of what has been built is not
about the textarea at all. Counted over the editor's own functions:

- **Model — operates on the source and its spans, no surface at all.** The
  block and directive scans, the outline and its containment depths, attribute
  parsing and writing, the component definitions and their `only` constraints,
  the inserter's contents, the element and document panels, the media kind
  locks, the HTML-to-Markdown converter. Around 57 functions. A second surface
  reuses every one of them unchanged.
- **Surface — knows about a textarea, a caret or the highlight layer.** The
  write path, the highlight renderer, the caret-to-element resolver, and the
  keyboard behaviors that only mean something in text: the fence completion,
  Enter continuing a list, Tab moving a block, Backspace unmaking a marker, the
  guards that keep an insertion out of a directive's tag. Around 21 functions,
  and the visual editor replaces them with its own.
  The rule that follows: **a new behavior goes in the model layer unless it can
  only exist in text.** Reordering elements is a model operation — it is a span
  moved between siblings — so it belongs there and both surfaces get it. The
  sentence tools are a surface operation, because "wrap the selection in
  asterisks" is a sentence about Markdown.

### The node list, and the first thing both editors share

The model layer now has the list both surfaces need: the entry's **top-level
elements, in order**, each owning an exact run of characters, with the
whitespace between them held separately as the document's own.

A node carries its **raw source**, which is what makes the invariant structural
rather than careful. Putting the document back together emits the characters
each node came in with; a node only re-serializes from its parsed form at the
moment something edits it, which is the only moment the format may change and
the only moment anyone is looking at it. The visual editor's escape hatch falls
out of the same property: an element it has no view for is still a node with a
raw, and a node it never touched comes back byte for byte.

**The gaps belong to the document, not to the nodes.** Moving an element swaps
the text and leaves the spacing where it was, so a document with a wider break
before its conclusion still has one afterwards.

The round trip is asserted on every scan while the prototype is being built,
and says so once in the console if it ever fails. It holds across every seeded
entry and across the sources that usually break a parser: trailing spaces, no
final newline, runs of blank lines, leading blank lines, an unclosed fence,
nested containers, raw HTML, an attribute block above a list, an orphan
attribute line, whitespace only, and empty.

### Reordering, which is a model operation

Moving an element is a span swapped with its sibling, so it is written once in
the model and every surface calls it. Three ways in, because they answer at
different moments:

- **⌥↑ and ⌥↓** while writing. It acts on the top-level element the caret is
  in, so a caret deep inside a list inside a container moves the container —
  the only answer that needs no rule.
- **Position, in the element panel**: *Move up*, *Move down*, and where you are
  in the order. When the panel is describing something nested, the hint names
  what would actually move — *"Moves the Callout · 5 of 12"* — rather than
  letting the count look like it belongs to the element at the top of the
  panel.
- **Handles on an outline row**, on hover. Only on a top-level row: an element
  inside a container has no siblings to be reordered among, and it moves when
  its container does.
  Only the first and last of a document have an edge, so those two buttons are
  disabled rather than hidden — in a list of positions, a missing control reads
  as a missing position.

### The visual surface — prototyped, then put on hold

It was built and then taken back out. The code is gone from the prototype; what
it taught is here, because the decision to ship two surfaces has not changed
and this is what the next attempt starts from.

**It worked, and the invariant held.** Switching over, changing nothing and
switching back returned the source byte for byte, across a document holding
every element type. The node model carried it: a node owns its raw characters,
so a node nothing touched comes back as itself.

**One undo stack, from one trick.** In the visual view the Markdown textarea
was moved out of sight rather than removed, because it is the field every write
goes through and `execCommand` cannot record against an element that cannot be
focused. The result was better than the trick: an edit made in the visual
editor was reversed by ⌘Z in the Markdown one, and the source came back
byte-identical. Whatever the next attempt does, it should keep this.

**It is a page, not a stack of blocks.** The first pass drew every node as a
titled card with its options along the top, with a type label and handles in
the margin, and the document came out looking like an inventory of itself. What
should be drawn is what the *content* is — a callout looks like a callout —
with nothing marking the block being typed in, because the caret does that.
Only something you cannot put a caret into needs to say it is selected.

**Prose is edited in place; structure is edited in the panel.** A paragraph, a
heading, a quote and a list are text and are typed into directly. A table, a
code block, an image and a component are not — they are selected on the surface
and edited in the panel beside it, which is where their options already live.
This is why the visual editor needed no toolbar of its own.

**Three things that will bite again.** Committing an edited block has to flatten
what the browser leaves in a contenteditable before converting it, or a *block*
reader turns each stray fragment into a block and the sentence comes out in
pieces. Re-rendering after a commit re-fires the commit unless it is guarded.
And the surface must own its own selection: routing "where am I" through the
hidden textarea lost it the moment the inserter opened, with nothing in the code
touching the selection to blame.

**Still unbuilt when it was shelved:** splitting a block with Enter, a caret
that travels between blocks, and the sentence tools, which act on a text
selection in a source that has no caret there.

### What this does not change### What this does not change

The element model, the component vocabulary, the panels, the outline, the
breadcrumb, bleed, and every rule in this document about what a component *is*
and what may go inside it. Those are about the document, not about how it is
typed. The Markdown editor's own rules — the highlighting, the caret alignment,
the keyboard behaviors — stay exactly as described below, because that editor
is not going anywhere.

---

## 8. Patterns — the editor

### The editor is a writing surface
Every other screen in this admin is a tool. The editor is a place someone
writes for an hour, so it is designed against a different measure: how little
of it you notice.

- **One centered column.** The source sits in a `68ch` measure with the
  remaining width as margin. Full-width lines are unreadable, and a column that
  moves when a panel opens is worse than one that does not.
- **Settings push, never cover.** The drawer widens the layout aside rather
  than sliding over the text, so nothing is hidden behind a panel while it is
  open. Closed by default; ⌘/ or the header button opens it. Below 980px there
  is no room to push, so it overlays instead.
- **Two tabs in the drawer, both always visible**: one for the entry, one for
  whatever the caret is inside. Neither replaces the other's header and there is
  no back arrow — you can always see where you are and what the alternative is.
- **Both tabs are named for what they hold, not for what they are.** The first
  is **the content type's own name** — *Post*, *Page*, *Release*, *Topic* — not
  "Document", because "document" is a word about software and a writer opening
  this drawer is editing a post. The second **renames itself to what it is
  showing**: "Callout", "Heading 2", "List", falling back to *Elements* for the
  outline or an empty selection. The panel under it changes every time the caret
  moves, so a constant label would be the one thing on screen not telling you
  anything. (`singular` is stored lowercase for use inside sentences — "New
  post" — so a name standing on its own is title-cased at the point of use.)
- **The tabs sit left, the close button sits right.** Tabs stretched to fill the
  width read as segmented buttons, not tabs, and a panel with no visible way to
  shut it sends people hunting the toolbar for the control that opened it. The
  first tab is **flush with the panel's edge**: its own padding lines its label
  up with the fields below, so the whole drawer shares one left edge.
- **The element tab is never disabled.** A disabled tab is a dead end that still
  costs a click to discover. It names whatever the caret is in, and in the state
  where the caret is nowhere it says so in words. That state is reachable on
  purpose — see *Leaving the text drops the selection* — so it is a state the
  panel has to be good at, not a corner case.
- **The drawer opens on whatever the caret is in.** Reach for the settings
  panel with the caret inside a callout and the callout's options are what
  comes up, not the entry's status and slug. The entry tab is one click away
  and has not moved; the other way round, the element tab would have been one
  click away *and* would have needed the author to notice that the panel had
  not opened on the thing they were looking at. With nothing selected it lands
  on the entry tab, because the other one has nothing to show — and the tab is
  decided on every open rather than remembered, or the drawer opens on
  "Nothing Selected" after the author has clicked away from the element they
  last had. Three callers ask for the entry by name and get it: the entry
  crumb, the Outline, and the validation notice pointing at a required field.
- **The Outline is a drilldown inside the entry tab, not a third tab and not a
  borrowed one.** "What is in this entry" is a question about the entry, so it
  is asked where the entry's other properties are: one quiet row at the foot of
  that tab, opening a panel *over* its fields with a way back.
  ```
  ←  Post / Outline                            36
  ```

  The path says what you left as well as where you are, so the panel reads as
  gone-one-level-deeper rather than replaced, and the tab above stays on **Post**
  the whole time — borrowing the element tab to show it made the drawer look
  like it had navigated somewhere else. Opening it also **scrolls the panel back
  to the top**: the row that opens it sits at the foot of a scrolled panel, and
  landing halfway down the list hides the elements it starts with. Picking a row
  selects that element, which is the one moment the element tab takes over,
  because the panel is now showing that element's options.
- **The drawer says nothing about `container` / `leaf` / `inline`.** That is the
  syntax's business, not the author's: they can see the shape of the thing in
  the text, and the word adds a vocabulary they never asked to learn.
- **No "go to it in the text" button.** The caret is already inside the
  component — that is why the panel is showing it. A button that goes where you
  already are is furniture.
- **The title is part of the document**, not a form field above it — display
  face, 30px, no box, wrapping to as many lines as it needs, and it scrolls
  away with the text. Enter moves to the body.
- **Chrome recedes while typing.** The header and footer fade to a third
  opacity as soon as keys move and come back on any pointer movement. Nothing
  disappears; it just stops competing.
- **Focus mode** (⌘⇧F, or the palette) drops the rail and top bar entirely,
  leaving the column. Escape returns.
- **The footer says where you are, and how much you have written.** Two things
  and nothing else: the **breadcrumb** on the left, **words and reading time**
  on the right. No shortcut hints — a hint that is always on screen is being
  read by nobody after the first day, and the space it takes is the space the
  breadcrumb needs. The commands those hints named live in the editor's own
  menu, which is where someone goes when they are looking rather than typing.
- **Attribute blocks are marked, and marked differently from directives.**
  `{.class #id key=value}` can hang off any ordinary Markdown — an image, a
  link, a heading, a paragraph — and an author who cannot see where one ends
  will break one. It gets a quiet gray chip: braces and punctuation in `--fg-3`,
  key/value pairs in `--fg-2`, and the class and id names in full `--fg`,
  because the names are what you scan a document for. The accent chip stays
  reserved for directives, so the two are never confused: accent means *this is
  a component*, gray means *this is metadata about the line it is attached to*.
  A brace that is not a valid attribute block — prose, or one inside a code
  span — is left as plain text, so the highlight doubles as a syntax check.
- **The toolbar is bigger than the admin's default.** 36px targets and 18px
  icons against 32/16 elsewhere, with more space between them. It is the most
  used toolbar in the product and the one people reach for without looking.
- **A selected element is named, not opened.** The breadcrumb follows the caret
  whether the drawer is open or shut, and opens it only when a crumb is clicked.
  Interrupting writing to show a panel nobody asked for is the thing this design
  is against.
- **The editor opens with both panels closed.** The section panel collapses on
  the way in and the settings drawer starts shut, so an entry opens as a column
  of text and nothing else. The list of other things you could be working on is
  not what you came here for. Both reopen on request, and **the section panel is
  put back the way it was found** when you leave — collapsing it for the editor
  is a courtesy, not a setting the editor gets to change on your behalf.
- **Unpublished changes are ordinary, not exceptional.** They get a small
  `Changes` pill beside the status and the primary button reading **Update** —
  no banner. Discard lives in the overflow menu. A bar across the top of the
  screen is for something that has gone wrong: a failed save, a newer version on
  the server, a required field blocking publication. Routine state does not earn
  one, and spending the banner on routine state means nobody reads the ones that
  matter.
### The toolbar
- **The header has two halves, and the split is meaningful.** On the left, the
  insert tools, the text tools and the bleed control — things you do *to* the
  document. On the right, save state, status, settings, overflow, and the
  primary action — what the document *is* and what happens to it. Reading the toolbar should not require remembering where a given control
  was put. The insert button also sits directly above the panel it opens, so the
  panel reads as coming from the button rather than appearing beside the text.
- **The left half reads in three parts**: what you put in the *document*
  (components, media), what you put in a *sentence* (bold, italic, link, an
  icon, an inline component), and how *wide* it all is (bleed). A hairline between
  each. The order is not decorative — it is the order of the questions a writer
  asks, from the largest thing to the smallest.
- **The icon button is in the sentence group, not the insert group.** An icon
  is `:icon[name]` — an inline directive that goes inside a sentence, the same
  as the inline components next to it — so it is offered where a sentence is
  and nowhere else. It sat with the block inserters only because it is an
  inserter; what decides the group is what the thing *is*, not how it is
  reached.
- **The first part is fixed; the other two are contextual.** The two insert
  tools are present on every entry at every moment, which is what makes the
  toolbar learnable. The sentence group and bleed appear and disappear as the
  caret moves, because each of them is a lie where it cannot act: a width
  control on an element that cannot take a width, a bold button where asterisks
  are just asterisks. They are **hidden, not disabled** — a toolbar of
  greyed-out buttons following the cursor around the document is noise.
- **A contextual group collapses at the end of the fixed ones, never between
  them.** This decides the order as much as the reading does: the sentence
  group has to sit after all three insert tools, because if it sat between two
  of them, every trip into a code block would slide the one on its right 138px
  across the toolbar and back. Nothing that is always there is allowed to move.
- **Every control in that row is one button.** An icon, in a fixed position,
  with a caret after it if it opens a menu — that is the whole vocabulary, and
  it is the condition under which an icon is a label (§4). A word beside one of
  them would be the only word in the row, and a second target inside one of them
  would be the only place in the row where the target you press changes what
  happens.
- **One thing is open at a time.** Opening any of the toolbar's panels, menus or
  modals closes whatever else was open — including the inserter panel, which
  otherwise stays put. Each of them used to dismiss only itself, so reaching for
  a second left the first standing behind it: a menu over a modal, a panel
  behind both, and two presses of Escape to get back to the text. They are
  alternative answers to one question — *what am I putting in, or changing?* —
  and one question does not have two answers open at once.
- **Bold, italic and link show wherever Markdown emphasis is emphasis**, which
  is prose: a paragraph, a heading, a list item, a quote, a cell in a table, the
  body of a container. They go away inside a fenced code block, on a directive's
  own `:::` line, on a thematic break, and on a table's rule — in all four, a
  pair of asterisks is two asterisks. **The inline-component button goes with
  them**, because it answers the same question — what goes inside this sentence
  — and is just as meaningless in a code fence.
- **The group is shown only while the caret is in the source.** Clicking the
  entry title hides it, and so does the editor opening with nothing focused: the
  title is text but it is not *Markdown*, and a bold button over it would write
  two asterisks into a heading. The toolbar's own controls are the exception —
  reaching for one of them is not leaving the source, which is the same rule
  that keeps the breadcrumb from forgetting what you had selected.
- **Bold and italic are toggles; link is a form.** Bold and italic have an on
  state, read out of the source with the highlighter's own patterns, so a button
  cannot say a phrase is bold while the ink beside it says otherwise. Link has
  no such state to toggle: it needs an address, and an address has to be typed.
  So it is the one control here with a caret on it, and it opens a small form
  with Text and Address rather than dropping `[text](url)` into the sentence and
  leaving the writer to find the two halves of it. The form arrives knowing what
  it is for — the selected words already in Text, a selected URL already in
  Address, both filled in and a **Remove** button when it is opened inside a
  link. Focus lands on the first empty field, which is the address whenever
  there is already something to hang it on. Enter applies, Escape returns to the
  text.
- **Bold writes `**`; italic writes `_`.** Markdown has two marks for emphasis
  and the highlighter honors both, so the buttons recognize either — a writer
  who typed `*quick*` by hand gets a lit italic button. What they *write* is the
  pair that keeps the two legible as different things in the source: `**bold**`
  and `_italic_`, rather than a reader counting asterisks to tell `**` from `*`.
  The one exception is inside a word, where `_` is not emphasis at all
  (`qu_ick_` renders as itself); there the asterisk is written instead, because
  the alternative is a control that writes something inert.
- **Nothing selected means "this word", not "nothing".** The caret is almost
  always in the middle of the word being emphasized, and asking a writer to
  select it first is asking them to do the easy half of the work. The link form
  takes the word under the caret as its Text for the same reason. With no word
  under the caret either, the emphasis marks go in empty and the caret lands
  between them.
- **⌘B and ⌘I, and ⌘⇧K for link.** The first two are what every writing surface
  binds. Link is not ⌘K because ⌘K is the command palette everywhere else in the
  admin, and a shortcut that means one thing in most of the window and another
  inside one box is a shortcut nobody can rely on.
- **Pressing anything in the toolbar leaves the selection alone**, including the
  controls that have nothing to do with it. See *Leaving the text drops the
  selection*.
- **A toolbar control does not toast.** Changing a setting there — a bleed
  width, say — shows its result in the source, in the control's own glyph and in
  the panel beside both. A fourth report of the same thing, floating over the
  corner of the screen, is noise. A toast is for something that happened where
  you were not looking.
- **The header has no back button, and does not say where you are.** Both are
  in the admin's own trail, one row up: `Content / Posts / Editing`, where
  *Posts* is the way out and sits where every other screen keeps it. A second
  control pointing at the same screen, in a row that is otherwise all writing
  tools, taught an author that the editor is a place with its own rules. Leaving
  through the crumb saves the entry, the same as every other way out.
- **A picker only offers what the container will take.** Inside a gallery the
  media button stays — an image is exactly what belongs there — but the library
  it opens shows images only, and its kind filter goes away. A picker that
  shows you files you cannot use is a worse answer than a shorter picker.
  What earns a permanent place in the header: the two insert tools, save state,
  status, settings, overflow, and the primary action — plus the sentence group and
  bleed, which earn conditional ones. Everything else is in the overflow menu or a
  shortcut.

### Marking the source
The editor shows Markdown, not a preview, so the highlighting *is* the typography
of the page. Two rules decide all of it.

**The words are the point.** Every syntax character — `#`, `**`, `>`, `-`, the
brackets around a link, the braces around an attribute — drops to `--fg-3`, and
the content it wraps keeps full ink. The page then reads as prose with faint
scaffolding rather than as code with prose in it. Nothing is hidden: an author
who cannot see where a `**` ends will break one.

**Nothing may change a character's advance width.** The highlight layer is a
`<pre>` sitting under a transparent `<textarea>`; if one of them lays out a
character a pixel from where the other does, the caret drifts away from the
letter it is on and the editor feels broken. So the palette is: color, weight,
slant, background, `text-decoration`, vertical padding, `border-radius`,
`box-shadow`. Never: `font-size`, `letter-spacing`, `font-family`, horizontal
padding or margin. Weight and slant are safe *because the face is monospaced* —
every weight shares one advance, and a synthesized oblique is a shear. This is
also why headings are told apart by weight rather than size.

| Element | How it is marked |
|---|---|
| Heading | `#`s dim; text full ink, 600 at h1–h2, 500 from h3 |
| Bold / italic / strike | Markers dim; content bold, oblique, or struck and dimmed |
| Inline code | Backticks dim, content on a `--surface-2` chip |
| Link | Brackets dim, **label in the accent**, target in `--fg-3` |
| Image | Marked exactly like a link: `!` and brackets dim, **alt in the accent**, source in `--fg-3`, **caption in full ink at 500** |
| Blockquote | `>` dim, quoted text `--fg-2` |
| List | Marker `--fg-2` at 600; text untouched |
| Task | `[ ]` dim, `[x]` in `--good`; the text is never struck through |
| Rule | `--fg-2` at 500 — it is a divider, it should divide. An attribute block hanging off it keeps the gray chip, because the dashes are the divider and the braces are metadata about them |
| Table | Pipes dim; **header row cells full ink at 600**; the delimiter row dim except its alignment colons, which are `--fg-2` at 600 |
| Definition list | The term's line full ink at 600; the `:` dim; the definition full ink |
| Fence | The whole run, delimiters included, in **one** `--surface-2` box with a hairline and rounded corners; language named in `--fg-2` |
| Footnote | Reference and definition marker in the accent |
| Directive | Prefix dim, **name in the accent**, label in full ink |
| Attributes | The gray chip, wherever they appear |

Consequences worth keeping:

- **A link's label is read in the sentence and its target is not.** Coloring the
  whole `[label](url)` in the accent makes a paragraph with three links unreadable.
  The label takes the accent; the URL steps back to `--fg-3`.
- **An image is marked as a link**, because that is what it is — a reference out
  of the document that happens to point at a picture. Alt text sits where a
  link's label sits and takes the accent; the path steps back the same way. The
  quoted caption is the exception: the reader sees it, so it takes full ink at
  500 while the quotes around it dim. Three parts, three weights, one glance.
- **A directive is not boxed.** Its name carries the accent and that is enough.
  The box is reserved for **the component the caret is inside**, so a highlight
  in the source always means *you are here* rather than *this is a component*.
  A container marks its **opener and its closer only** — never the body between
  them. The body is the writing; putting a tint behind three paragraphs an author
  is in the middle of is the opposite of a writing surface, and the two marked
  lines already say where the container starts and stops.
- **A selection is a tint, not a fill.** The textarea's own text is transparent —
  every color on screen comes from the `<pre>` underneath it — so an opaque
  selection background paints over the only legible copy of the text. The
  selection is a translucent accent (`color-mix`, ~26%) on the textarea, and
  transparent on the `<pre>`, so selected text keeps its highlighting and its
  contrast. This is the same class of bug as the advance-width rule: two layers
  drawing the same characters, and only one of them being looked at.
- **Some lines cannot tell you what they are.** A table's header row is a table
  row, and a definition list's term is a plain line of prose; only the block scan
  knows which is which. So the scan tells the highlighter — a set of line offsets
  passed in — rather than the highlighter guessing from the line alone.
- **Valid syntax is the only syntax that lights up.** A brace that is not an
  attribute block, a `*` that closes nothing, a `[` with no `]` — all stay plain
  text. The highlighting doubles as a syntax check: if it did not light up, it
  will not parse.
#### The fenced block is a box, and the box is free
A code block is the one place where the source *is* the content, so it is the
one place that gets a shape of its own. The whole run from the opening fence to
the closing one sits inside a single box — `--surface-2`, a hairline, rounded
corners — rather than a tint behind each line. Per-line stripes are ragged at
every line's end and read as damage; what the author is looking at is a block of
code, which is a rectangle.

This is the hardest thing in the layer to do without breaking the caret, so all
three parts of it are bought for nothing:

- **The run is wrapped once, in one block element.** The newlines between the
  lines go inside it; the one that used to follow the closing fence is dropped,
  because a block ends its own line and emitting the newline as well opens a
  blank one under every code block that the textarea does not have. An unclosed
  fence is the exception — it runs to the end of the source, so it keeps that
  newline the way any last line would.
- **Horizontally, a negative margin cancels the padding exactly.** `width` stays
  `auto`, so the content box is still the full measure and a long line wraps
  where the textarea wraps it. The box overhangs the column by 12px on each
  side, into padding the writing surface already has.
- **Vertically, nothing at all** — no margin, no padding, no border. The inset is
  already there: a 28px line box around a 14px face leaves 7px of half-leading
  inside the block's own top and bottom edges. Vertical padding would add height
  the textarea does not have; negative margins to cancel it collapse through the
  `<pre>` when the block is its first or last child, and the block ends up 7px
  from where the caret expects it. The hairline is a `box-shadow` for the same
  reason — a 1px border is 2px of height.
  The test for any of this is not reading the CSS. Lay the raw source out in a
  second `<pre>` that matches the highlight layer in every property that affects
  metrics, and compare where every rendered line lands: that reference is exactly
  how the textarea lays the same text out, so matching it is matching the caret.
  `70-build-runbook.md` has the probe.

### The document panel
The Document tab holds everything about the entry that is not its text, which is
a lot of different kinds of thing. It works because it is a **list of label →
value rows**, not a stack of form fields.

```
Status      ✓ Published        ⌄
Visibility  ◉ Public           ⌄
Date        Tue 29 Sep 2026  08:20 am  ⌄
Slug        grid-survives-editors
```

The label states the question in flat ink; the value answers it in the accent
and opens whatever control that answer needs. **Boxes are spent only on things
you type into.** Ten bordered inputs stacked down a sidebar make ten identical
targets out of ten different decisions; a row list makes the *answers* the thing
you scan, which is what someone opening this panel came for.

Order is by how often it is touched: **Publish, Featured Image, Authors,
taxonomies, Summary, custom fields.** Every group has a heading, and a heading
may carry one piece of **right-aligned meta** — "2 selected", "84 / 160" — so a
glance tells you the state of a group you are not looking at.

**Status and Visibility are the same shape of decision**, so they share one
menu: a short list where every option carries a line saying what it *does*
("Not on the site. Only editors see it", "Readers need the password you set").
A status a person picks once a week is worth one sentence of explanation.

**A menu shows what is in force by filling the row, not by ticking it.** The
chosen row takes the accent-soft background and accent ink that the hovered row
takes — the same signal, for the same reason, in the same place. A tick on the
end of the row is a second thing to look for in a list of three, and it says
what the fill already says. This holds for every menu that carries a choice:
status, visibility, bleed.

**The date opens a month.** Monday-first grid, today ringed, the chosen day
tinted — **soft, not solid**: a filled accent square is the loudest thing in the
panel and it is only saying "this one". A line under the rows says what the date
**means** — "Goes live 6 days from now", "Published today" — because a date on
its own is a fact an author still has to do arithmetic on.

**Time is two fields and a switch, on a 12-hour clock.**

```
[08] : [20]   ( AM | PM )
```

Two short monospace inputs and a two-button group — not one string to parse, and
not the native `<input type="time">`, which brings its own AM/PM widget, its own
clock glyph and its own idea of what a control looks like, none of which match
anything else here. The fields are `type="text"` with `inputmode="numeric"` and
`maxlength="2"`, so a phone offers digits without the browser also offering a
stepper. Hours read 1–12, and noon and midnight are written `12`, not `00` — the
one place where padding a number would say the wrong thing. AM/PM is a
`role="group"` pair with `aria-pressed`, not a select: a two-way choice that is
always on screen should not cost a click to read. Storage stays a `Date`;
12-hour is a presentation layer applied on render and undone on commit.

**Accent is ink here, not fill.** Values, links and the selected day are
accent-colored *text*; the only solid accent in the whole panel is a checked
checkbox. Tags are neutral chips with a border. A sidebar of blue fills reads as
a sidebar of buttons.

**A hierarchical taxonomy is one box, not three controls.** Search, tree and
*New Topic* share a single bordered container with dividers between them — three
boxes with gaps between them read as three unrelated things that happen to be
stacked. Every row carries its entry count, because a term's weight is worth
knowing *before* you file something under it. Searching keeps a matched term's
ancestors visible, and indentation rather than disclosure triangles carries
depth: you are picking, not browsing. The footer opens an inline **name +
parent** form, so a term is created where the author is already looking.

**The parent dropdown shows the tree, not an index.** Options come out in tree
order, each carrying its depth, and the list indents them accordingly:

```
Typography
  Variable fonts
  Specimens
Interfaces
  Tables
Field notes
```

A flat alphabetical list of candidate parents is technically complete and
practically useless — it hides the shape you are adding to, which is the only
thing you are looking at the list to learn. The same rule governs the **entry
parent** selector on hierarchical content types.

**A flat taxonomy is a token field.** Chips and a bare input share one box.
Typing filters, Enter takes the first suggestion, Enter with nothing matching
**creates the tag**, Backspace on an empty input removes the last chip.

**Authors are people, not a select.** Each one is an avatar, a name and a role,
with the first marked *Lead*; removing is an × that appears on hover. Adding is
a **search**, because a site with forty accounts makes a dropdown useless and the
same field still works at four. A type declares whether it takes more than one.

**An entry always has at least one author.** With one author left, the × is not
shown and the handler refuses the removal — the rule is enforced in the data
path, not only in the markup. Under the search sits one line of copy saying why
("An entry always has at least one author, so this one cannot be removed until
another is added"), because a control that silently stops working reads as
broken. To hand an entry over, you add the new author first and then remove
yourself; there is no moment where the entry belongs to nobody.

**The featured image reuses the image component**, at 16:9 rather than 4:3
because that is the shape it will be used in, with the same hover Replace and
Remove over a veil. One preview component, two places; the alternative is two
things that look alike and behave differently. It appears only for types that
declare one — a switch in the type builder beside the index-page switch.

### The inserters
Four ways to put something in an entry. The shape of each follows how much of a
decision it is and where in the document the result lands.

**Block components slide in from the left.** Choosing one is part of writing,
often with browsing involved, so the panel widens the layout aside and stays
open — it never covers the sentence you were writing, and it does not snap shut
after one pick. It does close when another of the toolbar's overlays opens; see
*One thing is open at a time*.

A tile is **an icon and a name, and nothing else**. No border, no plate behind
the icon, no kind label. Twenty-odd bordered boxes read as twenty-odd objects
competing; the same twenty-odd names with space between them read as a list you
can scan. The highlight appears only where the pointer or the keyboard is.
Anything more about a component — its description, whether an addon supplied
it — goes in the strip at the foot of the panel, which follows the selection.

**There is no category control at all.** The grid is already headed by
category, so a filter for them was a control standing in for a scroll — and it
cost a band of the panel that the components themselves should have. The search
field is the only control in the panel: one plain row, no box around it, the
full width. Twenty-odd items is a scroll, not a search problem; the field is
there for the person who already knows the name.

**Three tiles across.** A tile with no border and no icon plate needs less room
than one with both, and the width it gives back buys a third column — four
category groups in view at once instead of two. The count of what fits is the
point: a panel you scroll twice is a panel you stop opening.

Keyboard maps to the grid: left and right step, up and down move a row, Enter
inserts, Escape closes. Hovering a tile highlights it, so pointer and keyboard
agree on what Enter would do.

**Typing `/` at the start of a line opens the same panel**, with what follows
filtering it. The query lives in the document while it is typed and is removed
when something is inserted, so an abandoned slash is just text. This is the
path most authors end up using; the toolbar button is the discoverable one that
teaches it.

#### The Markdown elements are in the same panel
The panel is not only for components. **The leaf and container level things
Markdown already has are tiles too** — Heading, Quote, List, Definitions, Code
Block, Table, Divider — because someone reaching for a table should not have to
remember how to draw one in pipes, and because that is the question they are
actually asking. They are **interleaved into the same category groups**, not
parked in a "Markdown" group of their own: a group heading that separated them
would teach exactly the distinction the rest of the editor works to stop
mattering. Within a group the Markdown elements come first, because they are
the things reached for most and the components build on top of them.

- **No paragraph.** A paragraph is what you get by typing, and a tile that
  inserts an empty line is furniture.
- **No inline emphasis either.** Bold and italic wrap a word already written,
  which is a different act from placing a block — the same reason inline
  components have their own dropdown rather than a tile here.
- **Each one is named and drawn the way `BLOCK_KINDS` already names and draws
  it**, so a heading is called *Heading* in the inserter, the outline, the
  element tab and the breadcrumb. One object, one name, four places.
- **The placeholder is inserted selected, not just typed.** Choosing *Heading*
  writes `## Heading` and leaves the word *Heading* selected, so the first
  keystroke replaces it. A caret sitting beside a placeholder asks the author to
  delete something the editor wrote.
- **A search alias comes with each one**, so "hr", "rule" and "separator" all
  find *Divider*, and "dl" finds *Definitions*. The name people look for is not
  always the name the thing has.
- **What lands has to look like the thing it is.** Inserting a code block puts
  three backticks in the document, and three backticks on their own are the
  least legible thing Markdown has — which is why the fence gets a real box in
  the source, and why typing the third backtick writes the whole block. An
  inserter that produces something an author cannot see the shape of has not
  finished the job.
  Where a Markdown element and a component would collide, **Markdown keeps the
  name** and the component is renamed to say what it adds — see the decisions log
  on *Data Table* and the dropped divider component. The test is the one that
  already governs images: if Markdown says it, Markdown says it.

**Inline components have their own dropdown.** They are not in the panel at all,
because putting a badge inside a sentence is a different act from putting a
gallery between two paragraphs: the panel's grid implies "pick a block to place
here", and inline components do not go *here*, they go *inside this word*. Its
button carries a caret so it reads as a menu, and the menu is a short list with
a description on each row — there are few of them and each is one decision.

**Icons are a modal, and it is the same modal the media library uses** — a
header, a search field, a left column of categories, a grid, and a footer naming
the selection with the directive it will write. An icon set is a *library*: three
dozen glyphs now and more later, and nobody remembers which one is called
"schema". A 300px popover turns that into a scrolling memory test. Categories go
down the left rather than across the top, because a vertical list takes a column
that a wide modal has to spare while a horizontal one eats the height the grid
needs, and it holds its shape as the set grows. Below 760px the same list turns
into one scrolling row.

Its button is a shapes glyph — triangle, square, circle. Avoid a sparkle or a
star here, or anywhere that is not generation: a sparkle now reads as "AI"
before it reads as anything else, and an icon that promises the wrong thing is
worse than a dull one.

**Media inserts Markdown for images**, and a component only where Markdown has
no syntax: an image becomes `![alt](src "caption")` with the library's own alt
text and caption filled in, a video becomes `::video`, anything else becomes
`::file`.

**Media is a dropdown onto a modal.** The toolbar button offers two ways in —
*Media library* and *Upload a file* — because they are different acts with
different expectations: one browses what exists, the other adds something new,
and an author usually knows which before they click. Both land in the same
modal on the matching tab, so the menu is a way in, not a fork.

The modal itself is the same picker used everywhere else in the admin, so a file
is chosen the same way wherever you are.

The rule: **a panel for what you browse while writing, a dropdown for a short
fixed list, a modal for a library.** The question is not how important the
choice is, it is how much there is to look through — and whether the looking
happens while a sentence is half-written.

Both modals share one shell: head, filter bar, body, footer, with the primary
button disabled until something is chosen and labeled for the errand. Two
libraries that behave differently is two things to learn.

### Every element is an object
The element tab does not only show components. **Every block-level thing in
the source has a panel** — heading, paragraph, list item, quote, code block,
table, divider, image, directive — because every one of them can take a class or
an id, and an author should not have to remember where the braces go.

**The most specific element wins.** One resolver runs over both scans — the
directives and the blocks — and takes whichever span containing the caret is
smallest. Inside a callout the caret is in a *paragraph*, and the callout is
that paragraph's parent, which is what the breadcrumb is for; the container only
wins on its own opening or closing line, where there is no block to lose to.
"Component or block" was never the question a writer is asking, and an editor
that answers it anyway reports the wrong thing every time a component has
content in it.

**The resolver watches identity, not the index.** An element is remembered by
where it starts and what it is called, not by its position in the scan. Deleting
a directive above the caret shifts every index below it, so the same number can
name a different element — and a panel that only watches the number goes on
describing the one that left.

Two controls are the same in every one of them, because the syntax is the same
everywhere: **Classes** (space separated, without the dots) and **ID**. Above
them sits whatever else that block has to say:

| Block | Its own controls |
|---|---|
| Heading | Level, 1–6, which rewrites the hashes |
| Code block | Language, written as the fence's info string |
| List | Its type — Bulleted, Numbered, Task — and its Content |
| List item | Whether it is a task, and whether the task is done |
| Definition list | Its Content: the terms and definitions in it |
| Image | The picture, alt text and caption |
| Everything else | Attributes alone |

Rules that keep it from becoming noise:

- **A blank line belongs to the element above it — at its own level.** Otherwise
  the panel empties itself every other line as you arrow through a document,
  which is worse than being a line behind. The level clause is what keeps it
  honest: on the blank line under a closed `:::`, walking back naively lands on
  the container's last paragraph and the breadcrumb then says you are somewhere
  you have just left. Candidates buried in a container the caret has stepped out
  of are skipped, so that line resolves to the container itself.
- **Blocks get no highlight in the source.** The ring means *you are here*, and
  the caret already says that; tinting the whole paragraph you are typing in
  would undo the writing surface. A fenced block's box is not an exception — it
  is there whether the caret is in it or not, because it says *this is code*,
  not *you are here*.
- **The outline is a state, not a footer.** Once the panel always has something
  to show, printing the whole index under every heading and paragraph is
  padding. It is reached from **one quiet row** — icon, "Elements in This
  Entry", the count, a chevron — at the bottom of the **Document** tab, and only
  there. Picking a row, or moving the caret, turns the list back into that
  element's options.
- **Attributes go where the syntax puts them**: at the end of the block's last
  line, after the info string on a code fence, and on a line of its own *above*
  a list. Three placements, each one the framework's, none of them the editor's
  invention. *Provisional:* the placement for tables and dividers follows the
  end-of-block rule, which the parser should be checked against — see
  *A divider can carry attributes* below.
- **A list is an element, and a list item's parent.** Items come out of the scan
  flat, so the runs and their nesting are rebuilt from indentation into `list`
  records. A list is what you reach for to restyle the whole thing rather than
  one line of it, and it is what makes *List Item* a thing with a parent to
  climb to. An item's `first`/`last` stay on its own text, so an attribute still
  writes to the right line; its **span** reaches over anything nested under it,
  so a nested list belongs to the item it was written under rather than to that
  item's list.
- **The List panel offers what a list has: its type.** Bulleted, Numbered, Task
  — switching rewrites every marker at that list's own indent, renumbers as it
  goes, and leaves nested lists and the items' text alone.
- **A list carries its attributes on a line above it.** This is the one place
  the syntax puts an attribute block *before* the thing it describes, because a
  list has no last line of its own to hang one off:
  ```
  {.checklist}
  - list
  - item
  - three
  ```

  So that line is not a paragraph — the scanner skips it and the list claims it,
  and the list's span reaches up to include it, which is why selecting the list
  from the outline or the breadcrumb selects the thing the attributes are on.
  The panel's Classes and ID fields **write the line when it is needed and take
  it away when both fields are emptied**: a bare `{}` left sitting over a list is
  litter the author did not write. Everything else keeps attributes at the end
  of its last line, as before.
- **A divider can carry attributes.** `---` has no last line to spare, but it
  does not need one: `--- {.bleed-full}` keeps the attribute block at the end of
  the line the way everything else does, rather than inventing a second
  above-the-line exception for the one element with nothing else on it. The
  block scan and the highlighter both allow it, and the highlighter splits the
  line in two — dashes in the divider's ink, the braces in the gray chip they
  get everywhere else. *Provisional:* the parser has not been checked against
  this. If it rejects a trailing attribute block on a rule, the fallback is the
  list's placement — a line above — and both the scan and the writer change
  together.
### Content: what is inside this one
An element that holds other elements gets a **Content** group in its panel: a
list of the level directly beneath it, each row selecting that element the same
way an outline row does.

```
CONTENT
  Term        Container
  Definition  A component that wraps other content…
  Term        Leaf
  Definition  A component that takes a single line.
```

**One level, never the subtree.** The whole tree is what the Outline is for;
repeating it inside a panel would make every callout a second outline, and the
deeper rows belong to the elements that own them — a nested list's items are the
nested list's business. One level answers the question the panel is being asked:
what is in *this*.

The elements that hold content are the ones that open a level in the outline:
**a container directive, a list, a list item, a definition list.** The same
predicate drives both, so a thing that indents its children in the outline is
exactly a thing with a Content group, and neither can drift from the other.

Rows are the shared outline row with its indent dropped, because inside a
Content group everything is one level by definition — and because an element
should look like itself wherever it is listed.

### Definition lists
Terms and their definitions, in the syntax the framework reads:

```
First Term
: This is the definition of the first term.

Second Term
: This is the first definition of the second term.
: This is a second definition for the same term.

Term Three
Term Four
: Multiple terms can share a single definition.
```

- **The marker is exactly one colon.** Two would be a leaf directive, so the
  scan and the highlighting both require `:` not followed by another.
- **Groups separated by a single blank line are one list.** That is how they
  render — three `<dl>`s in a row is not what the author wrote — so the scan
  keeps walking across one blank line as long as what follows is another
  term-and-definitions group.
- **The list, each term and each definition are all elements.** *Definitions* is
  the container, carrying the count ("4 terms · 4 definitions") and a Content
  group; *Term* and *Definition* sit inside it and take attributes of their own.
- A term is a plain line of prose, so nothing about the line says it is a term —
  see *Marking the source* on why the scan has to tell the highlighter.
### The editor finishes what you started
The source is the document, so the editor writes source rather than maintaining
a model beside it — and where a construct takes more than one keystroke to
become valid, the editor supplies the rest. Two rules, one principle: **never
make an author repeat a marker the syntax already implies, and never leave them
holding half a construct.**

#### Enter carries the marker
A list is a run of lines that each repeat a marker, so the editor repeats it.
Enter at the end of `- The first point` opens `- ` on the next line; Enter in an
ordered list opens the next number and **renumbers the whole run**, not the tail,
because a list with an item pushed into the middle is wrong from that point
down. A task item opens another `[ ]`, unticked.

**Enter on an item with nothing in it ends the list.** The marker goes away and
the caret lands in a paragraph. That second press is how every editor people
already use ends a list; without it, the only way out is to delete characters
you did not type. Blockquotes work identically: Enter carries the `>` down,
Enter on a bare `>` takes it away. Tables work the same way: Enter opens
another row with the same columns, and — if the table has no delimiter row yet —
writes the one the syntax requires first, because a table without it is not a
table. Enter on a row of empty cells ends the table.

One trap worth naming: a row of empty cells is all pipes and spaces, which the
delimiter pattern also matches. **A delimiter row is only a delimiter row if it
has dashes in it**, and every test for one has to say so, or the second Enter
silently does nothing.

**The escape leaves a blank line above the caret.** A paragraph written straight
under a list item is a lazy continuation of that item, not a paragraph — so
ending a list this way has to produce source that actually parses as what the
author just did. Getting the markers right and the blank line wrong would be a
bug you only find at publish time.

#### The third backtick writes the block
A fence is the one construct that is **invalid until its other half exists**. An
author who types ``` and starts writing has a document that does not parse, and
will not find out until they publish. So the third backtick writes the whole
element — opening fence, a line to write on, closing fence — the same way Enter
carries a list marker down rather than making the author type it again.

- **The caret stays where it was**, on the opening fence, because that is where
  the language goes. Jumping it into the body would put the one thing an author
  types next out of reach.
- **Enter from there steps into the block**, rather than pushing a blank line
  into it. The line is already there; inserting another would put an empty line
  above code the author has not written yet.
- **Three backticks, alone on the line, with the caret at the end of them.**
  Backticks typed into a sentence are inline code and none of this business, and
  backticks with text already after them on the line are not an opening fence.
- **An odd number of fence lines means the block is open.** That is the whole
  test for whether to write a closer: odd means this fence opened something
  nothing closes, even means the author just typed a closing fence themselves
  and writing another would close the same block twice. It also means an author
  who deletes a backtick and retypes it does not get a second closer.
  The same rule would apply to any construct with a required second half. There
  are only two in this syntax — a fence and a container directive — and the
  inserter already writes both halves of a container.

### The breadcrumb
The bottom bar's left half says where the caret is, from the entry down to the
smallest thing holding it:

```
Post  ›  List  ›  List Item  ›  List  ›  List Item
```

A line and column number says where you are in a *file*. This says where you
are in a *document*, which is the question someone writing one actually has —
and it answers the one a nested structure always raises: what am I inside?

- **The root is the content type**, named the same way the drawer's first tab is
  — *Post*, *Page*, *Topic*. It is the entry itself, so clicking it opens that
  tab.
- **Every crumb is a way in, not a label.** Clicking one moves the caret to that
  element, scrolls the source to it, and opens the drawer on its options. That
  includes the ancestors, which is how you select the list a list item is in
  without hunting for its first line.
- **Ancestors are containment, computed, not stored.** The path is every element
  whose span swallows the current one, outermost first. Nothing has to maintain
  a tree; the same spans that drive the outline drive this.
- **A component crumb is accent, the rest is flat ink** — the same rule as the
  source and the outline. The last crumb is the one you are in, so it is full
  ink and never the one that gets truncated: crumbs shrink from the middle.
#### Leaving the text drops the selection
A breadcrumb still naming a callout after the writer has clicked away is
pointing at something they are no longer in — and the element tab beside it is
offering options for it. So **when the caret leaves the text, the selection
leaves with it**: the breadcrumb falls back to the entry alone, and the element
tab says, in words, that there is nothing under the cursor.

- **The root becomes the last crumb**, in full ink rather than dimmed as an
  ancestor of nothing. It is where you are.
- **The editor's own chrome is the exception — all of it.** The toolbar, the
  footer, the settings drawer, the inserter and the pickers act *on* the entry
  or on whatever is selected in it. Reaching for one of them is never a way of
  saying "I am no longer in this element", and treating it as one empties the
  panel out from under the field being typed into. This is not a list of the
  controls that happen to need it — the status pill and the word count need
  nothing from the selection, and they are exempt too, because a rule that
  exempts *most* of the toolbar is a rule nobody can predict.
- **Leaving the editor's furniture is what clears it**: the rail, the entry
  title, the Document tab inside the drawer, the margin beside the column,
  another screen. That is what "clicking outside an element" means in practice,
  and the cases are not worth enumerating in the interface — the rule is *the
  caret is somewhere or it is not*.
- **Where the press landed decides it, not where focus went.** Half the toolbar
  is plain spans; pressing one blurs the text and leaves focus on the body,
  which is indistinguishable from clicking the margin. So the press is recorded
  as it happens and the blur that follows asks where it landed. A keyboard Tab
  away carries no press, so it is judged on focus alone, which is right.
- **Clicking back into the text restores it**, because the resolver runs on the
  click the same way it runs on every caret move. Nothing has to be remembered
  across the gap.
### The editor's menu
Everything the editor can do that is not the one primary button, in a menu
**to the right of that button** — the last thing in the toolbar, where a menu of
everything else belongs, rather than wedged between the status and the action.

Sections, because a flat list of eight actions is a list nobody reads:

| Section | Holds |
|---|---|
| **View** | Settings panel, Outline, Focus mode, Preview |
| **Entry** | Copy link, Duplicate, Revisions, — , Move to Trash |

- **No inserters here.** Putting something *into* the entry is the toolbar's job
  and it is one keystroke away; a menu that repeats the toolbar is a second place
  to look for the same thing, and the two drift apart the first time one of them
  gains an item.
- **The trigger is a vertical ellipsis**, because it sits at the end of a
  horizontal row of controls. A horizontal one reads as *more of this row*; a
  vertical one reads as *a list opens below*.
- **Sections are named, not just divided.** A rule between two groups says they
  differ; a heading says how.
- **Shortcuts are printed here**, right-aligned, which is why the footer no
  longer has to carry them. A menu is where someone looks for a command; a
  status bar is where they look for a number.
- **An index page and a taxonomy term lose Duplicate and Move to Trash** — both
  are singular and permanent. Leaving items out beats showing them disabled; a
  menu of dead entries reads as a bug.
- **The destructive item gets a divider**, always.
### The Outline
One list of everything the document is made of, in source order — the Markdown
the author wrote and the directives they placed, together:

```
  Paragraph   An opening paragraph. Nothing here is rendered…
  Callout     Before you start
  │ Paragraph   This is a container component. The cursor…
  Heading 2   How it works
  Quote       A blockquote keeps the marker faint and the…
  List        Bulleted · 4 items
  │ List Item   The first point
  │ List Item   The second point, with a nested list
  │ │ List        Numbered · 2 items
  │ │ │ List Item   Ordered items are marked the same way
  Table       3 columns
  Code Block  php · 2 lines
  Entry List  post
```

**One list, not two.** Splitting components from Markdown would ask the author
to know which of the two a thing is before they could go looking for it, and
that distinction is exactly what the rest of the editor works to stop mattering.
A table and a callout are both things in the document; the outline is the
document's shape, and half a shape is no shape.

**Container and leaf level only — inline directives are left out.** A badge or a
footnote reference lives *inside* a sentence, so it is not something the
document is made of; listing them would bury the structure under every scrap of
markup in the prose. The three things that own a line of their own are what
appear.

**The excerpt is the point.** "Paragraph" eight times in a row tells you nothing
about the document you are looking at, so every row carries one line of what is
actually in it: the heading's words, the quote's first sentence, a code block's
language and length, a table's column count, a component's title attribute. A
divider has nothing to say and says nothing.

**The type name is a column; the excerpt flows out of it.** Pushing the excerpt
to the right edge would make a key/value table out of a table of contents. The
name is quiet mono in a fixed column and the excerpt reads down the page like
the document it describes.

**The name is accent only when it is a component.** That is the same rule the
source highlighting follows: the accent means *someone placed this*. Ordinary
Markdown keeps the flat ink it has everywhere else, so one glance down the list
separates what was inserted from what was written — and because an image is
Markdown, an image's name is flat too.

**Depth is containment, drawn with indentation and a hairline.** Anything
starting before a container closes is inside it — and the things that open a
level are a container directive, a list, and a list item, which is what puts a
nested list under the item it was written under. No disclosure triangles: they
would ask you to open the document a second time to see what is in it.

**A line that is nothing but an image is listed as the image**, not as a
paragraph that happens to contain one — otherwise the same object appears twice
under two names.

Every row is a way to select: it puts the caret in that element, scrolls the
source to it, and turns the panel into that element's options.

### An image is Markdown
There is **no figure component**. An image is what Markdown already says it is:

```
![alt text](/media/2026/06/icon-dream.webp "The caption"){.bleed-wide}
```

Four parts, each with a job: alt text for anyone who cannot see it, the source,
the quoted title — which this framework renders as the **caption** — and an
attribute block, here carrying a bleed class. A component wrapping the same four
things would be a second syntax for one object, and the entry's source would
then depend on which button the author happened to press.

So the editor makes the Markdown itself selectable. An image is scanned like a
directive, appears in **the Outline**, names the element tab, and gets the same
panel: **Variant** (Default, Float left, Float right), the **image itself**,
**Alt text**, and **Caption**. Its width is not in that list — width is bleed,
and bleed is one control in the toolbar that every leaf and container element
shares.

**The panel shows the picture, not its file name.** A path in a read-only text
field asks someone to recognize a photograph by its slug. The preview is the same
4:3 cropped frame the library uses, so a file looks the same wherever it appears,
with the path and dimensions on one quiet line beneath it. **Replace** and
**Remove** sit on the image itself on hover or keyboard focus, over a veil that
still lets it show through — the actions are on the thing they act on, and the
panel stays two controls lighter until someone reaches for them. An image whose
file is not in the library says so rather than showing a broken frame.

- The **caption field is called Caption, not Title**, because that is what it
  does here. Naming a field after the syntax rather than the effect is how an
  interface ends up teaching its own implementation.
- An image's variant is a **class**, not `variant=`, because that is what the
  framework reads for an image. Same idea as a directive's variant, spelled the
  way images spell it — changing it swaps that one class and leaves every other
  class and attribute, bleed included, alone.
- An **empty caption writes nothing**, not an empty pair of quotes.
- The inserter still lists **Image** under Media, but choosing it opens the
  media library rather than writing a directive. The entry point stays where
  people look for it; only the output changed.
  The same holds in reverse: video and downloads *do* get components, because
  Markdown has no syntax for them. The test is whether Markdown already says it.

**`:::figure` is a container, not an image.** It wraps *anything* that needs a
caption and a width — a table, a gallery, a code sample, an image — and its own
options are the caption and the alignment. That is a different job from an image,
which is why it can exist alongside one without being a second way to write the
same thing: an image is the content, a figure is the frame around content.

### Bleed
How far an element reaches past the text column. Three steps, and only two of
them are classes:

```
Base          the width of the text column — no class at all
bleed-wide    wider than the text, as much of the margin as the theme gives
bleed-full    edge to edge, ignoring the measure entirely
```

**Base is the absence of a class, not a class called base.** It is the width the
element already has, so writing one would be recording the default in the
source — the same mistake `variant=default` would be, and the same answer:
Default writes nothing. Only the two steps that actually bleed are written, and
an entry nobody has widened carries nothing saying so, which is what lets a
theme change what the measure is and have that reach every entry ever written.

**The prefix is `bleed-`, not `stretch-`.** What these do is bleed out past the
measure; *stretch* says something about the element's own geometry that is not
what happens. The name is the behavior.

#### The control
**One button, with a caret, opening a menu** — the same shape as the media and
inline inserters beside it, because it is the same kind of thing: a button that
asks a question with a short list of answers.

```
[ ▭ ⌄ ]
```

- **One target, not two.** A split button offered two hit areas 22px apart for
  what is one decision with three answers, and the half that toggled could only
  ever reach two of them. The menu is the control; the button is the way in.
  Pressing it again closes the menu, the way every other menu in the toolbar
  behaves.
- **The icon is the readout, and there is no name beside it.** Three widths,
  three glyphs — two rules for the text column with a bar between them that
  matches the column at Base, overruns it at Wide, and loses its ends at Full
  because it does not stop. Every other control in that row is a bare icon in a
  fixed position; a word on this one would be the only word there, saying what
  the glyph already says. The names live in the menu, where they are being
  chosen between, and in the button's `aria-label`, which reads *Bleed: Wide*.
- **The button takes the accent while the element is bleeding.** The glyph says
  which width; the tint says *this element has been widened*, which is the thing
  you want to notice without looking for it.
- **The menu is all three, named and described**, with the one in force filled
  the way a hovered row is — no tick. Base is a real entry in it, because going
  back to the column is a choice like the other two. There is no separate
  "None": Base is none.
- **Picking one says nothing.** The width changes in the source, the glyph
  changes with it, and the panel is open beside both. See *A toolbar control
  does not toast*.
#### When it is shown
**Top level only.** The control appears for a leaf or container element at the
top of the document and is hidden everywhere else. An element inside a
container, a list or a figure is bounded by its parent whatever class it
carries, so offering the control there would be offering something that does
nothing. Inline directives never qualify either: they sit inside a sentence,
which has no width of its own.

*Provisional, deliberately.* A container that is itself bleeding could in
principle pass the room down to its children, and a theme may well want that.
When it does, the predicate that decides visibility is the one place to widen —
it is a single function over the outline's own depth, so the rule can get more
specific without anything else moving. Until then the simple rule holds,
because a rule an author can state in one sentence beats one they have to
discover.

#### How it is written
**Bleed owns width; a variant owns style.** The two used to overlap: an image
and a figure each carried `stretch-wide` and `stretch-full` in their variant
list. They no longer do. A variant list that also carried widths would be a
second way to set the same class, and the two would disagree the first time one
of them gained an entry.

**The class is swapped on the raw attribute block**, not round-tripped through
the attribute parser. That parser reads `{.bleed-wide}` as a key named
`bleed-wide` and would write it back as `bleed-wide=true`, quietly destroying
every class on the element. Reading and writing a class is string work on the
`{...}` itself, and both bleed and the image variant go through the same helper.

**Where the class lands is the syntax's business**, so bleed writes to whatever
line that element's attributes already live on: the end of the last line for
most things, above the list for a list, after the info string on a code fence,
and at the end of the rule for a divider. Going back to Base leaves no litter —
an emptied attribute block is removed, and a list's attribute line goes with it.

### Variants
Every component has a **variant**: a named style the theme provides. The
component decides what the thing *is*; the variant decides how it looks. A
callout is a callout whether it is tinted, bordered or compact.

- Variants are **named and described**, never numbered or previewed as a
  swatch. "Bordered — no fill, a rule down the left and plain text" tells an
  author what they are choosing; a thumbnail of a rectangle does not.
- Every component has a **Default**, and Default writes **no attribute at all**.
  An entry that has never been styled carries no styling in its source, so a
  theme change reaches it. Bleed's Base is the same rule in the other half of
  the model.
- Any other variant writes `variant=key` on the directive, beside the options.
  It is an ordinary attribute, so nothing new is needed to parse it.
- The selector sits **at the top of the element tab**, above Options, because
  it usually changes what the options mean.
- **Themes own the list.** A component ships with the variants its theme
  defines; an entry referring to a variant the current theme does not have falls
  back to Default rather than failing. This is the seam where a site's design
  system meets its content, so it is worth keeping narrow: a handful of named
  looks, not a style panel.
- **A variant never sets width.** That is bleed's job, and the two do not
  overlap.
