/**
 * Popovers (D-505): a panel shown beside the button that opens it, over
 * the page, as a select's list or a date picker is. A press outside it
 * closes it, and closing puts focus back on the button.
 */

import { getCurrentScope, nextTick, onScopeDispose, ref, type Ref } from 'vue';

/**
 * Calls `close` on a press outside every element `inside` lists, while
 * `open` says the popover is open; `capture` hears the press before
 * anything under it can stop it.
 */
export function onPressOutside(inside: Ref<HTMLElement | null>[], open: () => boolean, close: () => void, capture = false): void {
	const press = (event: PointerEvent): void => {
		if (open() && event.target instanceof Node && !inside.some((element) => element.value?.contains(event.target as Node) === true)) {
			close();
		}
	};

	document.addEventListener('pointerdown', press, capture);

	if (getCurrentScope() !== undefined) {
		onScopeDispose(() => document.removeEventListener('pointerdown', press, capture));
	}
}

export interface PopoverOptions {
	// Space between the button and the panel.
	gap: number;
	// Whether the panel is at least the button's width.
	matchWidth?: boolean;
	// Whether the panel is exactly the button's width, its content
	// shortened to fit (a search's results under its box), so it stays
	// lined up with the box however long what it lists.
	sameWidth?: boolean;
}

// Space kept between the panel and the window's edges.
const EDGE = 8;

/**
 * A panel under its button, lined up with its start, or over it when
 * there's no room below; kept EDGE from the window's edges.
 */
export function placeNear(box: DOMRect, panel: HTMLElement, options: PopoverOptions): Record<string, string> {
	const height = panel.offsetHeight;
	const width  = options.sameWidth === true ? box.width : options.matchWidth === true ? Math.max(panel.offsetWidth, box.width) : panel.offsetWidth;
	const below  = box.bottom + options.gap + height + EDGE <= window.innerHeight;
	const place: Record<string, string> = {
		left: `${Math.max(EDGE, Math.min(box.left, window.innerWidth - width - EDGE))}px`,
		top: `${below ? box.bottom + options.gap : Math.max(EDGE, box.top - height - options.gap)}px`
	};

	if (options.sameWidth === true) {
		place.width = `${box.width}px`;
	} else if (options.matchWidth === true) {
		place.minWidth = `${box.width}px`;
	}

	return place;
}

/**
 * Where a popover's panel goes (`<Teleport :to>`): into the modal its
 * button is in, since a modal leaves the page under it out of reach
 * (D-686), else the page's body.
 */
export function layerOf(element: HTMLElement | null): HTMLElement | 'body' {
	return element?.closest<HTMLElement>('dialog[open]') ?? 'body';
}

/**
 * A popover's state: whether it's `open`, where it's placed (`null`
 * until it's measured, when it's drawn hidden), and the `layer` it's
 * drawn in (`layerOf()`). `show()` opens and places it, or places it
 * again when it's open; `close()` closes
 * it, focusing the button unless told not to.
 */
export function usePopover(button: Ref<HTMLElement | null>, panel: Ref<HTMLElement | null>, options: PopoverOptions) {
	const open  = ref(false);
	const place = ref<Record<string, string> | null>(null);
	const layer = ref<HTMLElement | 'body'>('body');

	async function show(): Promise<void> {
		const was = open.value;

		layer.value = layerOf(button.value);
		open.value  = true;

		// Shown again while open (its content changed), it's placed again
		// where it is, not hidden first, so it doesn't flicker.
		if (!was) {
			place.value = null;
		}

		await nextTick();

		const box = button.value?.getBoundingClientRect();

		if (box !== undefined && panel.value !== null) {
			place.value = placeNear(box, panel.value, options);
		}
	}

	function close(refocus = true): void {
		if (!open.value) {
			return;
		}

		open.value = false;

		if (refocus) {
			button.value?.focus();
		}
	}

	onPressOutside([button, panel], () => open.value, () => close(false), true);

	return { open, place, layer, show, close };
}
