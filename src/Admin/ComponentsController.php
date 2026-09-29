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
use Blush\Content\Schema\Field;
use Blush\Content\Schema\Fields\EnumField;
use Blush\Extension\ExtensionManifest;
use Blush\Extension\Extensions;
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
 *   `label` and, for a choice, `choices` labels by value.
 */
final readonly class ComponentsController
{
	public function __construct(
		private ViewFactory $views,
		private ThemeResolver $resolver,
		private Themes $themes,
		private Extensions $extensions
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

		return Response::json(['components' => $components], headers: ['Cache-Control' => 'no-store']);
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
			'source'      => $type === null ? $this->source($name, $chain) : null,
			'props'       => array_map(
				fn (Field $field): array => $this->prop($field, $name, $views),
				$component->definition?->props() ?? []
			)
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

	/**
	 * Names where a component that isn't core comes from: a theme in the
	 * chain, the site (`app`), or an extension (by its vendor).
	 *
	 * @return array{kind: string, label: string}
	 */
	private function source(ComponentName $name, ThemeChain $chain): array
	{
		foreach ($chain->themes as $theme) {
			if ($theme->slug === $name->namespace) {
				return ['kind' => 'theme', 'label' => $theme->name];
			}
		}

		if ($name->namespace === 'app') {
			return ['kind' => 'site', 'label' => 'This site'];
		}

		$extensions = array_values(array_filter(
			$this->extensions->all(),
			static fn (ExtensionManifest $extension): bool => $extension->name === $name->namespace || str_starts_with($extension->name, "{$name->namespace}/")
		));

		return ['kind' => 'extension', 'label' => count($extensions) === 1 ? $extensions[0]->name : $name->namespace];
	}
}
