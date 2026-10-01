# Decisions log

> **Part of the Meridian admin design direction**, in this project under
> `design/`. Start at `design/00-project-brief.md`.

Choices that are settled, and the reasoning that settled them. **Read this
before re-opening something that looks arbitrary** — most of these look like
preferences until you know what they were chosen against.

Append to this file. Do not edit an existing entry except to record that it was
reversed, and when that happens say what changed the answer.

---

## The shell

**The rail never navigates — not even Home.** Clicking a section changes what
the panel offers and nothing else; the work area keeps whatever is open. There
is no exception for a single-screen section, however tempting: an author halfway
through an entry who taps Content to check a term name would lose the entry, and
a rule that holds four times out of five is a rule nobody can rely on. Two
levels, two jobs — the rail scopes, the panel navigates.

**Three rail sections, not more.** Home, Content, Config. Taxonomies are content
so they sit with the types; *defining* a type is configuration so the builder
sits with settings. The test for a new section is whether someone would go
looking for it by name, not whether the things in it are related.

**A rail button is a toggle for the panel it opens.** Pressing the section you
are already in closes the panel; pressing it again brings it back. Pressing a
*different* section still opens it, because that is a different question with a
different answer. Before this the only way to shut the panel was the collapse
button in the top bar, nowhere near the thing it collapses — so the rail could
open the panel and never close it, and the control that did close it was one an
author had to go looking for. This does not soften *the rail never navigates*:
closing a panel is not changing the screen. At narrow widths the rail and panel
are one off-canvas drawer, so the same press closes that instead.

**The editor opens with both panels closed**, and the section panel is put back
the way it was found on the way out. Collapsing it for the editor is a courtesy,
not a setting the editor gets to change on the user's behalf.

**The top bar's collapse button is gone.** It was the only way to shut the
section panel, and it sat in the corner of the screen furthest from the panel it
acted on. Now that a rail button toggles its own panel, the control is on the
thing it controls and the second one is just a second place to look.

**The trail says the rail section, the screen, and what you are doing to it.**
`Content / Posts / Editing`, `Config / Content Types / Pages`. The site name
used to lead it; it never changed, so it never told anyone anything — a crumb
that is always the same word is a decoration. The section is the answer to
*which part of the admin is this*, which is exactly the question the rail asks,
so the two now agree.

Everything but the last crumb is a way back. The **section crumb scopes the rail
panel** — it opens that section's panel without changing the screen, which is
the rail's job, done from the trail. It always opens, never toggles: pressing a
crumb and getting nothing is not an answer. The **view crumbs navigate**, and
leaving the editor through one saves the entry first, the same as every other
way out.

**So the editor has no back button.** `Content / Posts / Editing` already has
*Posts* in it, one row up and in the place every other screen keeps its way out.
A second control pointing at the same screen, in a toolbar that is otherwise all
writing tools, was a leftover from before the trail said anything useful. The
editor's own breadcrumbs, below the title, are a different trail — they are
where you are *in the document*, not where you are in the admin.

At narrow widths the section crumb is the first thing dropped, because the
burger is right beside it and says the same thing. The screen crumb survives, so
the way back survives.

## Theming

**Both admin themes ship, and the theme is a per-account setting.** It lives on
Your Profile with the light/dark choice, not on an Appearance screen — Appearance
is about the site, and which admin chrome *you* look at is not a property of the
site.

**Every token is defined on bare `:root` first.** A token defined only inside a
media query or an attribute selector never applies to the viewers who set
nothing, which is most of them. This is the single most consequential rule in
the project and the easiest to break by accident.

**Flat by default.** `--shadow-1: none`. Borders and surface steps separate
things; a shadow is spent only where something genuinely sits above the page —
menus, toasts, modals over a scrim.

## Color

**Accent is ink, not fill.** In the document panel, values, links and the
selected calendar day are accent-colored *text*; the only solid accent in the
whole panel is a checked checkbox. A sidebar of blue fills reads as a sidebar of
buttons. This came directly from "the primary blue is jarring" and it turned out
to be the rule, not a one-off fix.

**One accent hue.** A second would have to mean something, and nothing in this
admin needs a second meaning that status colors do not already carry.

**Status is never color alone.** Every status carries a word.

## The editor

**Two writing surfaces ship, and Markdown is the document.** A Markdown editor
and a visual editor, because they answer to different people: someone who
writes in Markdown daily is slowed down by a surface that hides it, and someone
who has never seen a fence should never be shown one. Not a choice and not a
migration — both.

The file is the entry. A parse tree is something either surface may build to do
its job and throw away; the moment a tree becomes the thing that is saved, the
source starts drifting, and it drifts in text the author wrote and did not
touch. Two surfaces is what makes that enforceable rather than aspirational:
the other one is a click away, so any damage is visible immediately to the
person it happened to. A single-surface editor can be lossy for years without
anyone finding out.

