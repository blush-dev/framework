# Responses: a design brief for the admin

> **Status:** planned, nothing built (D-907). Only the admin screens
> are covered here. The front end, the type and site settings, and
> spam rules come later.

For the design team. Visitors will be able to respond to entries, and
the admin needs a place to read those responses and moderate them.
This brief says what each screen needs, not how it should look. Build
it from the existing pieces (the entry lists' status tabs and rows,
panels, the editor's Document tab rows, toasts) wherever they fit.

Words, as the admin uses them: a **response** is anything a visitor
sends about an entry. Its **kind** says which sort it is. **Comment**
is the first kind. Others may follow (webmentions, reviews; not yet
decided), so a row's design should leave room for a kind tag and a
small extra for that kind, such as a rating. **Moderating** is
deciding what's shown. Buttons are Title Case.

A response has an author (a guest's name, with an email and website
they may give, or a signed-in account shown as its profile), a body
(short formatted text: paragraphs, links, emphasis), the entry it's
on, when it came in, and a status. A response can answer another
response on the same entry.

**Statuses:** **Pending** (waiting for a moderator), **Approved**
(shown on the site), **Spam**, and **Trash**. Spam and Trash are left
out of everything else, as Trash is for entries.

---

## 1. Responses (new screen)

**Where:** Content in the rail, as one item after the content types.
Only accounts that may moderate some type see it. Each account sees
the types it moderates.

**What it's for:** "what's waiting for me, and what has been said?"
Most visits are to clear the Pending tab, so that path has to be quick:
read, then approve or reject, one at a time or many at once.

**Tabs:** **All** (Pending and Approved), **Pending**, **Approved**,
**Spam**, **Trash**, each with its count, as the entry lists' tabs
have.

**What each row shows, newest first:**

- **Who:** an avatar and name. A signed-in account's name links to its
  profile and is marked as an account, so it's easy to tell from a
  guest. A guest's website, if they gave one, is shown quietly.
- **What they said:** the opening of the body, a few lines at most.
- **Reply to:** when it answers another response, a "Reply to *Name*"
  link that opens that response. Lists are always flat; threads show
  only through these links.
- **On:** the entry's title, linking to its editor, with its type.
- **When:** relative ("3 minutes ago"), with the exact time on hover.
- **Kind:** a quiet tag. With only comments at first, it may be hidden
  until a site has a second kind.
- **Actions:** on hover or focus, the ones that fit the row's status:
  **Approve** or **Unapprove**, **Reply**, **Spam** or **Not Spam**,
  **Trash** or **Restore**. Each takes effect at once, with Undo in a
  toast. **Delete Permanently** is only in Spam and Trash.
- **Reply** opens a small box under the row, to answer as yourself.
  Your reply is approved at once. Replying to a pending response
  approves it too, and the box says so.

**Choosing many:** checkboxes, with Approve, Spam, and Trash for the
chosen rows (Restore and Delete Permanently on Spam and Trash), as
bulk actions work on the entry lists. **Empty Spam** and **Empty
Trash** on those tabs.

**Search and filters:** search the body and the author's name, email,
and website. Filter by type, by entry, and by kind. The editor's
Responses row (below) opens this screen with its entry filter already
set, which shows as a removable chip.

**States:**

- **Nothing pending:** an empty state that says so. It's good news,
  not an error.
- **No responses yet** (no type takes them, or none has come in): an
  empty state. When no type takes responses, it says where they're
  turned on (the type's screen, when that's built).
- **Paging:** a page of rows and more on request, as other long lists
  do.

## 2. A response's screen (new screen)

**Where:** `/admin/responses/{id}`, opened from a row, a Reply to link,
or a link in a notification email (later).

**What it's for:** one response, in full, with everything a moderator
needs to judge it.

**What it shows:**

- **The body,** in full, editable by a moderator (fixing a typo or
  removing a link), saved with **Update**.
- **Reply to:** the response it answers, as a short quote that links to
  it, when it has one.
- **Replies:** the responses that answer it, as rows that link to each
  one.
- **Status:** the current one and the actions from that status, as on
  the list.
- **Author:** name, email, and website (a guest's), or the account
  (linked) for a signed-in one. Also the IP address while the site
  keeps it (later: kept for a set time, then cleared).
- **On:** the entry, linked to its editor and to its page on the site.
- **Submitted:** the date and time.
- **Reply,** as on the list, under the replies.

**States:**

- **Edited:** a quiet line saying who edited it and when, so a
  moderator's change isn't taken for the author's words.
- **In Trash or Spam:** the actions to restore it or to delete it
  permanently, and no Reply.
- **No longer there:** the not-found screen, as for an entry.

---

## Parts added to existing screens

- **The editor's Document tab, a Responses row** pinned to the end with
  Archive Page, Linked From, and Outline (D-608's pattern), with its
  count and how many are pending ("12 · 2 pending"). It opens a level
  down to the entry's latest responses, each with the list's actions,
  and an **All Responses** link to the Responses screen filtered to
  this entry. Only on types that take responses.
- **The Dashboard:** pending responses in "what needs you", linking to
  the Pending tab.
- **Roles:** a Responses section with a Moderate capability for each
  type, and one for every type, as a role's content sections are drawn.

## Open for the designers

- Whether the rail item shows a pending count. The section panel
  replaced its counts with chevrons (D-800), so a count here would be
  the only one.
- Whether the list's default tab is Pending when something is waiting,
  or always All.
- How a row reads at a glance when the body is long, or is only a link.
