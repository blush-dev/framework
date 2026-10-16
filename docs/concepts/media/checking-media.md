# Checking the details

`bin/blush content:lint` (and **Site Health** in the admin) checks
every file in `user/data/media/` along with your content (see
[Checking your content](../checking-content.md)).

- **Errors:** a file that can't be read, such as JSON with a typo, or a
  value that doesn't fit its field. The library treats such a file as
  empty until it's fixed. A media file with no id, or one that isn't a
  UUID, and an id two files share (see
  [Ids and image sizes](ids-and-image-sizes.md)).
- **Warnings:** details for a file that's gone, such as after renaming
  or deleting the file by hand (move the details file with it, or
  delete it), or for a file of a type the site doesn't allow; details for an
  image size, which the original's details stand in for; and
  `renditions` listing a file that's gone. (`renditions` that isn't a
  list of files with their width and height is an error.)
- **With `--strict`:** keys that aren't one of the file's fields.
