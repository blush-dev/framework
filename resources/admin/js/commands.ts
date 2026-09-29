/**
 * The command palette's commands (admin.md §7, D-248). The palette always
 * has commands for going places and creating entries; a screen adds its
 * own while it's open (`useCommands`), such as the editor's focus mode,
 * which come first.
 */

import { onBeforeUnmount, onMounted, ref, type Ref } from 'vue';
import type { IconName } from './icons';

export interface Command {
	id: string;
	label: string;
	icon: IconName;
	// More words it's found by.
	keywords?: string;
	// A shortcut to show beside it.
	shortcut?: string;
	run: () => void;
}

const sources = ref(new Map<symbol, () => Command[]>()) as Ref<Map<symbol, () => Command[]>>;

/**
 * The commands the screens on show have added.
 */
export function screenCommands(): Command[] {
	return [...sources.value.values()].flatMap((source) => source());
}

/**
 * Adds a screen's commands to the palette while it's mounted.
 */
export function useCommands(source: () => Command[]): void {
	const key = Symbol('commands');

	onMounted(() => {
		sources.value = new Map(sources.value).set(key, source);
	});

	onBeforeUnmount(() => {
		const next = new Map(sources.value);

		next.delete(key);
		sources.value = next;
	});
}

/**
 * Whether a command matches a search: every word in its label or
 * keywords.
 */
export function commandMatches(command: Command, query: string): boolean {
	const words = query.trim().toLowerCase().split(/\s+/).filter((word) => word !== '');
	const text  = `${command.label} ${command.keywords ?? ''}`.toLowerCase();

	return words.every((word) => text.includes(word));
}
