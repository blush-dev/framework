/**
 * Waiting on the API (D-505): an action that's busy while it runs and
 * keeps its failure's message for the notice beside it, a search whose
 * late answers are ignored, and a call put off until typing stops.
 */

import { getCurrentScope, onScopeDispose, ref } from 'vue';
import { errorMessage } from './api';

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