**The round-trip is the gate, and it is the first thing to build** — before the
visual editor, as the test it has to pass. Open, change nothing, switch back:
byte-identical. Change one thing: the diff touches only that thing. The second
half is the one people forget, and an editor that rewrites the document on
every save passes the first and fails the second, where the damage shows up in
version history rather than on screen.

**The visual editor does not have to model everything.** Anything it has no
view for is shown as its Markdown, in place, in a box — editable as text,
untouched on save. That one escape hatch is what lets it ship early and grow,
and it is also the honest answer for things that are genuinely source: a raw
embed, an unrecognized attribute block, a construct from a later version of the
format.

**The Markdown surface steps aside rather than leaving, and that is what gives
both editors one undo stack.** In the visual view the textarea is moved out of
sight, not removed: it is the field every write goes through, and `execCommand`
cannot record against an element that cannot be focused. The result is worth
more than the trick — an edit made in the visual editor is reversed by ⌘Z in
the Markdown one, and the source comes back byte-identical. Two surfaces, one
history.

**The visual editor was prototyped and then taken back out.** The code is gone;
the architecture is not. Two surfaces over one Markdown source is still the
plan, the node model and the round-trip check it needs are still in place, and
what the prototype taught is written down in `30-editor.md` so the next attempt
does not rediscover it. Shelved because the Markdown editor is what is being
used now and a half-built second surface is a maintenance cost with no reader.

What stayed behind from that work, because it was never about the visual
editor: the **node model** and its round-trip invariant, **reordering** as a
model operation, and the **stacked chevrons in the toolbar** that drive it.

**The visual editor is a page, not a stack of blocks.** No frames, no hover
tints, no handles in the margin, no label on each part naming its type. The
first pass drew every node as a titled card with its options along the top and
the document came out looking like an inventory of itself. What is drawn is
what the *content* is — a callout looks like a callout because that is what a
callout looks like — and what is structural stays underneath, where it was
always going to be the editor's problem rather than the author's.

Nothing marks the block being typed in; the caret does that, the way it does on
any page. Only something you cannot put a caret into shows a quiet ring when it
is selected, so the panel beside it is describing something visible.

**Reordering moved to the toolbar.** Two stacked chevrons, no words, before the
inline tools. A handle that appears beside the paragraph you are reading is the
editor interrupting; in the toolbar the control is in one place at all times,
serves both surfaces, and names what it would move in its tooltip. The *Position*
row in the element panel went away with it — one control, not two.

**The visual surface keeps its own position.** In the Markdown editor "here" is
the caret and the textarea knows it; in the visual editor the author has
selected an *element* and there is no caret in the source at all. Routing that
through the hidden textarea's selection proved exactly as fragile as it sounds —
opening the inserter was enough to lose it, with nothing in the code touching
the selection to blame. One function now answers "where am I" for both, and
inserting from the visual surface lands the new element after the selected one,
in the gap, one newline short of the next — the snippet brings its own leading
newline and the two together make exactly the blank line that was already there.

**Prose is edited in place; structure is edited in the panel.** A paragraph, a
heading, a quote and a list are text and are typed into directly. A table, a
code block, an image and a component are not text — they are selected in the
visual surface and edited in the panel beside it, which is where their options
already live. This is why the visual editor did not need a toolbar of its own
to be useful.

**Committing a block reads it back through the paste converter.** One
HTML-to-Markdown reader for both, so the two cannot disagree about what
`<strong>` is. What the browser leaves in a contenteditable — text outside the
paragraph it started in, three text nodes where there was one — is flattened
before it is read, because a *block* reader turns each fragment into a block
and the sentence comes out in pieces.

**A node carries its raw source, so the round trip is lossless by
construction.** The node list is the entry's top-level elements in order, each
owning an exact run of characters, with the whitespace between them held as
the document's own. Reassembling emits what each node came in with; a node
re-serializes from its parsed form only at the moment something edits it,
which is the only moment the format may change and the only moment anyone is
watching. The escape hatch for an element the visual editor cannot draw is the
same property rather than a special case.

It holds across every seeded entry and across the sources that usually break a
parser: trailing spaces, no final newline, runs of blank lines, leading blank
lines, an unclosed fence, nested containers, raw HTML, an attribute block above
a list, an orphan attribute line, whitespace only, and empty. The check runs on
every scan while the prototype is being built, because the failures are cheap
to catch now and impossible to attribute later.

**Reordering is in the model, with three ways in.** ⌥↑/⌥↓ while writing, a
*Position* control in the element panel, and handles on a top-level outline row.
All three call one function, which is the point: the first feature that belongs
to both editors was built once. The caret deep inside a list inside a container
moves the container — the only answer that needs no rule — and where the panel
is describing something nested it names what would actually move rather than
letting the count look like the element at the top of the panel.

**A new behavior goes in the model layer unless it can only exist in text.**
The split is already most of the way there — the scans, the outline, attribute
writing, the component vocabulary and its `only` constraints, the panels, the
inserter, the media kind locks and the HTML converter are all surface-free, and
a second surface reuses them unchanged. Reordering elements is a model
operation, a span moved between siblings, so both surfaces get it from one
implementation. The sentence tools are not: "wrap the selection in asterisks"
is a sentence about Markdown.



