# Content types from a plugin

A plugin can define [content types](../concepts/content-types.md) and the
[relations](../concepts/relationships.md) between them, so a site gets
them by turning the plugin on.

## Types

Write a class that implements `ContentTypeSource` and returns the types:

```php
<?php

declare(strict_types=1);

namespace Acme\Recipes;

use Blush\Content\Query\Order;
use Blush\Content\Type\Collection;
use Blush\Content\Type\ContentTypeSource;
use Blush\Content\Type\Listing;
use Blush\Content\Type\TypeOrder;
use Blush\Content\Type\TypeUrls;

final class ContentTypes implements ContentTypeSource
{
	public function types(): iterable
	{
		// Kept in user/content/_recipe, served under /recipes.
		yield new Collection(
			'recipe',
			urls: new TypeUrls(prefix: 'recipes'),
			listing: new Listing(orderBy: 'date', order: Order::Desc)
		);

		// Cuisines are terms: ordered by position.
		yield new Collection(
			'cuisine',
			urls: new TypeUrls(prefix: 'recipes/cuisines'),
			order: TypeOrder::Position,
			llms: false
		);
	}
}
```

The kinds and options are the same as a data type's (see
[Content types](../concepts/content-types.md)).

## Relations

What files recipes under cuisines is a
[relation](../concepts/relationships.md), which comes from a class of
its own that implements `RelationSource` (one class can't be both):

```php
<?php

declare(strict_types=1);

namespace Acme\Recipes;

use Blush\Content\Relation\Relation;
use Blush\Content\Relation\RelationKind;
use Blush\Content\Relation\RelationSource;

final class Relations implements RelationSource
{
	public function relations(): iterable
	{
		yield new Relation('cuisine', RelationKind::Classify, from: ['recipe'], to: ['cuisine'], create: true);
	}
}
```

## Registering them

Tag both in the plugin's [provider](../plugins/service-providers.md):

```php
protected const array TAGS = [
	ContentTypeSource::TAG => [ContentTypes::class],
	RelationSource::TAG    => [Relations::class]
];
```

## When the site changes them

A site can change one of your collections or trees with a file in
`user/data/types/` named for it (which the admin writes when someone
edits the type): each option the file sets replaces yours, and the
admin's **Reset the Type** removes the file, going back to yours. A
relation in `user/data/relations/` replaces yours by name.

## Names

When two plugins define a type or a relation by the same name, the site
uses the first plugin's and leaves the other's out. Site Health and the
Plugins screen in the admin say so, naming both. So give generic names a
prefix of your own (`acme_related`, not `related`; `acme_series`, not
`series`), and keep plain names for types only your plugin would have.

A plugin still defining a type with the old taxonomy kind stops the site
from loading, with a message saying what to change.
