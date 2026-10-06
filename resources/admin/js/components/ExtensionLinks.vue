<script setup lang="ts">
/**
 * An extension's homepage, support, and funding links (D-428), as rows
 * of its details' facts: **Links** (each named for what it is, in
 * order) and **Sponsor** (each funding link, named for where it goes).
 * Neither row is drawn when it has nothing. `support.email` isn't shown
 * (the author's call), though the API has it.
 *
 * Its rows are a fragment inside the details' `.facts`, whose global
 * rules style its `dt` and `dd`.
 */

import { computed } from 'vue';
import AdminIcon from './AdminIcon.vue';
import type { ExtensionFunding, ExtensionLink } from '../api';

const props = defineProps<{ links: ExtensionLink[]; funding: ExtensionFunding[] }>();

const shown = computed(() => props.links.filter((link) => link.kind !== 'email'));

const names: Record<string, string> = {
	homepage: 'Homepage',
	docs: 'Documentation',
	source: 'Source',
	issues: 'Issues',
	forum: 'Forum',
	chat: 'Chat',
	wiki: 'Wiki',
	irc: 'IRC',
	rss: 'RSS',
	security: 'Security'
};

const platforms: Record<string, string> = {
	github: 'GitHub Sponsors',
	patreon: 'Patreon',
	opencollective: 'Open Collective',
	tidelift: 'Tidelift',
	'ko-fi': 'Ko-fi',
	liberapay: 'Liberapay',
	buymeacoffee: 'Buy Me a Coffee',
	paypal: 'PayPal'
};

/**
 * Names a funding link: its platform, when its type is a known one, else
 * the host it goes to.
 */
function fundingName(fund: ExtensionFunding): string {
	const platform = platforms[fund.type.toLowerCase()];

	if (platform) {
		return platform;
	}

	try {
		return new URL(fund.url).host.replace(/^www\./, '');
	} catch {
		return fund.url;
	}
}
</script>

<template>
	<template v-if="shown.length">
		<dt>Links</dt>
		<dd class="extension-links">
			<a v-for="link in shown" :key="link.kind" :href="link.url" target="_blank" rel="noopener">{{ names[link.kind] ?? link.kind }}<span class="visually-hidden"> (new tab)</span></a>
		</dd>
	</template>
	<template v-if="funding.length">
		<dt>Sponsor</dt>
		<dd class="extension-links">
			<a v-for="fund in funding" :key="fund.url" class="extension-links__fund" :href="fund.url" target="_blank" rel="noopener"><AdminIcon name="heart" />{{ fundingName(fund) }}<span class="visually-hidden"> (new tab)</span></a>
		</dd>
	</template>
</template>

<style scoped>
/* In a details screen's facts, which set the dt and dd. */
.extension-links {
	display: flex;
	flex-wrap: wrap;
	gap: var(--s-1) var(--s-3);
}

.extension-links__fund {
	display: inline-flex;
	align-items: center;
	gap: var(--s-1);
}
</style>
