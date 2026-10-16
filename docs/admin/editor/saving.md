# When a save doesn't go through

When the editor can't save, your changes aren't lost:

- **Unsaved changes stay in your browser** as you type, and as you
  leave the editor or the page. If you go elsewhere in the admin, the
  tab closes, you reload, or the browser crashes, open the entry again and choose **Restore Them** (or **Throw
  Them Away**). They're only in that browser until you save.
- **Offline**, a bar under the top bar says so, and you can keep
  writing. A save you ask for shows **Waiting…** on its button and goes
  ahead once you're back online.
- **A save that fails** (a server error, say) shows **Not saved** and a
  **Try Again** button.
- **A save that adds HTML your role can't add** is refused, naming
  what (`<iframe>`, say). The editor marks such HTML, and links to
  `javascript:` addresses, with a wavy red underline as you type, along
  with any already there that someone else wrote; only what you add
  counts, so you can still edit their words, or take it out. See
  [Capabilities](../../concepts/accounts/README.md).
- **If the entry changed after you opened it** (someone else saved it,
  or its file was edited or pulled from git), the editor stops saving
  and says when it changed. **Compare** shows the fields and body lines
  that differ; **Keep Theirs** throws your changes away for their
  version; **Keep Mine** saves your version of everything the editor
  shows over theirs.

A publish can also be refused when a required field is empty (see
[Publishing buttons](README.md#publishing-buttons)) or when a
relationship is full on the other side (see
[Choosing relations](relation-pickers.md#when-the-other-side-is-full)).
