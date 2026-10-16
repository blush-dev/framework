# Inside a sentence

These built-in [blocks](README.md) mark up words in running text:

```markdown
A :abbr[CMS]{title="content management system"} saves with :kbd[Ctrl+S].
The launch is :time[next Tuesday]{datetime=2026-10-06}. Comments :badge[Beta]{variant=info}
```

| Block | Example                                         | Props                                                                                                                                                                                  |
|-----------|-------------------------------------------------|----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `abbr`    | `:abbr[CMS]{title="content management system"}` | `title`: what it stands for.                                                                                                                                                           |
| `badge`   | `:badge[New]{variant=tip}`                      | A short label set off from the text. Variants: `info`, `tip`, `warning`, and `danger`; without one it's neutral.                                                                      |
| `cite`    | `:cite[The Hobbit]`                             | The title of a work: a book, film, article, and so on.                                                                                                                                 |
| `dfn`     | `:dfn[Blush] is a flat-file CMS.`               | The term the sentence defines. `title`: the term, when the text says it differently.                                                                                                  |
| `ins`     | `~~$20~~ :ins[free]{datetime=2026-10-06}`       | Text added later, the pair to `~~deleted~~` text. `datetime`: a date, or a date and time, when it was added. `cite`: a link that explains why.                                        |
| `kbd`     | `:kbd[Ctrl+S]`                                  | Keys joined with `+` are a combination, and each key is marked up on its own.                                                                                                          |
| `samp`    | `:samp[File not found.]`                        | What a program prints.                                                                                                                                                                 |
| `small`   | `:small[Prices include tax.]`                   | A side comment or fine print.                                                                                                                                                          |
| `time`    | `:time[next Tuesday]{datetime=2026-10-06}`      | `datetime`: a year (`2026`), month (`2026-10`), date, date and time (`2026-10-06T14:30`, with an optional time zone such as `Z` or `-05:00`), time (`14:30`), or duration (`PT2H30M`). |
| `var`     | `:var[x]`                                       | A variable in math or code.                                                                                                                                                            |

[Buttons](buttons.md) and [icons](icons.md) go in a sentence too.

## Dates and times

`time` gives software (search engines, calendars) the exact date while
readers see your words. With an empty label,
`:time[]{datetime=2026-10-06}` shows the date in the page's language
(on a [translation](../translations.md), the translation's) and
your site's time zone and date format, such as "October 6, 2026". A time uses the
site's time format, and a date with a time uses both; a year or a month
shows just those ("October 2026").

## What Markdown already does

For the rest, Markdown already has a way: `==text==` highlights
(`<mark>`), `~~text~~` strikes out (`<del>`), and `[text]{.class}` wraps
text in a `<span>` with a class or id (see
[Markdown](../markdown.md)).
