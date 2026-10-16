# Building a blog

This example puts the pieces of [content types](content-types.md) and
[relationships](relationships.md) together: posts listed at `/blog`,
newest first, with a feed, monthly archives, and tags.

## The post type

Posts live in `user/content/_post/` and are listed at `/blog`, newest
first, with a feed and monthly archives, in `user/data/types/post.json`:

```json
{
	"urls": { "prefix": "blog" },
	"listing": { "orderBy": "date", "order": "desc" },
	"feed": true,
	"dateArchives": "month"
}
```

## Tags

Tags live in `user/content/_tag/`, listed under `/blog/tags`, ordered
by position, in `user/data/types/tag.json`:

```json
{
	"urls": { "prefix": "blog/tags" },
	"order": "position",
	"llms": false
}
```

And posts are filed under tags with a `tag` key, through a relation in
`user/data/relations/tag.json`:

```json
{"kind": "classify", "from": ["post"], "to": ["tag"], "create": true}
```

The admin's **New Content Type** writes both when you choose **Terms**
(see [the admin](../admin/README.md)). A plugin can define the
same types and relation in PHP (see
[Content types from a plugin](../features/content-types.md)).

## What you get

| File                        | URL                                                |
|-----------------------------|----------------------------------------------------|
| `_post/index.md`            | `/blog` (the post listing, newest first)           |
| `_post/2026-09-26.hello.md` | `/blog/hello`                                      |
|                             | `/blog/2026` and `/blog/2026/09` (date archives)   |
|                             | `/blog/feed`, `/blog/feed/atom`, `/blog/feed/json` |
| `_tag/php.md`               | `/blog/tags/php` (every post tagged `php`)         |

Create a post with:

```sh
bin/blush content:new post "Hello world"
```

Tag it in its front matter:

```yaml
tag: [php, news]
```

## Making the blog the homepage

To show the latest posts on the homepage, name the type as `home` in
`config/content.php`:

```php
<?php

declare(strict_types=1);

use Blush\Content\ContentConfig;

return new ContentConfig(home: 'post');
```

The homepage then lists posts, with `/page/2` and so on, and the feed
moves to `/feed`. It takes the place of `user/content/index.md`, which is
no longer shown.
