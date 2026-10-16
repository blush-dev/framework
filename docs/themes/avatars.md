# Avatars

`$profile->avatar()` draws a profile's avatar the way the admin does:
its picture, from the profile's `avatar` field, or, without one, a
square box with up to two initials of its name ("Jane Doe" is JD), as an
inline SVG. Give a size in pixels (48 by default):

```php
<?php if ($author = $entry->creators()->published()->first()) : ?>
	<p class="byline">
		<?= $author->avatar(32) ?>
		<?= e($author->title) ?>
	</p>
<?php endif ?>
```

## Screen readers

Beside a printed name, it's hidden from screen readers. Alone, give it a
label so it's read as an image: `$profile->avatar(label:
$profile->title)`. Only profiles have avatars: on any other entry, it
prints nothing.

## Styling it

A picture is an `<img>` cropped square at the size you give. Initials
are gray until your theme styles them. Both have the class `avatar`;
two custom properties set the initials' colors, and the letters use the
page's font:

```css
.avatar {
	--avatar-background: #fde2e4;
	--avatar-color: #9d174d;
	border-radius: 50%; /* a circle; leave it out for a square */
}
```

For profiles themselves, see [Credits and profiles](../concepts/credits-and-profiles.md).
