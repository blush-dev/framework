# Your first page

Everything visitors read lives in `user/content/`. Each file is an
**entry**: a page, a post, a tag, and so on. You write entries in Markdown
with a small block of settings at the top, called
[front matter](../concepts/front-matter.md).

Create `user/content/contact.md`:

```markdown
---
title: "Contact me"
---

Send me an email at **hello@example.com**.
```

Visit `/contact` and it's there. In development, Blush picks up new and
changed files on the next request. (In production you publish changes
instead; see [Publishing](../running/publishing.md).)

## Next steps

- [How files become URLs](../concepts/content-files.md).
- [Front matter](../concepts/front-matter.md): titles, dates, and the
  rest of an entry's settings.
- [Drafts, scheduling, and hiding](../concepts/status-and-visibility.md).
- [Content types](../concepts/content-types.md): blogs, categories, and
  types of your own.
