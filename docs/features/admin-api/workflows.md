# Workflows and statuses

The Workflows screen: workflows and the statuses they use. Every request here is under `/admin/api`, as the [admin's API](README.md) describes.

| Request | What it does |
|---|---|
| `GET workflows` | The Workflows screen (with `site.settings`): `{"workflows", "statuses", "problems"}`. Each workflow is `{"name", "label", "statuses", "initial", "transitions", "subject", "source", "over", "types", "editable", "customize"}` (`source` and `over` each `{"kind", "label"}`, `kind` being `core`, `plugin`, or `site`; `over` is what a site record replaces, or `null`); each status adds `source`, `over`, the `workflows` using it, and whether it may be relabeled (`relabel`), edited (`edit`), or deleted (`delete`). `GET workflows/{name}` is one, with `counts`, how many entries of its types are stored with each status. `PUT workflows/{name}` saves `{"label", "statuses", "initial", "transitions"}` as the site's, `POST workflows/{name}/customize` copies Blush's or a plugin's into it, and `DELETE workflows/{name}` deletes it (`{"deleted": true}`) or reverts a copy (the workflow). `PUT statuses/{name}` saves `{"label", "plural", "tone", "live", "active"}` and `DELETE statuses/{name}` deletes or reverts one, each answering `GET workflows`. What can't be saved is a `422`, saying why |
