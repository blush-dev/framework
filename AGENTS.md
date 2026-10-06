# AGENTS.md

Guidance for agents working in this repository.

## Status

The `2.x` branch is a **full rewrite** of Blush, a flat-file CMS, targeting
PHP 8.5. Planning and milestones M0 (setup), M1 (core), M2 (HTTP +
Console), M3 (Routing), M4 (Content), M5 (Views + theming), M6 (Caching +
publishing), and M7 (Static export, since removed for a plugin to
build, D-476) are **complete**. **Milestone M8
(Port jtcom)** is on hold after a trial port on the `jtcom-trial` branch
of `../blush` (a test bed the author uses; never commit its site files).
**The current focus is the admin** (M9 and M10), grown out of the setup
DX/UX work (D-156): first-run setup (D-218), accounts and auth (D-215 to
D-219), the Vue admin app (D-220 to D-227), the editor's write path and
editing API (D-228, D-229), and, built from the admin design direction
(`.claude/docs/admin-design/admin.md`, D-231): the tokens and shell,
per-account preferences on Your profile (D-235), a list per content type
with status and Trash tabs (D-230, D-234, D-236, D-237), and the first
editor (D-233); and, from the clickable prototype
(`.claude/docs/admin-design/blush-admin.html`), the full navigation with
stub screens and a Markdown source editor (D-241), and the component
inserter (D-243); then the section rail (D-244) and the editor as a
writing surface with component options (D-245), and its three
inserters (components, media, icons; D-246, D-247); toasts and the
command palette (D-248); and read-only Roles and Accounts, Content
types, and Media screens (D-249 to D-251); then the updated design's
space scale, four inserters, icon categories, and marked source
(D-265); component variants (D-266); `:::figure` as a container (D-267);
every block as an object, images edited as Markdown, and uploads
(D-268); and alt text and captions in the media library, filled in on
insert (D-269, D-272); and, from the updated direction, every element as
an object with an outline, a breadcrumb, lists and definition lists,
Enter carrying list and quote markers, drawn selects, and a calendar
for dates (D-280); and the reference picker, with the document panel's
groups (D-281, D-283); and the Markdown editing experience's first set
(D-284, D-285); and media metadata fields by kind (D-287) and the
media index (D-288), embedded image metadata (D-289), titles (D-290),
sound and video metadata (D-291), and metadata files in
`content:lint` (D-293); page bundle media is removed, so media lives
only in `user/media` (D-294); and the Appearance, Extensions, and Settings screens, read-only
(D-306, D-308, D-309; the default theme's `excerpts` setting is gone,
D-307), so every screen in the navigation is built; and editing,
creating, and deleting `user/data/types` types (D-311); and editing
accounts and roles, with one-time password links and suspension
(D-312); and, from the direction split into numbered documents, the
editor's toolbar in three parts, bold and italic toggles, the link
form, moving elements, bleed with theme-named classes, the code block
as one box, and the Markdown elements as inserter tiles (D-313); then
moving the element the caret is in, Backspace taking markers off,
guarded directive syntax, media kinds, and `only` (D-314); and Tab in
quotes and over lines of code (D-315); the per-line highlight cache
(D-316); and the shell's rail toggle and section trail, and the
Editorial admin theme (D-317); and account names, used across the
admin, with roles shown by their labels (D-322, D-323); and editable
settings on four screens, saved in `user/data/settings.json` over
`config/` (D-324, D-325); and People as its own rail section, and Appearance named Themes
(D-326, D-327); and authors as their own kind, with archives under each type (D-329
to D-332), an exploration now replaced by accounts, profiles, and
bylines (D-351, from `.claude/docs/admin-design/meridian-profiles.html`),
with its content and routing (D-352) and its admin (D-353) built;
and editing code collections and taxonomies through a file in
`user/data/types` over them, and every URL path of a type (D-349,
D-350); and per-type capabilities (`content.{type}.{action}`, with
`content.*.…` for every type), with a role's screen drawn as capability
sections (D-359, from `.claude/docs/admin-design/meridian-role-capabilities.html`);
and a Calendar on Home, of dated entries by month (D-368); and, from
the revised profiles sketch, Your Account as the account screen on
your own row and the sketch's cleanups (D-369), with accounts keeping
their display name, every account needing an email, and the Users
screens drawn as the sketch is (D-370); and counts in the section
panel, with Your Account at `/accounts/{username}` (D-371); and
extensions as a type system: plugins (once "extensions"), themes, and
icon packs, each known by a `vendor/name` with a label and a declared
namespace, and admin themes planned (D-378, D-379); and, from the themes
sketch (`.claude/docs/admin-design/blush-themes-screen.html`), the
Themes screen as cards with previews drawn from each theme's declared
palette, activating a theme in `user/data/settings.json`, and deleting
theme folders, with a details screen for each theme (D-381,
D-383);
and components rendering themselves, a theme's template winning
(D-382); and, from the extensions sketch
(`.claude/docs/admin-design/blush-extensions.html`), the Plugins and
Icon Packs screens: switches saved in `user/data/settings.json`,
plugins' `requires` enforced at boot, packs that can be turned off,
deleting, and a details screen for each (D-385); and the Tree kind in
place of Pages: the built-in page type a tree, and trees of a site's own
in their folders (D-386); and toasts as the toast sketch
(`.claude/docs/admin-design/toast-sketch.html`) draws them: kinds, a
countdown, stacking, and Undo (D-387); and, ahead of installing
extensions from the admin into `user/` (D-388), a capability for each
extension action per kind, `extensions.{kind}.{action}` (D-389); and
nothing in `user/` on until it's named, with config listing only what's
on (D-390), and the admin's saved list naming everything that's on,
Composer's included (D-391); and installing and replacing extensions
from a `.zip`, from the sketch's uploader (D-392), and rolling back to
the version a replace kept (D-393); and, from a discussion of
APIs, agents, and headless sites (in `open-questions.md`), Markdown
pages and `llms.txt` (D-395), with full URLs (D-396); an AI Settings screen (Markdown copies, the
types `llms.txt` lists, AI crawler rules), with a site description on
General (D-398, D-399), and a `Blush\Ai` provider layer for plugins
planned (D-397); and, from the settings sketch
(`.claude/docs/admin-design/meridian-settings-sketch.html`), every
Settings screen drawn full width as rows of label, control, and help,
with switches (D-404), and its Media screen: upload rules by kind, and
a Documents kind (D-406); and media capabilities by kind for
uploading and by whose file it is for changing and deleting, with
deleting media, which warns where a file is used (D-407); and a new
tree page's parent, a parent kept as a file becoming its folder's
`index.md` (D-408), with order prefixes only for collections and
taxonomies (D-409); and moving a tree's page with the pages under it
(D-410); and error pages pinned on Pages (D-411); and `position` for
sibling order in trees and taxonomies (D-412), first on the All tab,
with collections newest published first (D-413); and every local
extension in `extensions/{vendor}/{name}` at the site's root, Composer's
model, with manifests in Composer's shape (`require`, `autoload` with
`files`) falling back to `composer.json` (D-418); and, ahead of a read-only
content API (D-479), a UUIDv7 `id` in every content file, with
`content:ids` and Content health to fix files without one (D-477,
D-478, D-480); and trash as a status, `status: trash` on a file left
where it is, outside "any status" and every status control (D-484);
and storage as a driver per area (content, data, accounts, sessions)
named in config or `STORAGE_DRIVER`, with `filesystem` the only one
for now, so a site can later keep its data in a database (D-485,
D-486); build stored data with that in mind; and an id for every media
original, with image sizes found by rule and given none
(D-487), and sizes recorded in their image's details, the library
listing one item per image (D-488); and Blush's own Markdown API over
league/commonmark, which is never public: one dialect, settings by name
(D-492), mentions linking to profiles (D-493), a Writing settings
screen with smart punctuation, heading anchors, and line breaks on by
default (D-494), raw HTML in the admin by capability, with an allowed
list and an always-refused list (D-495), and struck, highlighted, and
code text in the editor's inline menu (D-496); SVG never uploaded
(D-497); and mentions highlighted and suggested as `@` is typed
(D-498); and an owner role above the administrator, which only an
owner gives or changes, with the administrator a list that leaves out
changing plugins and themes (D-500); and view engines chosen by file
extension, plain PHP built in, so plugins can add Twig or Blade later (D-502).
The Fields
API (D-337 to D-348: field types and controls, field sets on content
types, media, and the Settings screens, slots, and Structure → Fields)
is paused as a baseline: the author thinks there's more to get right,
so build nothing more on it until it's picked up again (D-348).
Next: more
of media; and more of the Markdown editing experience (live
preview is unsettled; see `open-questions.md`); see `.claude/docs/roadmap.md`.
Admin app sources are in `resources/admin/`; rebuild with
`npm run admin:build` (D-221, D-224). Admin CSS reads design tokens
from `resources/admin/css/tokens.css` only: no literal colors, fonts,
type sizes, or radii elsewhere (D-231).
The dev site is `../blush` (`ddev start`, https://blush.ddev.site), on
`jtcom-trial` for now (the skeleton itself is its `2.x` branch). Code on
`master` (1.x) is not a reference implementation, with one exception:
every content convention 1.x supports must keep working (D-078), unless
a tool in the admin and the CLI migrates existing content (D-478).

"Blush" is a working name. Keep product-name references centralized so a rename
stays mechanical.

## Git

**Never commit or push.** The user reviews and commits all changes. Leave work
uncommitted and summarize what changed.

## Project knowledge lives in `.claude/docs/`

At the start of a session, read `roadmap.md` (current milestone) and skim
`decisions.md`. Read the other docs before touching their subsystems, and
**keep them all current**:

| File | Purpose |
|---|---|
| [`.claude/docs/decisions.md`](.claude/docs/decisions.md) | Numbered decision log. Append a new entry for every decision; never silently change a past one (supersede it instead). |
| [`.claude/docs/paths.md`](.claude/docs/paths.md) | Framework layout, site layout, and paths to related local repos. |
| [`.claude/docs/architecture.md`](.claude/docs/architecture.md) | Subsystem-by-subsystem design. |
| [`.claude/docs/theming.md`](.claude/docs/theming.md) | Theming system design. |
| [`.claude/docs/cli.md`](.claude/docs/cli.md) | Custom CLI design. |
| [`.claude/docs/roadmap.md`](.claude/docs/roadmap.md) | Milestones and exit criteria. |
| [`.claude/docs/open-questions.md`](.claude/docs/open-questions.md) | Unresolved questions. Move each to `decisions.md` once answered. |
| [`.claude/docs/admin-design/00-project-brief.md`](.claude/docs/admin-design/00-project-brief.md) | The admin's design direction (D-231, split into numbered documents in D-313: the brief, foundations, components, editor, screens, conventions, open questions, decisions log, and runbook), kept exactly as the author uploads it, with its prototype `meridian-admin.html` ("Meridian" is the design project's codename only). Read the brief, foundations, and the part you're changing before writing admin UI; don't edit them. `old/` has the earlier single `admin.md`. `tokens.css` beside them is the prototype's, not the build source. |
| [`.claude/docs/admin-design/departures.md`](.claude/docs/admin-design/departures.md) | The project's side of the direction: file paths, every departure from it and why, and its settled questions. Read with the direction; record a departure here, not there. |