**The editor shows source, not a preview.** So the highlighting *is* the
typography of the page, and two rules govern all of it: the words are the point
(syntax characters recede, content keeps full ink), and **nothing may change a
character's advance width** — the highlight layer is a `<pre>` under a
transparent `<textarea>`, and a pixel of drift puts the caret on the wrong
letter. Weight and slant are safe only because the face is monospaced.

**A selection is a tint, not a fill.** Same cause: the only legible copy of the
text is the layer underneath, so an opaque selection background hides it.

**A fenced code block gets one box, not a stripe per line.** The source *is* the
content there, so it is the one construct that earns a shape: one `--surface-2`
rectangle with a hairline and rounded corners around the whole run, delimiters
included. Per-line backgrounds end where each line's text ends, and a stack of
ragged rectangles reads as damage rather than as a block of code.

Getting it without moving the caret took three separate tricks, and each one is
load-bearing:

- The run is wrapped **once**, in a block element, and the newline that used to
  follow the closing fence is dropped — a block ends its own line, and emitting
  the newline too opens a blank one the textarea does not have. An unclosed
  fence keeps it, because it runs to the end of the source and is the last line.
- **Horizontally**, a negative margin cancels the padding exactly, and `width`
  stays `auto` so the measure is unchanged and long lines wrap where the
  textarea wraps them.
- **Vertically, nothing at all.** The inset is already there — a 28px line box
  around a 14px face leaves 7px of half-leading inside the block's own edges.
  Vertical padding adds height; negative margins to cancel it *collapse through
  the `<pre>`* when the block is its first or last child, and the block lands
  7px from where the caret expects it. That one was found by measuring, not by
  reading. The hairline is a `box-shadow` for the same reason: a 1px border is
  2px of height.
  **Alignment is proven against a reference, not inspected.** Lay the raw source
  out in a second `<pre>` matching the highlight layer in every property that
  affects metrics, and compare where every rendered line lands — that reference is
  exactly how the textarea lays the same text out. `70-build-runbook.md` has the
  probe. Reading the CSS would have passed all three of the mistakes above.

