<script setup lang="ts">
/**
 * An extension's Details (D-509), as every kind's details screen lists
 * them: its name, who made it, its version, license, and links, how it
 * was installed and where, its namespace, then the extensions on the
 * other side of its package links (D-431, D-440). A kind's own rows go in
 * the `kind` slot, after the namespace, and `after` ends the list.
 */

import AdminIcon from './AdminIcon.vue';
import ExtensionDependents from './ExtensionDependents.vue';
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
	<dl class="facts facts--grid">
		<dt>Name</dt>
		<dd class="mono">{{ extension.name }}</dd>
		<dt>{{ extension.authors.length > 1 ? 'Authors' : 'Author' }}</dt>
		<dd>
			<template v-if="extension.authors.length === 0">—</template>
			<span v-for="author in extension.authors" :key="author.name" class="facts__author">
				<a v-if="author.homepage" :href="author.homepage" target="_blank" rel="noopener">{{ author.name }}<span class="visually-hidden"> (new tab)</span></a>
				<template v-else>{{ author.name }}</template>
				<span v-if="author.role" class="facts__role">{{ author.role }}</span>
			</span>
		</dd>
		<dt>Version</dt>
		<dd :class="{ mono: extension.version }">{{ extension.version || '—' }}</dd>
		<dt>License</dt>
		<dd :class="{ mono: extension.licenses.length }"><LicenseLinks :parts="extension.licenses" /></dd>
		<ExtensionLinks :links="extension.links" :funding="extension.funding" />
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
		<dt>Namespace</dt>
		<dd class="mono">{{ namespace ?? extension.namespace }}</dd>
		<slot name="kind" />
		<ExtensionDependents :dependents="extension.requiredBy" />
		<ExtensionDependents :dependents="extension.conflictedBy" label="Conflicts with it" />
		<ExtensionDependents :dependents="extension.replacedBy" label="Replaced by" />
		<ExtensionDependents :dependents="extension.providedBy" label="Also provided by" />
		<slot name="after" />
	</dl>
</template>
