<script setup lang="ts">
/**
 * What an extension suggests (D-434), as Composer's `suggest` has it, the
 * same for plugins, themes, and icon packs, drawn as the Requires list
 * is: each package with the version the site has beside it, and why as
 * its note. An installed extension is linked to its details, and it and
 * a loaded PHP extension are checked. Anything else (a library, or an
 * extension that isn't installed) is only named, since Blush can't say
 * whether Composer has it. Nothing is enforced.
 */

import AdminIcon from './AdminIcon.vue';
import type { ExtensionSuggestion } from '../api';
import { extensionRoute } from '../extensions';

defineProps<{ suggestions: ExtensionSuggestion[] }>();

// The package as Requires names it, with the version the site has.
function text(suggestion: ExtensionSuggestion): string {
	const name = suggestion.extension?.label
		?? (suggestion.name.startsWith('ext-') ? `the PHP extension ${suggestion.name.slice(4)}` : suggestion.name);

	return suggestion.version ? `${name} ${suggestion.version}` : name;
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
			<span v-if="suggestion.reason" class="suggestions__note">{{ suggestion.reason }}</span>
		</li>
	</ul>
</template>

<style scoped>
.suggestions {
	display: grid;
	gap: var(--s-2);
	margin: 0;
	padding: 0;
	font-size: var(--text-sm);
	list-style: none;
}

.suggestions li {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: var(--s-2);
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
</style>
