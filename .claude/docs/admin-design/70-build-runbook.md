# Build & verify runbook

> **Part of the Meridian admin design direction**, in this project under
> `design/`. Start at `design/00-project-brief.md`.

Open this before changing the prototype. It exists because of one thing that is
not obvious: **the prototype is not on disk.** Every conversation starts in a
fresh container with nothing in it. The artifact is the only copy.

The design documents are the opposite case — they live in this project under
`design/` and are read and written with `project_read` / `project_write`. Only
the prototype has to be fetched.

---

## Getting the prototype

```
Artifact  action: read   url: https://claude.ai/artifact/S1PBodE7fni3JSBcQnARye
```

That returns the published HTML. Save it to a working file — `cms-admin.html`
by convention — and work on that file. Everything below assumes it is there.

**The published file has no doctype, `<html>`, `<head>` or `<body>`.** The
Artifact publisher supplies the page skeleton, so the source starts at
`<title>` and the sprite. Do not add a skeleton to it; the standalone build is
what wraps it, and that is a separate file.

**Publishing may refuse the first attempt** with "you hadn't viewed the live
version". The whole saved file has to be read before it will accept a publish —
read it in chunks of about 500 lines, then publish. A working copy that was
`cp`'d from that saved file loses nothing, but the read is still required.

## Making a change

The prototype is one file: a `<style>` block, an inline SVG sprite of Lucide
symbols, the markup for every screen, and one `<script>`. No build step, no
framework, no dependencies.

Edit it with the file tools. Two habits, both learned the hard way:

- **Replace by a unique string or by line index, never by a marker you have not
  counted.** Searching for the end of a function by its closing brace has
  silently eaten three later functions in this project. If you script a
  replacement, assert the occurrence count first.
- **A script that aborts partway writes nothing.** A failed assertion in the
  middle of a Python edit means the `open(p, "w")` at the end never runs, so
  every successful replacement earlier in that script is discarded too. Re-grep
  after any failure rather than assuming the file is where you left it.
## Verifying

Syntax first — the script is inline, so extract it:

```python
import re
s = open("cms-admin.html", encoding="utf-8").read()
b = re.findall(r"<script>(.*?)</script>", s, re.S)
open("_js.js", "w").write("\n".join(b))
```

```
node --check _js.js
```

Then **verify in a browser, not by reading the diff.** Chromium and Playwright
are preinstalled (`PLAYWRIGHT_BROWSERS_PATH=/opt/pw-browsers`; never run
`playwright install`). A probe looks like this:

```js
const {chromium} = require('playwright');
const T = ms => new Promise(r => setTimeout(r, ms));
(async () => {
  const b = await chromium.launch();
  const ctx = await b.newContext({viewport:{width:1500,height:1000}, deviceScaleFactor:2});
  const p = await ctx.newPage();
  const errors = [];
  p.on('pageerror', e => errors.push('PAGEERROR ' + e.message));
  p.on('console', m => { if (m.type() === 'error' && !/ERR_TUNNEL|fonts\.googleapis/.test(m.text())) errors.push(m.text()); });
  await p.goto('file:///mnt/user-data/outputs/meridian-admin.html');
  await T(700);
  // …drive the screen, then assert on the DOM
  await b.close();
  console.log(errors.length ? 'ERRORS:\n' + errors.join('\n') : 'no errors');
})();
```

Useful entry points for a probe:

| To reach | Do |
|---|---|
| A content list | `click('[data-section="content"]')` then `click('#nav .nav-item[data-name="post"]')` |
| The editor | `click('#tbody tr:nth-child(2) .t-title')` |
| The index page | `click('#pinBody .t-title')` — it is pinned in `#pinBody`, not in `#tbody` |
| The settings drawer | `click('#edSideToggle')` — it starts closed |
| A caret position | set `#edSrc.setSelectionRange(i, i)` then dispatch a `click` event on it |
| A new source | set `#edSrc.value` then dispatch an `input` event |

**Always print console errors.** A silent probe that "passes" while the page
throws on every keystroke has happened here more than once.

Screenshot and read the image when the change is visual. Check both themes and
both color schemes for anything that touches color — `colorScheme: 'dark'` on
the context, and `document.documentElement.setAttribute('data-admin-theme',
'editorial')` for the second admin theme.

### Proving the highlight layer still lines up

Any change to `renderHl` or to a `.hl-*` rule can move the caret off the
character it is on, and **reading the CSS will not tell you.** Three separate
mistakes in the fenced-block box passed inspection and were caught here.

The test: lay the raw source out in a second `<pre>` that matches `#edHl` in
every property that affects metrics, and compare where every rendered line
lands. That reference is exactly how the `<textarea>` lays the same text out,
so matching it is matching the caret.

