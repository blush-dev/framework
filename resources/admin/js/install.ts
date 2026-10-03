/**
 * What the Themes, Plugins, and Icon Packs screens share for installing
 * (D-392): whether the Install modal is open (it opens on its own at
 * `?install=1`, which is how an archive of another kind is sent to its own
 * screen), and what follows an install: the server compiles and reindexes
 * when something that runs was replaced, and the list and counts load
 * again.
 */

import { ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { request, type InstallAnswer } from './api';
import type { InstallKind } from './components/InstallModal.vue';
import { loadCounts } from './counts';
import { can } from './session';

const SCREENS: Record<InstallKind, { route: string; capability: string }> = {
	'theme': { route: 'themes', capability: 'extensions.themes' },
	'plugin': { route: 'plugins', capability: 'extensions.plugins' },
	'icon-pack': { route: 'icon-packs', capability: 'extensions.icon-packs' }
};

export function useInstall(kind: InstallKind, reload: () => Promise<void>) {
	const route      = useRoute();
	const router     = useRouter();
	const canInstall = can(`${SCREENS[kind].capability}.install`);
	const installing = ref(canInstall && route.query.install === '1');

	if (route.query.install !== undefined) {
		const { install: _install, ...query } = route.query;

		void router.replace({ query });
	}

	async function afterInstall(answer: InstallAnswer): Promise<void> {
		if (answer.refresh) {
			await request('POST', '/settings/refresh').catch(() => undefined);
		}

		await reload();
		void loadCounts();
	}

	// An archive of another kind: that kind's screen, with its modal open.
	function hop(other: InstallKind): void {
		installing.value = false;
		void router.push({ name: SCREENS[other].route, query: { install: '1' } });
	}

	return { installing, canInstall, afterInstall, hop };
}
