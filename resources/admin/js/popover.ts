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
}

// Space kept between the panel and the window's edges.
const EDGE = 8;

/**
 * A panel under its button, lined up with its start, or over it when
 * there's no room below; kept EDGE from the window's edges.
 */
export function placeNear(box: DOMRect, panel: HTMLElement, options: PopoverOptions): Record<string, string> {
	const height = panel.offsetHeight;
	const width  = options.matchWidth === true ? Math.max(panel.offsetWidth, box.width) : panel.offsetWidth;
	const below  = box.bottom + options.gap + height + EDGE <= window.innerHeight;
	const place: Record<string, string> = {
		left: `${Math.max(EDGE, Math.min(box.left, window.innerWidth - width - EDGE))}px`,
		top: `${below ? box.bottom + options.gap : Math.max(EDGE, box.top - height - options.gap)}px`
	};

	if (options.matchWidth === true) {
		place.minWidth = `${box.width}px`;
	}

	return place;
}

/**
 * A popover's state: whether it's `open`, and where it's placed (`null`
 * until it's measured, when it's drawn hidden). `show()` opens and places
 * it; `close()` closes it, focusing the button unless told not to.
 */
export function usePopover(button: Ref<HTMLElement | null>, panel: Ref<HTMLElement | null>, options: PopoverOptions) {
	const open  = ref(false);
	const place = ref<Record<string, string> | null>(null);

	async function show(): Promise<void> {
		open.value  = true;
		place.value = null;
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

	return { open, place, show, close };
}