```js
const compare = () => p.evaluate(() => {
  const hl = document.querySelector('#edHl');
  const cs = getComputedStyle(hl);
  const ref = document.createElement('pre');
  ['fontFamily','fontSize','lineHeight','letterSpacing','whiteSpace','overflowWrap',
   'wordBreak','tabSize','fontWeight','fontStyle'].forEach(k => ref.style[k] = cs[k]);
  ref.style.margin = ref.style.padding = ref.style.border = '0';
  ref.style.position = 'absolute';
  ref.style.width = hl.getBoundingClientRect().width + 'px';
  ref.style.visibility = 'hidden';
  ref.textContent = document.querySelector('#edSrc').value;
  hl.parentNode.appendChild(ref);

  /* the first glyph of every rendered line, relative to the element's own box */
  const anchors = (el) => {
    const r0 = el.getBoundingClientRect();
    const w = document.createTreeWalker(el, NodeFilter.SHOW_TEXT);
    const out = [], range = document.createRange();
    let last = null, n;
    while ((n = w.nextNode())){
      const v = n.nodeValue;
      for (let i = 0; i < v.length; i++){
        if (/\s/.test(v[i])) continue;
        range.setStart(n, i); range.setEnd(n, i + 1);
        const rc = range.getBoundingClientRect();
        if (!rc.height) continue;
        const top = rc.top - r0.top;
        if (last === null || Math.abs(top - last) > 1){
          out.push([+top.toFixed(2), +(rc.left - r0.left).toFixed(2)]);
          last = top;
        }
      }
    }
    return out;
  };
  const a = anchors(hl), r = anchors(ref);
  ref.remove();

  const bad = [];
  for (let i = 0; i < Math.max(a.length, r.length); i++){
    const x = a[i], y = r[i];
    if (!x || !y){ bad.push({i, why: 'line count'}); continue; }
    if (Math.abs(x[0] - y[0]) > 0.5 || Math.abs(x[1] - y[1]) > 0.5) bad.push({i, hl: x, ref: y});
  }
  const ta = document.querySelector('#edSrc');
  const prev = ta.style.height; ta.style.height = 'auto';
  const natural = ta.scrollHeight; ta.style.height = prev;
  return {bad, preH: hl.offsetHeight, taNatural: natural};
});
```

A pass is **`bad.length === 0` and `preH >= taNatural`** — no line out of place,
and the layer at least as tall as the text it is standing in for. Run it over a
set of sources, not one: a construct in the middle of a document, at the very
end, as the first thing, twice over, unterminated, and with a line long enough
to wrap. The end and first-thing cases are where margins leak.

A visual confirmation on top of the numbers: select a word in the middle of the
construct and screenshot. The selection tint is painted by the textarea and the
glyphs by the `<pre>`, so if the tint sits exactly over the word, the two layers
agree.

## Rebuilding the standalone

`meridian-admin.html` is the prototype wrapped as a complete document, so it can
be opened from disk. Split the source at the sprite and wrap:

```python
src = open("cms-admin.html", encoding="utf-8").read()
i = src.index('<svg style="display:none"')
head, body = src[:i], src[i:]
doc = ('<!doctype html>\n<html lang="en">\n<head>\n<meta charset="utf-8">\n'
       '<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">\n'
       '<title>Meridian — Admin prototype</title>\n'
       '<style>:root{color-scheme:light}html,body{margin:0;padding:0}\n'
       'body{font:14px/1.5 system-ui,-apple-system,"Segoe UI",sans-serif;background:#fff}\n'
       'img{max-width:100%}[hidden]{display:none!important}</style>\n'
       + head + "\n</head>\n<body>\n" + body + "\n</body>\n</html>\n")
open("/mnt/user-data/outputs/meridian-admin.html", "w", encoding="utf-8").write(doc)
```

Then open *that* file in the probe, not the source — it is what the reader gets,
and it is where a broken wrap would show up.

## Keeping tokens.css honest

If any value in the prototype's six theme blocks moved, `tokens.css` has to
move with it. The six blocks are:

```
:root{
:root[data-dir="editorial"]{
:root:not([data-theme="light"]){
:root:not([data-theme="light"])[data-dir="editorial"]{
:root[data-theme="dark"]{
:root[data-theme="dark"][data-dir="editorial"]{
```

`tokens.css` spells the two attributes `data-admin-theme` and
`data-color-scheme` — the names they should ship with. Same two axes.

Check parity by pulling every `--token: value` out of each block and comparing
against the file; a mismatch in either direction is a bug. The current set is
**57 token names**, every one of them defined on bare `:root`.

## Publishing

```
Artifact  action: publish
          url: https://claude.ai/artifact/S1PBodE7fni3JSBcQnARye
          file_path: <your working copy>
          label: <a few words on what changed>
```

**Always the same URL.** Publishing without `url` creates a second artifact and
splits the project in half.

Then write any changed design document back to its `design/` path with
`project_write`, and deliver the standalone so it is downloadable.

## The failure modes that have actually happened

| Symptom | Cause |
|---|---|
| A function is suddenly undefined | A scripted block replacement matched a closing marker inside a *later* function and ate everything between |
| Edits vanish with no error | A Python edit script asserted and exited before its write |
| The caret drifts from the character it is on | Something in the highlight CSS changed a character's advance width — check §8 *Marking the source* |
| A block in the highlight layer sits 7px off, but only at the top or bottom of the document | A block element's vertical margins collapsed through the `<pre>` because it was the first or last child. Do not use vertical margins in that layer at all |
| A blank line appears under a construct | A block element in the highlight layer was followed by a `\n` as well. The block already ends its line |
| The last line is clipped while typing | The highlight layer came out shorter than the text — the textarea is sized from it. Compare `#edHl.offsetHeight` against the textarea's own `scrollHeight` |
| The panel describes an element the caret is not in | Something keyed on a directive's *index*, which shifts when a directive above it is deleted. Key on identity |
| "Invalid string length" / a hang on render | A stateful regex (`/g`) shared across a recursive highlight call, clobbering `lastIndex` |
| A global click handler fires twice | Two features using the same `data-` attribute name |
| Escape closes the wrong thing | A new overlay was not added to the Escape chain before the drawer |
| A selection or overlay hides the text | Something opaque was painted over the transparent-textarea editor |
