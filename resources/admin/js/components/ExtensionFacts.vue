<script setup lang="ts">
/**
 * An extension's Details panel (D-509; drawn as the extensions sketch's
 * in D-565): its name, version, who made it, its license, its namespace,
 * then a kind's own rows (the `kind` slot), how it was installed and
 * where; then its Links and Funding as sections of the panel
 * (`ExtensionLinks`). What it needs and what needs it are in its
 * Dependencies panel (`ExtensionDependencies`).
 */

import AdminIcon from './AdminIcon.vue';
import ExtensionLinks from './ExtensionLinks.vue';
import LicenseLinks from './LicenseLinks.vue';
import type { ExtensionSummary } from '../api';
import { copyText } from '../toast';

defineProps<{
	extension: ExtensionSummary;
	// "Composer", "A folder in extensions/", or "Blush".
	installedBy: string;
	// Where it's installed, from the site's root; `null` for one that
	// ships with Blush.
	folder: string | null;
	// The namespace as it's used, when that isn't the name alone (an icon
	// pack's `weather/`).
	namespace?: string;
}>();
</script>

<template>
	<section class="panel" aria-labelledby="details-heading">
		<header class="panel__header"><h2 id="details-heading">Details</h2></header>
		<div class="panel__section">
			<dl class="facts facts--grid">
				<dt>Name</dt>
				<dd class="mono">{{ extension.name }}</dd>
				<dt>Version</dt>
				<dd :class="{ mono: extension.version }">{{ extension.version || '—' }}</dd>
				<dt>{{ extension.authors.length > 1 ? 'Authors' : 'Author' }}</dt>
				<dd>
					<template v-if="extension.authors.length === 0">—</template>
					<span v-for="author in extension.authors" :key="author.name" class="facts__author">
						<a v-if="author.homepage" :href="author.homepage" target="_blank" rel="noopener">{{ author.name }}<span class="visually-hidden"> (new tab)</span></a>
						<template v-else>{{ author.name }}</template>
						<span v-if="author.role" class="facts__role">{{ author.role }}</span>
					</span>
				</dd>
				<dt>License</dt>
				<dd :class="{ mono: extension.licenses.length }"><LicenseLinks :parts="extension.licenses" /></dd>
				<dt>Namespace</dt>
				<dd class="mono">{{ namespace ?? extension.namespace }}</dd>
				<slot name="kind" />
				<dt>Installed by</dt>
				<dd>{{ installedBy }}</dd>
				<dt>Folder</dt>
				<dd>
					<template v-if="folder">
						<span class="mono">{{ folder }}</span>
						<button type="button" class="button button--ghost button--small button--icon facts__copy" :aria-label="`Copy ${folder}`" @click="copyText(folder, 'the folder path')"><AdminIcon name="copy" /></button>
					</template>
					<template v-else>Ships with Blush</template>
				</dd>
			</dl>
		</div>
		<ExtensionLinks :links="extension.links" :funding="extension.funding" />
	</section>
</template>
