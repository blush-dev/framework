# Workflows

**Structure → Workflows** shows the statuses entries move through and
the steps between them (see [Workflows](../features/workflows.md) and
[Statuses](../features/statuses.md)), in two tabs. It needs
`site.settings`.

## The tabs

- **Workflows** lists each workflow: its statuses, in order, the types
  that use it, and where it's from (Blush, a plugin, the site, or the
  site over one of those). A type picks its workflow in its Type
  Settings.
- **Statuses** lists each status: its plural, whether it puts entries
  on the site, the workflows that use it, and where it's from. **New
  Status** makes one of the site's own, which sets everything. A
  status from Blush or a plugin can be **Relabeled**: its label,
  plural, and tone change, never what it does. **Delete** removes the
  site's own (not while a workflow uses it), and **Revert** takes away
  a relabel.

## A workflow's screen

A workflow's screen has its statuses, in order, with **First** on the
one new entries start at, and its transitions: each a button in the
editor, from some statuses (or any) to one, and whether it needs a
capability of its own, which roles then give on each type. Open a row
to change it, and move rows up and down as Menus' items move.
**Update** saves everything. Keys can't change once saved, since files,
types, and roles name them.

A workflow from Blush or a plugin opens read-only. **Customize** copies
it to the site to edit, and **Revert to {source}'s**, in **⋮**, takes
the copy away. A new workflow starts with Draft and Published, and
Publish and Unpublish.

Taking a status out of a workflow that entries still have asks first.
They keep it in their files, and Site Health's
[**Statuses Outside Their Workflow**](site-health/fixing-problems.md)
lists them, with **Reset** to move them to the first status.
