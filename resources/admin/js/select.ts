/**
 * Choosing rows for a list's bulk bar (D-301, D-509): each row by its
 * key, and a header box for every choosable row on the page, which is
 * `mixed` while only some are chosen. The selection is the caller's
 * (`selected`), so it can outlive a page or be cleared with a filter.
 */

import { computed, type Ref } from 'vue';

export function useSelectAll(choosable: () => string[], selected: Ref<string[]>) {
	const chosen = computed(() => choosable().filter((key) => selected.value.includes(key)).length);
	const state  = computed<'true' | 'false' | 'mixed'>(() => chosen.value === 0 ? 'false' : (chosen.value === choosable().length ? 'true' : 'mixed'));

	function isChosen(key: string): boolean {
		return selected.value.includes(key);
	}

	function toggle(key: string): void {
		selected.value = isChosen(key) ? selected.value.filter((item) => item !== key) : [...selected.value, key];
	}

	// All of the page's rows, or none once all are.
	function toggleAll(): void {
		const page = new Set(choosable());

		selected.value = state.value === 'true'
			? selected.value.filter((key) => !page.has(key))
			: [...new Set([...selected.value, ...page])];
	}

	return { state, isChosen, toggle, toggleAll };
}