**The third backtick writes the whole block.** A fence is the one construct in
this syntax that is *invalid until its other half exists* — an author who types
``` and starts writing has a document that will not parse, and finds out at
publish time. So the editor writes the opening fence, a line to write on and the
closing fence, the same way Enter carries a list marker down rather than making
the author repeat it. The caret stays on the opening fence, because that is
where the language goes; Enter from there steps into the body rather than
pushing a blank line into it.

**An odd number of fence lines is the whole test for whether to close one.** Odd
means this fence opened something nothing closes; even means the author just
typed a closing fence themselves and writing another would close the same block
twice. It also handles deleting a backtick and retyping it, which a "did they
just type three backticks" test would not.

**The most specific element wins.** Inside a callout, the caret is in a
*paragraph*; the callout is that paragraph's parent. "Component or block" was
never the question a writer is asking, and an editor that answers it anyway
reports the wrong thing every time a component has content in it.

**A blank line belongs to the element above it — at its own level.** Without the
level clause, stepping out of a container goes on reporting its last paragraph,
which says you are somewhere you have already left.

**An element is remembered by identity, not by index.** The resolver keys on
where an element starts and what it is called, not on its position in the scan.
Deleting a directive above the caret shifts every index below it, so the same
number can name a different element and a panel watching only the number goes
on describing the one that left. Found by a probe, not by reading the code —
which is the argument for driving the prototype in a browser rather than reading
the diff.

**An image is Markdown, not a component.** `![alt](src "caption"){.class}`
already has four parts with four jobs. A component wrapping the same four things
would be a second syntax for one object, and the entry's source would then
depend on which button the author happened to press. `:::figure` survives
because it is a *different* job: a frame around any content — a table, a
gallery, a code sample — not a picture.

**Accent on a name means "someone placed a component."** A tinted chip means
"you are here." Because an image is Markdown, an image's name is flat ink
everywhere it is listed. A fenced block's box is neither: it is there whether
the caret is in it or not, because it says *this is code*.

**A container marks its opener and closer only.** Tinting the body would undo
the writing surface; the two marked lines already say where it starts and stops.

**Every block-level element gets a panel**, because every one of them can take a
class or an id and an author should not have to remember where the braces go.

**A list is an element and a list item's parent.** It is what you reach for to
restyle the whole list rather than one line of it, and it is what gives a list
item something to climb to in the breadcrumb.

**Enter carries the marker; a second Enter ends the block.** That second press
is how every editor people already use ends a list. The escape leaves a blank
line above the caret, because a paragraph written straight under a list item is
a lazy continuation of it — getting the markers right and the blank line wrong
is a bug you only find at publish time.

**One write path for the source.** The caret, the scan, the highlight and the
panel can never end up describing different versions of the text.

**The footer says where you are and how much you have written.** No shortcut
hints: a hint that is always on screen is read by nobody after the first day,
and the space it takes is the space the breadcrumb needs. Ln/Col went with them
— a line and column number says where you are in a *file*; the breadcrumb says
where you are in a *document*, which is the question someone writing one has.

**Leaving the text drops the selection — but the editor's own chrome is not
leaving.** When the caret leaves the source, the breadcrumb falls back to the
entry alone and the element tab says there is nothing under the cursor. A
breadcrumb still naming a callout the writer has clicked away from is pointing
at something they are not in.

The exception is **the whole of the editor's furniture**: the toolbar, the
footer, the settings drawer, the inserter, the pickers. Not a list of the
controls that need the selection — the status pill and the word count need
nothing from it and are exempt too. A rule that exempted *most* of the toolbar
would be one nobody could predict, and the first time a press cleared the panel
out from under the field being typed into it would read as a bug. What clears
the selection is leaving the editor's furniture: the rail, the entry title, the
Document tab, the margin beside the column, another screen.

**Where the press landed decides it, not where focus went.** Half the toolbar is
plain spans, and pressing one blurs the text and leaves focus on the body —
indistinguishable from clicking the margin. So the press is recorded as it
happens and the blur that follows asks where it landed. A keyboard Tab away
carries no press and is judged on focus alone, which is the right answer there.

**The drawer opens on the element tab when something is selected.** The caret
is inside a callout and the author reaches for the settings panel: the callout's
options are what they came for. Both orders cost one click to correct, but only
one of them requires the author to *notice* the panel opened on the wrong
thing — an entry tab full of status and slug looks like a working panel, so the
mistake is invisible until they wonder where the options went.

**That tab is decided on every open, not remembered.** Sticky sounds harmless
until the drawer opens on "Nothing Selected" because the author clicked away
from the element they last had. Nothing selected means the entry tab, which is
the only one with something to show.

**The Outline is a drilldown inside the entry tab.** Borrowing the element tab
to show it made the drawer look like it had navigated somewhere else.

**One level in a Content group, never the subtree.** The whole tree is what the
Outline is for; repeating it inside a panel would make every callout a second
outline.

**Inline directives are not in the outline.** They live inside a sentence, so
they are not something the document is *made of*; listing them would bury the
structure under every badge and footnote in the prose.

**The highlight layer is rendered once per line, not once per keystroke.** It
is rebuilt on every character typed, and on a 5,000-word post that cost 73ms of
a 79ms keypress — four dropped frames per character, and a caret visibly behind
the typing. Nothing was wrong with the markup it produced; there were a thousand
lines of it and one had changed.

Each line's HTML is now cached under a key carrying everything its rendering
depends on: the text, whether a fence is open above it, the two things a line
cannot tell about itself (definition term, table header), and whether the
selected element begins or ends on it. Anything outside that key cannot change
the output — a directive's head is parsed from its own line, and a container's
body renders the same whether or not the container is selected, which is why
only the opener and the closer carry the selection tag. Lines that survive a
pass become the next cache, so a deleted line stops being paid for.

Two more came out of measuring rather than reading. The layer was being rebuilt
**twice** per keystroke, because the input handler called it and then called
`setActiveFromCursor`, which ends with another. And the textarea's height was
read back from the layer inline, right after writing the new HTML, which forces
the browser to lay out the whole 45,000-pixel document before the keystroke can
finish — 35ms of the remaining 51ms, more than the highlighting it was
measuring. A `ResizeObserver` asks the same question at a moment when the answer
already exists, carries the measured box with it, and fires only when the height
actually changed, which for most keystrokes is never.

| document | before | after |
| --- | --- | --- |
| 1,300 chars | 5.6ms | 0.9ms |
| 11,000 chars | 26.2ms | 3.9ms |
| 32,000 chars (~5,000 words) | 83.1ms | 10.1ms |

The eight-case alignment probe still passes with zero mismatches, which is the
only thing that makes a change to this layer safe to ship.

## The toolbar

**One thing is open at a time.** Opening any of the toolbar's panels, menus or
modals closes whatever else was open — the inserter panel included, though it
otherwise stays put while you browse. Each of them used to dismiss only itself,
so reaching for a second left the first standing behind it: a menu over a modal,
a panel behind both, and two presses of Escape to get back to the text. They are
alternative answers to one question — *what am I putting in, or changing?* — and
one question does not have two answers open at once. Mechanically it is one
`closeOverlays(keep)` the openers call, which also means a new overlay is added
to one list rather than to eight dismissal handlers.

**A toolbar control does not toast.** Changing a setting there shows its result
in the source, in the control's own glyph, and in the panel beside both. A
fourth report of the same thing floating over the corner of the screen is noise.
A toast is for something that happened where you were not looking — a save, a
deletion, a thing that moved on a screen you are not on.

**Bold, italic and link are in the toolbar, and they are typing, not styling.**
They write `**`, `*` and `[](…)` into the source — the same characters a writer
would type — and read their own state back out of it. The editor shows Markdown,
so a formatting button that pretended to hold state the source does not have
would be the first place the surface lied about what it is.

They are offered where Markdown emphasis *is* emphasis. In a fenced code block,
on a directive's `:::` line, on a thematic break and on a table's rule, a pair of
asterisks is two asterisks, so the group goes away rather than offering to write
something inert. The inline-component button goes with them: it answers the same
question — what goes inside this sentence — and is just as meaningless in code.

That grouping is also what keeps the toolbar still. A group that collapses has to
collapse at the *end* of the fixed tools; if it sat in the middle, every trip
into a code block would slide the buttons on its right across the row and back.
So the left half is now three parts in the order a writer thinks in: the
document, the sentence, the width.

**Bold writes `**`, italic writes `_`.** Both marks are Markdown and the
highlighter reads both, so the buttons light for either. What they write is the
pair that stays legible in the source: `**bold**` beside `_italic_` tells you
which is which at a glance, where `**` beside `*` asks you to count. Inside a
word, where `_` is not emphasis at all, the asterisk is written instead — not a
softening of the rule but the only way to keep the button from writing something
inert.

**Link is a form, not a toggle.** Bold and italic have a meaningful off; link
does not — what it needs is an address, and an address has to be typed. So it is
the one control in that row with a caret, and it opens a small form with Text and
Address. The earlier version wrote `[text](url)` into the sentence with the
placeholder selected, which is two placeholders and a hunt; the form asks for the
one thing it does not already know, fills the rest in from the selection, and
offers **Remove** when it is opened on a link that already exists.

**Every glyph in the editor toolbar is 14 to 18 units tall on the 24-unit icon
grid, and most are 16.** Lucide sizes each icon by eye, so across the set ink
heights run from 11 to 20. That is right in a list, where icons are read one at a
time next to their labels, and wrong in a toolbar, where ten sit in a row and the
tallest reads as a mistake — `i-link` at 19.9 was a head above everything beside
it, and hand-drawn letterforms at 12 looked like they had shrunk in the wash.

The fix is to the glyphs, not to the CSS: `i-link` is scaled from 19.9 to 16 and
`i-shapes` from 18.4 to 18, both by rewriting the path data about (12,12) rather
than wrapping a transform, so the stroke still comes from whichever rule is
setting it (`.chk .ic` uses 2.6, `.ins-tile .ic` uses 1.5). Bold and italic are
Lucide's own, untouched, which is 16 — the same as the A beside them.

This is a rule about a row, not about the icon set. The rail still runs 18 to 20
because three icons stacked with labels under them are not a row of ten.

**The group is shown only while the caret is in the source.** The entry title is
text but it is not Markdown, and neither is any other field in the editor. A
bold button hovering over the title would write two asterisks into a heading. The
toolbar's own controls stay the exception — pressing one is not leaving the
source — which is the same rule that keeps the breadcrumb from forgetting what
was selected.

**⌘K stays the command palette, everywhere.** Link is ⌘⇧K. Every editor binds ⌘K
to a link and the pull to follow them was real, but it would have meant one
shortcut with two meanings depending on which box has focus — the same objection
that keeps the rail from navigating. A rule that holds everywhere is worth more
than a convention that holds inside one textarea.

**Every write to the source goes through one function, and it writes the way a
keystroke does.** Assigning `textarea.value` replaces the text without recording
the change, so the browser's undo stack steps straight over it: pressing Bold and
then Ctrl-Z used to delete the previous sentence and leave the document in a
state the writer had never seen. Ten places in the editor were doing that. They
now go through `putSrc`, which reduces the change to the smallest span that
actually differs and applies it with `execCommand("insertText")` — the same path
a keypress takes, so undo reverses exactly the edit and redo puts it back. The
API is deprecated with no replacement that works in a textarea; every editor on
the web is in the same position, and a working undo is not optional in a writing
tool.

It borrows focus and gives it back, because the element panel writes to the
source while the author is in one of its fields, and a control that steals focus
when you change a dropdown is worse than one that cannot be undone.

**Paste is how a document starts, not an edge case.** Most of what reaches a CMS
was written somewhere else. Three kinds arrive: a *file* goes to the library and
comes back as a reference, so the source never holds bytes; a *URL dropped on a
selection* becomes a link around it, which is the one paste where the intent is
unambiguous — you selected words, then handed over an address; and *rich HTML*
becomes Markdown. A heading pasted from a browser used to arrive as a line of
plain text indistinguishable from the paragraph under it, and the writer rebuilt
the structure by hand on every long document. The converter walks the DOM rather
than matching the markup, because what Docs, Word, Notion and a browser emit has
nothing in common except the shape of the tree. Anything it does not recognize
falls through to the browser's own plain-text paste, so the worst case is exactly
the old behavior.

**Markup pasted as text converts too, but only the parts Markdown has a word
for.** Content copied out of a code editor, a template or an export carries no
`text/html` flavor, so the rich-paste path never sees it — and it is still
almost always someone moving content in. Two things make converting it the
right default rather than a liberty: undo now reverses a paste in one press, so
the wrong guess costs nothing; and the alternative, a wall of angle brackets in
a Markdown document, is not what anyone wanted either.

The limit is what keeps it honest. Markdown allows raw HTML and some of what
arrives is meant to stay that way, so this converts only **structure Markdown
can say** — headings, paragraphs, lists, tables, quotes, code — and only when
nothing that *means something as itself* is in it: a script, a style, an
iframe, an SVG, a form, a media element. An embed stays an embed. A fragment
with an opening tag and no closing one is a sentence about HTML rather than
HTML, and is left alone as well.

Where both flavors are on the clipboard the rich one wins, because it is what
the author was actually looking at.

**Tab moves a block; it does not type two spaces into one.** Tab used to insert
two spaces wherever the caret was, and Shift-Tab inserted two more — so the key
people reach for to nest a list item made the item wider, and the key they reach
for to undo that made it wider again. In a list or a quote, and over any
selection spanning lines, both keys now move the lines themselves. Everywhere
else Tab is still two spaces at the caret, which is what it is for inside a fence
or a table.

**Backspace takes the marker off.** With the caret just after a block's marker it
used to eat the space and leave `-item`, which is neither a list nor a paragraph.
It now removes the marker and leaves the words — and on an indented item it lifts
one level first, so Backspace is the mirror of Tab. The same for a quote's `>`
and a heading's `#`. A second press joins the line to the one above, which is
what Backspace does everywhere.

