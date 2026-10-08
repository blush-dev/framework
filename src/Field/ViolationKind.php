<?php

/**
 * Violation kind.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Field;

/**
 * What kind of problem a violation is (D-612), so Site Health can group
 * problems of one kind under a heading that says what the site does
 * about them, and leave out those another check reports (ids, terms,
 * folders, and image sizes have checks of their own).
 */
enum ViolationKind: string
{
	// A file, or its front matter, can't be read.
	case Unreadable = 'unreadable';
	// Two files are one entry, and one of them wins.
	case Duplicate = 'duplicate';
	// A value doesn't fit its field.
	case Value = 'value';
	// A required field is missing.
	case Required = 'required';
	// A key that isn't a field.
	case Unknown = 'unknown';
	// A 1.x name for a field, read as the field.
	case Alias = 'alias';
	// A date that isn't real, read as another.
	case Date = 'date';
	// An entry's or media file's id: missing, not valid, or shared.
	case Id = 'id';
	// A term or profile named with no file.
	case Term = 'term';
	// A collection's entry kept in a folder.
	case Folder = 'folder';
	// An image's details listing files that aren't its sizes.
	case Sizes = 'sizes';
	// An entry's parent.
	case Parent = 'parent';
	// A translation and its original.
	case Translation = 'translation';
	// A link between entries.
	case Relation = 'relation';
	// A page a route answers in place of.
	case Route = 'route';
	// A file name's order prefix where it isn't used.
	case Prefix = 'prefix';
	// A collection folder's settings.
	case Collection = 'collection';
	// A component's variant the theme doesn't have.
	case Variant = 'variant';
	// Media details that describe a file that isn't there or isn't
	// allowed, or that another file's details hide.
	case Details = 'details';
	// An audio or video file's cover art.
	case Artwork = 'artwork';
}
