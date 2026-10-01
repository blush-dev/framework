<?php

/**
 * Admin components controller.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin;

use Psr\Http\Message\ResponseInterface;
use Blush\Component\ComponentContent;
use Blush\Component\ComponentListing;
use Blush\Component\ComponentName;
use Blush\Component\ComponentType;
use Blush\Component\Variant;
use Blush\Content\Schema\Field;
use Blush\Content\Schema\Fields\EnumField;
use Blush\Http\Response;
use Blush\Http\Status;
use Blush\Theme\ThemeChain;
use Blush\Theme\ThemeException;
use Blush\Theme\ThemeResolver;
use Blush\Theme\Themes;
use Blush\View\ViewFactory;
use Blush\View\Views;

/**
 * Answers `GET {path}/api/components` (D-243): the components the admin's
 * inserter offers, which are the registered ones with a class (D-214)
 * that the active theme can render (as `component:list` lists them).
 * Each has:
 *
 * - its full `name` (`blush/callout`, `acme/tabs`), which the inserter
 *   writes (D-171), and its translated `label` and `description`;
 * - `content` (`none`, `text`, or `blocks`) and `kind`, how it's written:
 *   `container` (`:::name`), `leaf` (`::name`), or `inline` (`:name[…]`,
 *   for the core components meant for inside a sentence);
 * - `category`, a core component's group (`ComponentCategory`), or
 *   `null`, with `source` naming the theme, the site, or the extension
 *   the rest come from;
 * - `props`, as schema fields (`Field::toArray()`) with their translated
 *   `label` and, for a choice, `choices` labels by value;
 * - `only`, what a container holds when it's only some things (D-314,
 *   `Component::HOLDS`), such as the gallery's `["image"]`, else `null`;
 * - `variants` under the active theme (D-266), Default not included: each
 *   with its `name`, translated `label` and `description`, and `source`,
 *   `null` when the component's own namespace declared it, else where it
 *   comes from (a theme's variant for a core component, say).
 *
 * Beside them, `image` describes Markdown images, which aren't components
 * but are edited like one (D-268): its `variants` are the classes the
 * active theme offers (`theme.json`'s `variants.image`), each with its
 * `name` (the class), `label`, `description`, and `source` (`null`).
 *
 * `bleed` names the classes the editor's bleed control writes (D-313):
 * `wide` and `full`, as the active theme names them.
 */
final readonly class ComponentsController
{
	public function __construct(
		private ViewFactory $views,
		private ThemeResolver $resolver,
		private Themes $themes,
		private Provenance $provenance
	) {}

	public function __invoke(): ResponseInterface
	{
		try {
			$chain = $this->resolver->active();
			$views = $this->views->forChain($chain);
		} catch (ThemeException $error) {
			return Response::json(['error' => $error->getMessage()], Status::InternalServerError, ['Cache-Control' => 'no-store']);
		}

		$components = [];

		foreach ($views->components() as $component) {
			if ($component->className() !== null && ! $this->themes->isOutside($component->name->namespace, $chain)) {
				$components[] = $this->describe($component, $views, $chain);
			}
		}

		$image = [
			'variants' => array_map(static fn (Variant $variant): array => [
				'name'        => $variant->name,
				'label'       => $views->imageVariantText($variant, 'label') ?? ucfirst(str_replace('-', ' ', $variant->name)),
				'description' => $views->imageVariantText($variant, 'description') ?? '',
				'source'      => null
			], $views->imageVariants())
		];

		return Response::json(['components' => $components, 'image' => $image, 'bleed' => $chain->bleedClasses()], headers: ['Cache-Control' => 'no-store']);
	}

	/**
	 * Describes a component for the inserter.
	 *
	 * @return array<string, mixed>
	 */
	private function describe(ComponentListing $component, Views $views, ThemeChain $chain): array
	{
		$name    = $component->name;
		$type    = $name->isCore() ? ComponentType::tryFrom($name->name) : null;
		$content = $component->definition?->content() ?? ComponentContent::None;

		return [
			'name'        => (string) $name,
			'label'       => $component->displayLabel(),
			'description' => $component->description ?? '',
			'content'     => $content->value,
			'kind'        => match (true) {
				$content === ComponentContent::Blocks => 'container',
				$type?->isInline() === true           => 'inline',
				default                               => 'leaf'
			},
			'category'    => $type?->category()->value,
			'only'        => $component->definition?->holds() ?: null,
			'source'      => $type === null ? $this->provenance->of($name->namespace, $chain) : null,
			'props'       => array_map(
				fn (Field $field): array => $this->prop($field, $name, $views),
				$component->definition?->props() ?? []
			),
			'variants'    => array_map(fn (Variant $variant): array => [
				'name'        => $variant->name,
				'label'       => $views->variantText($name, $variant, 'label') ?? ucfirst(str_replace('-', ' ', $variant->name)),
				'description' => $views->variantText($name, $variant, 'description') ?? '',
				'source'      => $variant->registrant === $name->namespace ? null : $this->provenance->of($variant->registrant, $chain)
			], $component->variants)
		];
	}

	/**
	 * Describes a prop, with its translated label and choices.
	 *
	 * @return array<string, mixed>
	 */
	private function prop(Field $field, ComponentName $component, Views $views): array
	{
		$prop  = $field->toArray();
		$label = $views->componentText($component, "props.{$field->name}.label");

		if ($label !== null) {
			$prop['label'] = $label;
		}

		if ($field instanceof EnumField) {
			$choices = [];

			foreach ($field->options as $value) {
				$choices[$value] = $views->componentText($component, "props.{$field->name}.choices.{$value}") ?? $value;
			}

			$prop['choices'] = $choices;
		}

		return $prop;
	}
}
