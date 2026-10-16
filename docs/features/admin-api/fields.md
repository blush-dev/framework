# Fields

The controls and field types, and the field library on Structure → Fields. Every request here is under `/admin/api`, as the [admin's API](README.md) describes.

| Request | What it does |
|---|---|
| `GET fields/controls` | The controls and field types definitions can use: `{"controls": [{"value", "label", "description", "section", "type", "implies", "options"}], "types": [{"type", "label", "description", "controls", "options"}]}`. A control's `section` is the picker's (`{"value", "label"}`, or `null` for one the picker doesn't offer), `type` the value type it stores (`null` for one that edits several), and `implies` the type options it writes. A type's `controls` are `{"value", "label"}`, the ones that store it without implying options, its default first. `options` are definition keys as JSON Schemas |
| `GET fields/library` | The field library: `{"fields": [{"name", "label", "description", "required", "type", "control", "editable", "file", "types", "problems"}], "create"}`: the site's fields in `user/data/fields` only; `control` is the one it's drawn with; `types` are the types that have it (`{"name", "label"}`); `problems` says why it's left off a type (a key declared there already); `create` is whether fields can be made here |
| `GET fields/library/{name}` | One field, with the `field` as forms take it (with its `control`, and a choice's option labels as `choices`), and `counts` (how many entries of the types that have it have a value for it, and for an Object's fields by both keys, such as `seo.title`) |
| `POST fields/library` | Make a field in `user/data/fields`: `{"field"}`, a definition with its `name` and its `control` (as forms send it); answers `201` with the field. Needs `site.settings` |
| `PATCH fields/library/{name}` | Change a field: `{"field"}`, the whole definition; its key stays. Answers with the field, and `moved`: an Object's field put somewhere new has its values moved in the entries of the types that have it, and `moved` gives how many `entries` changed, how many `kept` a value already at the new place, and the `failed` ones' reasons by entry id. A key a type that has it already declares is a `422` with the reason |
| `DELETE fields/library/{name}` | Delete a field's file, and take it off every type that has it; answers `{"deleted"}`. Then `POST types/refresh` reindexes |
