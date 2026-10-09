/**
 * Waiting on the API (D-505): an action that's busy while it runs and
 * keeps its failure's message for the notice beside it, a search whose
 * late answers are ignored, and a call put off until typing stops.
 */

import { getCurrentScope, onScopeDispose, ref, type Ref } from 'vue';
import { errorMessage } from './api';
import { toast, type ToastKind } from './toast';

/**
 * An action's state: `busy` while one runs, so its button can say so, and
 * `error`, the last one's failure. `run()` clears the error, runs the
 * task, and keeps the server's message (else `fallback`) when it throws,
 * after `failed`, for one that also reads the failure (a field the server
 * blamed); it resolves whether the task finished.
 */
export function useAction() {
	const busy  = ref(false);
	const error = ref('');

	async function run(fallback: string, task: () => Promise<unknown>, failed?: (caught: unknown) => void): Promise<boolean> {
		busy.value  = true;
		error.value = '';

		try {
			await task();

			return true;
		} catch (caught) {
			error.value = errorMessage(caught, fallback);
			failed?.(caught);

			return false;
		} finally {
			busy.value = false;
		}
	}

	return { busy, error, run };
}

/**
 * Numbers requests, so only the latest one's answer is used: `ask()`
 * starts one and returns whether it's still the latest.
 */
export function latest(): () => () => boolean {
	let count = 0;

	return () => {
		const asked = ++count;

		return () => asked === count;
	};
}

/**
 * Puts a call off until `wait` milliseconds pass without another, as a
 * search does while typing. `cancel()` drops a call waiting; one set up
 * in a component is dropped when it unmounts.
 */
export function debounced<A extends unknown[]>(call: (...args: A) => void, wait: number): ((...args: A) => void) & { cancel: () => void } {
	let timer: ReturnType<typeof setTimeout> | undefined;

	const cancel = (): void => clearTimeout(timer);
	const later  = Object.assign((...args: A): void => {
		cancel();
		timer = setTimeout(() => call(...args), wait);
	}, { cancel });

	if (getCurrentScope() !== undefined) {
		onScopeDispose(cancel);
	}

	return later;
}

// What a list's action did: words for its toast, with its kind and Undo.
export type ActionDone = string | { message: string; undo?: () => void; kind?: ToastKind };

/**
 * A list's actions (D-509; the entries list's and Redirects', D-686):
 * `act()` runs one, named so its row can say it's busy (`busy`), then
 * toasts what it did in the past tense, with its Undo, and reloads the
 * list. What it couldn't do is `error` (the list's own, when it has
 * one for its loads too), for the notice above the list. `started` runs
 * before each, to clear what the last one left.
 */
export function useListAction(reload: () => Promise<void>, options: { error?: Ref<string>; started?: () => void } = {}) {
	const busy    = ref<string | null>(null);
	const error   = options.error ?? ref('');
	const started = options.started;

	async function act(name: string, task: () => Promise<ActionDone>, kind: ToastKind = 'good'): Promise<void> {
		busy.value  = name;
		error.value = '';
		started?.();

		try {
			const done = await task();
			const said = typeof done === 'string' ? { message: done } : done;

			toast(said.message, { kind: said.kind ?? kind, undo: said.undo });
			await reload();
		} catch (caught) {
			error.value = errorMessage(caught, 'That didn\'t work. Reload the page and try again.');
		} finally {
			busy.value = null;
		}
	}

	return { busy, error, act };
}