**A media option says which kind of file it takes, and the library only offers
that.** A video's File field opens on videos, its Poster image on images, an
audio element's File on audio, an image on images, the entry's featured image
on images. The option carries the kind, so the rule is declared where the
option is defined rather than at each of the places that open a picker.

A locked picker drops its kind filter — there is nothing to filter — and names
what it is for in its own title. It also **refuses an upload of the wrong
kind** rather than accepting it and hiding it: a file that vanished after being
uploaded would look like a bug, and the file chooser carries the matching
`accept` so the mistake is hard to make in the first place. Both outcomes are
reported in one line, because a refusal said on its own is replaced a frame
later by the one about what did go in.

The fields that take *any* file stay open: a download card, a CSV behind a data
table, the data behind a chart. The rule is about elements that play their file,
not about every field that names one.

This needed **audio to become a kind the library knows** — it was images, video
and documents, and an `.mp3` landed in documents, which made "only audio" an
empty shelf. One function decides what a file name claims to be, so the upload
tab, the library filter and the per-option lock cannot disagree about an `.m4a`.

**You cannot write into the machinery.** Parts of a line are syntax rather
than writing — a directive's opening tag, a container's closer, an attribute
block hung off the end — and text that lands in one of them does not join it,
it stops it being one. Silently: an inserter fired with the caret halfway
through `:::callout{type=note}` left `:::cal` and `lout{type=note}` with a
component wedged between them, and the author found out when the breadcrumb
lost the element it had been naming.

