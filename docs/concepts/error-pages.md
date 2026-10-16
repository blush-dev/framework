# Error pages

Error pages are entries you write for "not found" and other errors, in
place of the theme's generic message.

Create `user/content/_errors/404.md` to write your own "not found" page,
with a page for the `_errors` folder itself (`user/content/_errors/index.md`,
any title; `content:parents --write` writes it):

```markdown
---
title: "Page not found"
---

Sorry, there's nothing here. Try the [homepage](/).
```

The same works for other errors, such as `_errors/500.md`. Without one,
the theme shows a generic message.

Error pages never [expire](status-and-visibility.md#expiry), and `_errors`
is one of the `_` names Blush keeps (see
[Content files](content-files.md#names-blush-keeps)).
