# Creating a content type

**New Content Type**, on **Structure → Content Types**, is the same page
as [a type's page](content-types.md#a-types-page), empty.

Choose its kind
(**Collection**, **Taxonomy**, a collection whose entries file other
entries, or [**Tree**](../concepts/trees.md)), and its plural name,
from which its singular, its name (its key, such as `recipe`), and its
URL prefix follow until you type them. It starts with **Featured
Image**, **Summary**, and **Visibility** on, and **Position** for a tree
or a type that nests. A taxonomy chooses the types it files under
**Relationships**. Its **Addresses** follow what you choose, and each
can be set before it's made.

**Create**,
at the top right beside **Cancel**, makes `user/data/types/{key}.json`, its
index page when chosen (`index.md` in its folder, titled with the plural
name), and for a taxonomy the classify relationship that files the
chosen types, as `user/data/relations/{key}.json`. A taxonomy is a
collection ordered by position, credits no one, and is left out of
`llms.txt`.
