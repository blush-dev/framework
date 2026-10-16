# Table of contents

`::toc` lists the page's headings, nested by level, each linking to its
heading. Put it wherever the list should appear:

```markdown
::toc[On this page]

## Installing

### Requirements

## Setting up
```

The label is shown as its title. It lists headings from `min` to `max`
(levels 2 and 3 by default; `::toc{max=4}` goes deeper). Headings get an
`id` to link to only on pages with a table of contents, and a heading
that already has one (or a heading permalink) keeps it. It's left out
of excerpts and word counts, and shows nothing when a page has no
headings in range.