Nothing is blocked and nothing is refused. The insertion point moves to the
nearest place the same text means what it looks like, and the text goes in.
That is the whole rule, and it is better than a warning: a writer who is told
*no* has to work out where *yes* is, and the editor already knows.

**An inserter and a keystroke are not the same act**, so there are two rules.
A *snippet* anywhere inside one of these constructs breaks it, so its position
is pushed out of the whole line — out of an opening tag into the body, out of a
closer to below the container. A *character* typed in the middle of one is
someone editing it by hand, renaming a directive or fixing a class, and that
has to keep working; so a keystroke only moves where a single character
provably breaks something and no edit could be meant — past the closing brace,
at the very end of the line.

That gives the behavior the attribute block needed: an attribute block has to
be the last thing on its line, so **text typed after one goes before it**, at
the end of the words it describes. `A paragraph. {.bleed-wide}` with the caret
at the end takes the next character as `A paragraph.X {.bleed-wide}`, while the
caret between the braces still edits the class. At the end of a directive's
opening tag the character lands on the next line instead — the body, for a
container — which is the move Enter already makes from a fence.

A paste is a snippet that brings no newline of its own, so it gets the same
treatment one line further on. The caret is moved **before** the conversion is
attempted, so the fallback is protected too: when the paste is not converted
and the browser inserts the raw text, it still lands somewhere safe.

An attribute block alone on its line is left out of all of this. It is the one
placement Markdown puts *before* the thing it describes, and what a writer
means by typing after it is not something to guess at.

## The inserter and the Markdown elements

