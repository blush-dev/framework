<script setup lang="ts">
/**
 * What an extension suggests (D-434), as Composer's `suggest` has it, the
 * same for plugins, themes, and icon packs: each package, with why it's
 * suggested beneath it. A suggestion names no versions. An installed
 * extension is linked to its details, and it and a loaded PHP extension
 * are checked. Anything else (a library, or an extension that isn't
 * installed) is only named, since nothing is checked or enforced.
 */

import AdminIcon from './AdminIcon.vue';
import type { ExtensionSuggestion } from '../api';
import { extensionRoute } from '../extensions';

defineProps<{ suggestions: ExtensionSuggestion[] }>();

// The package as Requires names it.
function text(suggestion: ExtensionSuggestion): string {
	return suggestion.extension?.label
		?? (suggestion.name.startsWith('ext-') ? `the PHP extension ${suggestion.name.slice(4)}` : suggestion.name);
}

function status(suggestion: ExtensionSuggestion): string {
	if (suggestion.extension) {
		return 'Installed:';
	}

	if (suggestion.loaded !== null) {
		return suggestion.loaded ? 'Loaded:' : 'Not loaded:';
	}

	return 'Suggested:';
}
</script>

<template>
	<ul class="suggestions">
		<li v-for="suggestion in suggestions" :key="suggestion.name">
			<AdminIcon :name="suggestion.extension || suggestion.loaded ? 'circle-check' : 'lightbulb'" :class="{ 'is-met': suggestion.extension || suggestion.loaded }" />
			<span class="visually-hidden">{{ status(suggestion) }}</span>
			<RouterLink v-if="suggestion.extension" :to="extensionRoute(suggestion.extension.kind, suggestion.extension.name)">{{ text(suggestion) }}</RouterLink>
			<span v-else :class="{ mono: suggestion.loaded === null }">{{ text(suggestion) }}</span>
			<span v-if="suggestion.loaded === false" class="suggestions__note">isn't loaded</span>
			<span v-if="suggestion.reason" class="suggestions__reason">{{ suggestion.reason }}</span>
		</li>
	</ul>
</template>

<style scoped>
.suggestions {
	display: grid;
	gap: var(--s-3);
	margin: 0;
	padding: 0;
	font-size: var(--text-sm);
	list-style: none;
}

.suggestions li {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: var(--s-1) var(--s-2);
}

.suggestions .icon {
	flex: none;
	width: 15px;
	height: 15px;
	color: var(--fg-3);
}

.suggestions .is-met {
	color: var(--good);
}

.suggestions__note {
	color: var(--fg-3);
}

/* Why, on its own line, under the name rather than the icon. */
.suggestions__reason {
	flex-basis: 100%;
	padding-left: calc(15px + var(--s-2));
	color: var(--fg-2);
}
</style>
