/**
 * What the Themes, Plugins, and Icon Packs screens share for installing
 * (D-392): whether the Install modal is open (it opens on its own at
 * `?install=1`, which is how an archive of another kind is sent to its own
 * screen), and what follows an install: the server compiles and reindexes
 * when something that runs was replaced, and the list and counts load
 * again; and the modal's word on what was installed and its next step
 * (turning it on, or activating it), from a kind's small adapter (D-509).
 */

import { ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { refreshIfAsked, type InstallAnswer } from './api';
import type { InstallKind, InstallState } from './components/InstallModal.vue';
import { loadCounts } from './counts';
import { KIND_PATHS } from './extensions';
import { can } from './session';

// What a kind's list screen tells the Install modal about what it installed.
export interface InstallAdapter<T> {
	// The installed extension by name, from the list loaded again.
	find: (name: string) => T | null;
	// What it is now, and whether it can be turned on or activated.
	state: (extension: T | null, name: string) => InstallState;
	// The modal's next step: turning it on, or activating it.
	start: (extension: T) => Promise<unknown>;
}

export function useInstall<T>(kind: InstallKind, reload: () => Promise<void>, adapter: InstallAdapter<T>) {
	const route      = useRoute();
	const router     = useRouter();
	const canInstall = can(`extensions.${KIND_PATHS[kind]}.install`);
	const installing = ref(canInstall && route.query.install === '1');

	if (route.query.install !== undefined) {
		const { install: _install, ...query } = route.query;

		void router.replace({ query });
	}

	async function afterInstall(answer: InstallAnswer): Promise<void> {
		await refreshIfAsked(answer);
		await reload();
		void loadCounts();
	}

	// An archive of another kind: that kind's screen, with its modal open.
	function hop(other: InstallKind): void {
		installing.value = false;
		void router.push({ name: KIND_PATHS[other], query: { install: '1' } });
	}

	function installState(name: string): InstallState {
		return adapter.state(adapter.find(name), name);
	}

	// Closes the modal, then takes the next step it offered.
	async function next(name: string): Promise<void> {
		installing.value = false;

		const extension = adapter.find(name);

		if (extension !== null) {
			await adapter.start(extension);
		}
	}

	return { installing, canInstall, afterInstall, hop, installState, next };
}