**The Markdown elements are in the component inserter, interleaved.** Heading,
Quote, List, Definitions, Code Block, Table and Divider are tiles in the same
panel as the components, inside the same category groups rather than a
"Markdown" group of their own. Someone reaching for a table is not asking
whether a table is a component; a group heading that separated them would teach
exactly the distinction the rest of the editor works to stop mattering. Within
a group the Markdown elements come first, because they are reached for most and
the components build on top of them.

**No paragraph tile, and no inline emphasis.** A paragraph is what you get by
typing, and a tile that inserts an empty line is furniture. Bold and italic wrap
a word already written, which is a different act from placing a block — the same
reason inline components have their own dropdown.

**An inserted placeholder arrives selected.** Choosing *Heading* writes
`## Heading` with the word selected, so the first keystroke replaces it. A caret
beside a placeholder asks the author to delete something the editor wrote.

**An inserter is not finished at the insertion.** What lands has to look like
the thing it is. Adding Code Block to the panel is what exposed that three
backticks on their own are the least legible construct Markdown has — the
fence's box and the third-backtick completion both came out of that, not out of
a separate pass on the highlighting.

**Where Markdown and a component collide, Markdown keeps the name.** Two tiles
reading "Table" in one group is a worse problem than a renamed component, and
the project's own test already says which one is the intruder: if Markdown says
it, Markdown says it. So the `:::table` component became **Data Table** — it is
named for what it adds that Markdown cannot, a data file behind the rows — and
the `::divider` component was **dropped**, because `---` already is a divider
and its one option was a style, which is what a class on the rule is for. No
seeded content used it. *Reversible:* if the theme needs a divider that Markdown
cannot express, it comes back with a name that says what that is.

**A component may say what it holds, and the gallery is the first that does.**
`only` is a list of what may be nested, written in the same keys the inserter
uses. The gallery's is `["image"]`.

It moved from a leaf pointed at a folder to a container holding images, because
a gallery *is* a set of images arranged together: the arrangement is the
component and the images are the content. Naming a folder in an attribute made
the set something chosen elsewhere and left the entry unable to say which
pictures it contains — the same objection that keeps a component from being a
second syntax for something Markdown already writes.

Everything that offers to put something into the document reads `only`, so the
constraint is declared once and enforced wherever it could be broken. The
inserter shows one tile and says why. The sentence tools go away, because
nothing on an `only` list is text. The insert snippet arrives **empty** rather
than seeding "Your text here." into a container that refuses text — the
component would be breaking its own rule on the way in. And because the author
can always type anyway, the element panel counts what is actually in there and
names any line that is not an image.

Hidden rather than disabled, as everywhere else here: a grid of tiles that
refuse to do anything is a worse answer than a short grid of tiles that work.

## Bleed

**Base is the absence of a class, not a class called base.** There are three
steps and only two of them are written: `bleed-wide` and `bleed-full`. Base is
the width the element already has, so recording it in the source would be the
same mistake as `variant=default` — and gets the same answer. An entry nobody
has widened carries nothing saying so, which is what lets a theme change what
the measure is and have that reach every entry ever written.

**The prefix is `bleed-`, not `stretch-`.** What these classes do is bleed out
past the measure; *stretch* describes the element's own geometry, which is not
what happens.

**Bleed owns width; a variant owns style.** The image and figure variant lists
carried `stretch-wide` and `stretch-full` and no longer do. A variant list that
also set width would be a second way to write the same class, and the two would
disagree the first time one of them gained an entry. Image keeps Default, Float
left, Float right; Figure keeps Default, Bordered.

**One button that opens a menu — not a split button.** *This reverses the split
control the first version shipped with.* That one put two hit areas 22px apart
on a single decision, and the half that toggled could only ever reach two of its
three answers, so the same press meant different things depending on which
14 pixels it landed in. Every other control in that toolbar is one button with
one outcome; the media and inline inserters are exactly this shape already — an
icon, a caret, a menu. The menu is the control and the button is the way into
it.

**One glyph per width, and no word beside it.** The button shows the width in
force and nothing else: two rules for the text column with a bar between them
that matches it at Base, overruns it at Wide, and loses its ends at Full. Every
other control in that row is a bare icon in a fixed position, which is the
condition under which an icon is a label; a name on this one would be the only
word in the row, saying what the glyph already says. The names belong in the
menu, where they are being chosen between, and in `aria-label`, which reads
*Bleed: Wide*.

**The button takes the accent while the element is bleeding.** The glyph says
which width; the tint says *this one has been widened* — the thing worth
noticing without looking for it.

**Base is a real entry in the menu.** Going back to the column is a choice like
the other two, and it is the only way back now that the button no longer
toggles. There is no separate "None", because Base is none.

**Top level only — provisionally, and on purpose.** The control is shown for a
leaf or container element at the top of the document and hidden everywhere else:
an element inside a container, a list or a figure is bounded by its parent
whatever class it carries, so offering the control there offers something that
does nothing. A bleeding container could in principle pass the room down one
level, and a theme may want that; when it does, the visibility predicate is the
single place to widen. Until then the simple rule holds, because a rule an
author can state in one sentence beats one they have to discover.

