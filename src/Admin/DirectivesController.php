<?php

/**
 * Admin directives controller.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin;

use Psr\Http\Message\ResponseInterface;
use Blush\Directive\DirectiveListing;
use Blush\Directive\DirectiveName;
use Blush\Directive\DirectiveType;
use Blush\Directive\Variant;
use Blush\Field\Field;
use Blush\Field\Fields\EnumField;
use Blush\Http\Response;
use Blush\Http\Status;
use Blush\Theme\ThemeChain;
use Blush\Theme\ThemeException;
use Blush\Theme\ThemeResolver;
use Blush\View\ViewFactory;
use Blush\View\Views;

/**
 * Answers `GET {path}/api/directives` (D-243, D-532): the directives the
 * admin's inserter offers: every registered one, each with a class
 * (D-214, D-534). The admin calls them blocks. Each has:
 *
 * - its full `name` (`blush/callout`, `acme/tabs`), which the inserter
 *   writes (D-171), and its translated `label` and `description`;
 * - `content` (`none`, `text`, or `blocks`) and `kind`, how it's written:
 *   `container` (`:::name`), `leaf` (`::name`), or `inline` (`:name[…]`),
 *   as it's registered (D-531), the only form it works in;
 * - `category`, a core directive's group (`DirectiveCategory`), or
 *   `null`, with `source` naming the site or the extension the rest come
 *   from;
 * - `props`, as schema fields (`Field::toArray()`) with their translated
 *   `label` and, for a choice, `choices` labels by value;
 * - `only`, what a container holds when it's only some things (D-314,
 *   `Directive::HOLDS`), such as the gallery's `["image"]`, else `null`;
 * - `variants` under the active theme (D-266), Default not included: each
 *   with its `name`, translated `label` and `description`, and `source`,
 *   `null` when the directive's own namespace declared it, else where it
 *   comes from (a theme's variant for a core directive, say).
 *
 * Beside them, `image` describes Markdown images, which aren't directives
 * but are edited like one (D-268): its `variants` are the classes the
 * active theme offers (`theme.json`'s `variants.image`), each with its
 * `name` (the class), `label`, `description`, and `source` (`null`).
 *
 * `bleed` names the classes the editor's bleed control writes (D-313):
 * `wide` and `full`, as the active theme names them.
 */
final readonly class DirectivesController
{
	public function __construct(
		private ViewFactory $views,
		private ThemeResolver $resolver,
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

		$directives = [];

		foreach ($views->directives() as $directive) {
			$directives[] = $this->describe($directive, $views, $chain);
		}

		$image = [
			'variants' => array_map(static fn (Variant $variant): array => [
				'name'        => $variant->name,
				'label'       => $views->imageVariantText($variant, 'label') ?? ucfirst(str_replace('-', ' ', $variant->name)),
				'description' => $views->imageVariantText($variant, 'description') ?? '',
				'source'      => null
			], $views->imageVariants())
		];

		return Response::json(['directives' => $directives, 'image' => $image, 'bleed' => $chain->bleedClasses()], headers: ['Cache-Control' => 'no-store']);
	}

	/**
	 * Describes a directive for the inserter.
	 *
	 * @return array<string, mixed>
	 */
	private function describe(DirectiveListing $directive, Views $views, ThemeChain $chain): array
	{
		$name       = $directive->name;
		$definition = $directive->definition;
		$type       = $name->isCore() ? DirectiveType::tryFrom($name->name) : null;

		return [
			'name'        => (string) $name,
			'label'       => $directive->displayLabel(),
			'description' => $directive->description ?? '',
			'content'     => $definition->content()->value,
			'kind'        => $definition->kind()->value,
			'category'    => $type?->category()->value,
			'only'        => $definition->holds() ?: null,
			'source'      => $type === null ? $this->provenance->of($name->namespace, $chain) : null,
			'props'       => array_map(
				fn (Field $field): array => $this->prop($field, $name, $views),
				$definition->props()
			),
			'variants'    => array_map(fn (Variant $variant): array => [
				'name'        => $variant->name,
				'label'       => $views->variantText($name, $variant, 'label') ?? ucfirst(str_replace('-', ' ', $variant->name)),
				'description' => $views->variantText($name, $variant, 'description') ?? '',
				'source'      => $variant->registrant === $name->namespace ? null : $this->provenance->of($variant->registrant, $chain)
			], $directive->variants)
		];
	}

	/**
	 * Describes a prop, with its translated label and choices.
	 *
	 * @return array<string, mixed>
	 */
	private function prop(Field $field, DirectiveName $directive, Views $views): array
	{
		$prop  = $field->toForm();
		$label = $views->directiveText($directive, "props.{$field->name}.label");

		if ($label !== null) {
			$prop['label'] = $label;
		}

		if ($field instanceof EnumField) {
			$choices = [];

			foreach ($field->options as $value) {
				$choices[$value] = $views->directiveText($directive, "props.{$field->name}.choices.{$value}") ?? $value;
			}

			$prop['choices'] = $choices;
		}

		return $prop;
	}
}
