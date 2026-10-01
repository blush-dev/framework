# Meridian admin — project brief

**Read this file first in every conversation.** It is short on purpose: what the
project is, where everything lives, which rules hold everywhere, and which of
the other files you need for what you are about to do.

The admin interface for a PHP CMS — a **Vue SPA on a PHP JSON API** — designed
as a clickable HTML prototype plus the design-direction files beside it. Nothing
here is a running system: the prototype has invented data and no backend, and
the documents describe intent.

---

## Where everything lives

The prototype is a single HTML file, about 450 KB, vanilla JS, no build step. It
is published as an artifact, and **that artifact is its only source of truth** —
there is deliberately no copy in project knowledge, because a stale 450 KB file
that looks authoritative is worse than no file.

| What | Where |
|---|---|
| The prototype | https://claude.ai/artifact/S1PBodE7fni3JSBcQnARye — read it back with the Artifact tool, publish to the same URL |
| Standalone build | `meridian-admin.html` — the same prototype wrapped as a complete document you can open from disk |
| Design direction | The numbered `.md` docs in this project under `design/` |
| Tokens | `design/tokens.css` — the whole surface an admin theme may redefine |

**These documents are project docs, not attachments.** Read one with
`project_read`, change it, and write the whole updated file back to the same
path with `project_write` — there is no in-place patch. That is the only way
they change: nobody re-uploads anything by hand, and a document that is out of
date is a document somebody forgot to write back in the same turn as the change
it describes.

A conversation that needs the prototype reads it back from the artifact first.
`70-build-runbook.md` has that procedure and is the first thing to open if you
are changing the prototype rather than the documents.

## The documents

Section numbers (§1 … §13) are the original single document's and were kept
through the split, so a cross-reference still finds its target wherever it now
lives.

All paths below are under `design/`.

| File | Covers | Open it when |
|---|---|---|
| `00-project-brief.md` | This. Orientation, constraints, who-owns-what | Always |
| `10-foundations.md` | Vocabulary, principles, theming, type, color, layout, spacing | Always — these are the rules that break things |
| `20-components.md` | Shared controls: buttons, tables, selects, menus, empty states | Touching anything used on more than one screen |
| `30-editor.md` | The entry editor, end to end. The biggest and most active part | Working on the editor |
| `40-screens.md` | Lists, hierarchy, index pages, taxonomies, media, and the loading / failure / permission states | Working on anything that is not the editor |
| `90-conventions.md` | Motion, copy, Vue conventions, and the "do not" list | Before finishing anything |
| `50-open-questions.md` | What is still undecided, and requests between areas | Looking for work, or about to decide one |
| `60-decisions-log.md` | Choices already made, and why | Before re-opening something that looks arbitrary |
| `70-build-runbook.md` | How to get, change, verify and republish the prototype | Changing the prototype |
| `tokens.css` | The token definitions | Any visual change |

---

## Standing constraints

These are not design opinions, they are project facts. They hold in every
conversation — in copy, in code identifiers, in CSS token names and in comments.

- **US English, never UK.** color, gray, behavior, organized, catalog, center.
  This one gets violated by accident constantly; check before finishing.
- **Never document anything about WordPress.** Not as a comparison, not as a
  reference point, not in a comment.
- **Both admin themes ship.** Neutral is the default, Editorial is the second.
  The admin theme *and* the light/dark setting are per-account user settings,
  living on Your Profile — not on an Appearance screen, which is about the site.
- **Title Case names things; sentence case says things.** Headings, tab labels,
  panel group titles, screen names and menu items are named. Buttons, field
  labels, hints, toasts and body copy say something.
## What is firm and what is provisional

Nothing here was validated against a running system. Some of it is mechanical —
break it and the interface visibly fails. Some is taste, formed early and on
thin evidence.

**Firm.** These hold until someone deliberately re-architects them:

- The theming contract in §3. Getting the cascade wrong produces an admin that
  is unreadable for anyone on the default system setting.
- Token discipline: no literal colors, fonts or radii in component CSS.
- Status is never conveyed by color alone (§5).
- Real `<button>` and `<a>` elements, and the ARIA attributes in §11.
- The token *names*. Changing values is theming; changing names breaks every
  third-party admin theme.
  **Provisional.** Argue with these freely: the vocabulary table (§1), which
  reflects conversation rather than the API's field names; the specific components
  in §7, which came from a prototype with invented data; sidebar grouping and
  hide-vs-disable for permissions in §8, both interim; and the directory layout in
  §11, which should match whatever the repo already does.

Where the real shape of the project disagrees with these documents, **the
project wins**. Depart from them where they do not fit, say why, and update the
document in the same change — a rule that keeps needing exceptions is a wrong
rule.

---

## Working across several conversations

The design is now big enough that one conversation cannot hold it. That works
only if conversations do not quietly overwrite each other. **A conversation
claims an area, and an area owns its files.**

| Area | Owns | May read |
|---|---|---|
| Editor | `30-editor.md` | everything |
| Screens | `40-screens.md` | everything |
| Components & foundations | `10-foundations.md`, `20-components.md`, `tokens.css` | everything |
| Anything | `50-open-questions.md`, `60-decisions-log.md` — append only | everything |

- **Say which area you are in at the start.** "This conversation is the editor"
  is enough, and it tells the assistant which file it may rewrite.
- **Edit only your area's files.** If work in one area forces a change in
  another's file — an editor change needing a new shared control, say — do not
  make it. Write it in `50-open-questions.md` under *Requests*, name the area it
  belongs to, and let that area make it. One file, one owner.
- **`tokens.css` belongs to foundations, always.** A new token affects every
  screen and every future admin theme, so it is never added in passing.
- **The prototype is shared and everyone edits it.** It is one file and cannot
  be split, so the only protection is sequence: republish before you stop,
  re-read the artifact before you start.
- **Append to the decisions log rather than arguing twice.** If you settle
  something a future conversation could reasonably re-open, record it with the
  reason. That file exists to stop the same argument happening in three
  conversations.
## The routine that closes a turn

Whatever the area, a change is not finished until all of these are true.
`70-build-runbook.md` has the commands.

1. The prototype's JS parses — `node --check` on the extracted script.
2. The change is verified in a browser, not by reading the diff: a DOM probe or
   a screenshot, and no console errors.
3. The standalone build is rebuilt from the prototype.
4. The design document for the area is written back with `project_write` in the
   same turn — not noted for later.
5. `tokens.css` still matches the prototype's six theme blocks, if any value
   moved.
6. The artifact is republished to the same URL, and the files are delivered.
