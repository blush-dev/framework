# Changing the language

On a site with [translations](../../concepts/translations.md), the
**Language** field on the Document tab lists the site's languages.

Choose another and save to move the entry to it: its file is renamed
with the language's code (`hello.md` becomes `hello.fr.md`, and back),
and its address moves under the language's prefix (`/fr/…`). It keeps
its id and its translations. It's refused, with nothing saved, when one
of its translations is already in that language, or another entry has
its address there.

Landing pages keep their folder's language, so they have no Language
field.
