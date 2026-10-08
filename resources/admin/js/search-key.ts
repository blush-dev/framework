/**
 * A list's search takes `/` (D-612, shared from the entries list): the
 * key puts the caret in the search, unless something is being typed,
 * and the field shows the key (`.search-field__key`) until it has the
 * focus.
 */

import { onBeforeUnmount, onMounted, ref, type Ref } from 'vue';

/**
 * Returns the ref for the search's input, which `/` focuses while the
 * component is mounted.
 */
export function useSearchKey(): Ref<HTMLInputElement | null> {
	const field = ref<HTMLInputElement | null>(null);

	function slash(event: KeyboardEvent): void {
		const target = event.target as HTMLElement | null;

		if (event.key !== '/' || event.metaKey || event.ctrlKey || event.altKey || target?.closest('input, textarea, select, [contenteditable="true"], [role="listbox"]')) {
			return;
		}

		event.preventDefault();
		field.value?.focus();
	}

	onMounted(() => window.addEventListener('keydown', slash));
	onBeforeUnmount(() => window.removeEventListener('keydown', slash));

	return field;
}
