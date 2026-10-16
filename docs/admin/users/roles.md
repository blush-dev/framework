# Roles

**Users → Roles** lists each role, and a role's screen shows what it can
do as sections of capabilities you tick or untick. See
[Accounts and roles](../../concepts/accounts/README.md) for every
capability.

## A role's screen

A role's screen shows its key, where it comes from, and who holds it,
then its capabilities in sections: **Site Capabilities** (Media,
Structure, Site, People, and any a plugin adds) and **Content
Capabilities**, **Every Type** first, then one section for each
content type, under **Content types** and then **Taxonomies** (the
collections other entries are [filed under](../../concepts/relationships.md)). Each section says in a sentence what the role can do; open it
to tick or untick its capabilities. **Expand All** opens every section,
and **Show Keys** shows each capability's key.

- Whatever **Every Type** grants applies to every content type,
  including ones added later, and shows ticked (and fixed) in each
  type's section. A type it grants everything on says **Set by Every
  Type**. To let a role do less on one type, choose **Set each
  type separately** from Every Type's **⋮** first: it moves those
  capabilities into each type there is now (types added later then
  get nothing).
- Each section's **⋮** sets the whole section at once: a type's to
  **Full access**, **Their own only**, **Drafts only**, or **No
  access**; a site group's to everything or nothing.
- Ticking **anyone's** ticks **their own** too, and unticking their own
  unticks anyone's: one without the other does nothing.
- A type whose workflow has steps with a capability of their own (such
  as **Submit for Review**) lists them after its actions, and
  **Every Type** lists every workflow's. Taking one into or out of a
  published status also needs publishing. **Publish** and
  **Unpublish** need publishing alone.
- Changes wait in the **save bar** at the bottom, which counts them;
  **Save Changes** saves, **Revert** puts them back, and a section with
  unsaved changes says **Changes**.

## Making and changing roles

- **New Role** makes a role from a name, a key, a description, and its
  capabilities, in the same sections. **Duplicate** on any role starts a
  new one with its capabilities ticked.
- A role you made can be renamed (**⋮ → Rename this role**), described,
  given or denied capabilities, and deleted (**⋮ → Delete this role**)
  once no account holds it. **⋮ → Copy as JSON** copies any role.
- The built-in Editor, Author, and Contributor keep their names, but
  their capabilities can change; **⋮ → Reset to built-in capabilities**
  puts them back.
- The built-in Administrator can change too, and be reset. It starts
  with every built-in capability but installing, updating, and deleting
  plugins and themes.
- The Owner always has every capability, so its screen says so
  instead of listing them; it can't be changed. Roles defined in `config/auth.php` are
  changed there, so they're shown read-only.
