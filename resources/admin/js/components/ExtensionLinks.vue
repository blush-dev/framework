<script setup lang="ts">
/**
 * An extension's homepage, support, and funding links (D-428), as
 * sections of its Details panel (D-565, the extensions sketch's):
 * **Links**, each named for what it is, in order, with its kind's glyph,
 * and **Funding**, each a button named for where it goes, with a heart.
 * Neither is drawn when it has nothing. `support.email` isn't shown (the
 * author's call), though the API has it.
 */

import { computed } from 'vue';
import AdminIcon from './AdminIcon.vue';
import type { ExtensionFunding, ExtensionLink } from '../api';
import type { IconName } from '../icons';

const props = defineProps<{ links: ExtensionLink[]; funding: ExtensionFunding[] }>();

const shown = computed(() => props.links.filter((link) => link.kind !== 'email'));

const kinds: Record<string, [string, IconName]> = {
	homepage: ['Homepage', 'globe'],
	docs: ['Documentation', 'book-open'],
	source: ['Source', 'code'],
	issues: ['Issues', 'bug'],
	forum: ['Forum', 'message-square'],
	chat: ['Chat', 'message-square'],
	wiki: ['Wiki', 'book-open'],
	irc: ['IRC', 'message-square'],
	rss: ['RSS', 'rss'],
	security: ['Security', 'shield']
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
	<section v-if="shown.length" class="panel__section" aria-labelledby="links-heading">
		<div class="panel__section-head"><h3 id="links-heading" class="eyebrow">Links</h3></div>
		<ul class="extension-links">
			<li v-for="link in shown" :key="link.kind">
				<a :href="link.url" target="_blank" rel="noopener"><AdminIcon :name="kinds[link.kind]?.[1] ?? 'external-link'" /><span>{{ kinds[link.kind]?.[0] ?? link.kind }}</span><span class="visually-hidden"> (new tab)</span></a>
			</li>
		</ul>
	</section>
	<section v-if="funding.length" class="panel__section" aria-labelledby="funding-heading">
		<div class="panel__section-head"><h3 id="funding-heading" class="eyebrow">Funding</h3><span>The people who maintain it</span></div>
		<div class="extension-links__fund">
			<a v-for="fund in funding" :key="fund.url" class="button button--small" :href="fund.url" target="_blank" rel="noopener"><AdminIcon name="heart" />{{ fundingName(fund) }}<span class="visually-hidden"> (new tab)</span></a>
		</div>
	</section>
</template>