When the user makes a decision in conversation, record it in `decisions.md`
(and update any affected doc) in the same session.

## User documentation lives in `docs/`

`docs/` is the documentation for people installing and using Blush (D-141).
Keep it plain and task-first, and document only what's implemented. When
you change user-facing behavior (config options, front matter, content
conventions, CLI commands, the theming API), update `docs/` in the same
session.

## Commands

Run on PHP 8.5 (`php -v`). Run `composer check` before handing work back.

| Command | What it does |
|---|---|
| `composer install` | Install dev tools |
| `composer check` | Lint, analyse, and test (all three) |
| `composer lint` | PHPCS (`.phpcs.xml`) |
| `composer fix` | PHPCBF auto-fix |
| `composer analyse` | PHPStan, level max (`phpstan.neon`) |
| `composer test` | PHPUnit 12 (`phpunit.xml`) |
| `composer bench` | PHPBench against the generated jtcom-sized site (`benchmarks/`, D-101) |
| `composer schemas` | Regenerate the editor JSON Schemas in `resources/schemas/` (D-206) |
| `npm run admin:build` | Type-check and build the admin app (`resources/admin/` → `public/admin/`, committed; D-221). Run after changing admin sources |
| `npm run admin:watch` | Rebuild the admin app on every change |

Single test: `vendor/bin/phpunit --filter FrameworkTest`.