**Hidden, not disabled.** This control appears and disappears as the caret
moves. A toolbar of greyed-out buttons following the cursor around the document
is noise, and it is the only contextual control in a header whose whole value is
that everything in it is always there.

**The class is swapped on the raw attribute block.** `parseAttrs` reads
`{.bleed-wide}` as a key named `bleed-wide` and writes it back as
`bleed-wide=true`, which would quietly destroy every class on the element.
Reading and writing a class is string work on the `{...}` itself; bleed and the
image variant share one helper for it.

**A divider carries its attributes at the end of its own line.** `--- {.bleed-full}`
keeps the placement every other block uses rather than inventing a second
above-the-line exception for the one element with nothing else on it. The block
scan had to be loosened to allow it — without that, `--- {.bleed-full}` stops
matching the rule pattern and silently becomes a paragraph. *Provisional:* the
parser has not been checked against this; if it refuses, the fallback is the
list's placement and the scan and the writer change together.

## Controls

**The admin draws its own select.** The native `<select>`'s closed state can be
styled; its open list cannot — that popup is drawn by the operating system, in
the system font, ignoring the admin theme entirely. The real `<select>` stays in
the DOM holding the value and firing `change`; a button draws the label. The
select is the source of truth, always.

**A menu shows what is in force by filling the row, not by ticking it.** The
chosen row takes the accent-soft background and accent ink that a hovered row
takes — the same signal, in the same place, for the same reason. A tick on the
end is a second thing to look for in a list of three, and it says what the fill
already says. This holds for every menu carrying a choice: status, visibility,
bleed. The custom select keeps its tick, because its list can run to forty rows
and a fill that scrolls past is not a readout.

**A hierarchical taxonomy is one box, not three controls.** Search, tree and
*New Topic* share one bordered container. Three boxes with gaps between them
read as three unrelated things that happen to be stacked.

**A parent dropdown shows the tree.** A flat alphabetical list of candidate
parents is technically complete and practically useless — it hides the shape you
are adding to, which is the only thing you opened the list to learn.

**An entry always has at least one author.** Enforced in the data path, not only
in the markup, with one line of copy saying why — a control that silently stops
working reads as broken.

**Time is two fields and a switch, on a 12-hour clock.** The native
`<input type="time">` brings its own AM/PM widget, its own clock glyph and its
own idea of what a control looks like, none of which match anything else here.

**Boxes are spent only on things you type into.** Ten bordered inputs stacked
down a sidebar make ten identical targets out of ten different decisions; a
label → value row list makes the *answers* the thing you scan.

**A row of controls has one vocabulary.** In the editor's toolbar every control
is one button: an icon, in a fixed position, with a caret after it if it opens a
menu. Two targets inside one control, or a word beside one icon in a row of
bare ones, is a second vocabulary in a row that only works because the first one
is learnable at a glance.

**One glyph, one idea — and adjacency is what tests it.** Quote and Pull Quote
both used the curly quote marks, List and Table of Contents both used the list
glyph, Code Block and Code Sample both used braces, Table and Data Table both
used the table glyph. None of it showed until the Markdown elements landed
beside the components in the same inserter group. The Markdown element keeps the
plain glyph and the component takes one that says what it adds: a blockquote is
an indented bar of text and the curly marks belong to the pull quote that sets
them; a table of contents is a tree; a code sample is a file; a data table is a
file too.

## Lists and screens

**The table is the product.** Most admin time is spent scanning a list.

**Core columns only, the same for every type.** Custom fields never become
columns.

**The index page is an entry, pinned.** A type's archive is written like any
other content rather than in a settings field, and it is pinned at the top of
that type's list rather than buried in it.

**List, then detail — everywhere.** No master/detail split panel where a list
screen and a detail screen would do.

**Leaving items out beats showing them disabled.** An index page and a taxonomy
term lose Duplicate and Move to Trash rather than showing them greyed; a menu of
dead entries reads as a bug.

## Menus and actions

**The editor's menu sits to the right of the primary button** — the last thing
in the toolbar, where a menu of everything else belongs, rather than wedged
between the status and the action. Its trigger is a **vertical** ellipsis: a
horizontal one reads as *more of this row*, a vertical one as *a list opens
below*.

**No inserters in that menu.** Putting something into the entry is the toolbar's
job and one keystroke away; a menu that repeats the toolbar is a second place to
look for the same thing, and the two drift apart the first time one gains an
item.

**Sections are named, not just divided.** A rule between two groups says they
differ; a heading says how.

**A destructive action never sits flush against an ordinary one.**

## Copy

**Title Case names things; sentence case says things.** Headings, tab labels,
panel group titles, screen names and menu items are named. Buttons, field
labels, hints, toasts and body copy say something.

**Toasts state what happened, in the past tense** — and only where the result is
not already on screen. See *A toolbar control does not toast*.

**Empty states name what is missing, say why in one sentence, and offer the
action that resolves it.** Never a bare "No data".