## Architecture patterns

- **Container + service providers.** The application registers service
  providers that wire each subsystem into the DI container. Nothing is
  instantiated until needed. The container, application, and event system
  live in-tree (copied from the author's x3p0 packages); they are not Composer
  dependencies.
- **No global state.** No static facades, no `define()` constants, and no global
  helper functions except template escaping helpers. Use constructor
  injection.
- **Type enum + Registry + Factory + Registrar** for extensible subsystems
  (parsers, field types, cache drivers, components, commands). A backed enum
  maps canonical keys to built-in classes. The registry stores
  `key => class-string`. The factory instantiates through the container. The
  registrar seeds the built-ins without overwriting extension registrations.
- **Abstract base + final concrete types.** Typehint the abstract base or an
  interface. Concrete implementations are `final`.
- **Immutable, typed configuration objects**, never associative arrays passed
  around. Each also offers a `fromArray()` factory.
- **Render anywhere.** Nothing reads superglobals except the request factory,
  so `Kernel::handle(Request)` works from the web, the CLI, tests, static
  export, and admin preview.
- **Flat files are the default, not the only option.** Content storage goes
  through `ContentSource` / `ContentIndex` / `ContentRepository` interfaces.
- **The admin shares what repeats** (D-505 to D-509). Before writing
  admin UI, use the shared components (`resources/admin/js/components`),
  classes (`admin.css`), and modules (`resources/admin/js/*.ts`) that
  fit. A class is for how something looks; a component is for markup
  repeated three or more times or that has behavior; a module function
  is for logic. When something would be written a second or third time,
  make it shared, or ask the author when it's a judgment call. Sketches
  and mockups are read the same way: build them from existing pieces,
  and ask the author when a design disagrees with the admin.

## Coding conventions

Follow the `blush-code-style-php` skill (`.claude/skills/blush-code-style-php`).
This project uses only its own skills (D-037). Key points:

- PHP 8.5 minimum. Use modern features freely.
- `declare(strict_types=1);` in every PHP file.
- Tabs, and no spaces inside parentheses.
- MIT license in file headers.
- Avoid Laravel-isms in naming (no facades, "Illuminate"-style names,
  `make:` commands, "Foundation", and so on). Prefer `Core`.
