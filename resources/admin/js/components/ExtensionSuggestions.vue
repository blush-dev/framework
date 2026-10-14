<script setup lang="ts">
/**
 * What an extension suggests (D-434), as Composer's `suggest` has it, the
 * same for plugins, themes, and icon packs, as rows of its Dependencies
 * panel (D-565): each package, with why it's suggested beneath it. A
 * suggestion names no versions. An installed extension is linked to its
 * details, with its name beside it, and it and a loaded PHP extension
 * are checked. Anything else (a library, or an extension that isn't
 * installed) is only named, with a bulb, since nothing is checked or
 * enforced.
 */

import AdminIcon from './AdminIcon.vue';
import type { ExtensionSuggestion } from '../api';
import { extensionRoute } from '../extensions';
import { t } from '../messages';

defineProps<{ suggestions: ExtensionSuggestion[] }>();

function status(suggestion: ExtensionSuggestion): string {
	if (suggestion.extension) {
		return t('extension_suggestions.installed', 'Installed:');
	}

	if (suggestion.loaded !== null) {
		return suggestion.loaded ? t('extension_suggestions.loaded', 'Loaded:') : t('extension_suggestions.not_loaded', 'Not loaded:');
	}

	return t('extension_suggestions.suggested', 'Suggested:');
}
</script>

<template>
	<ul class="dependencies">
		<li v-for="suggestion in suggestions" :key="suggestion.name">
			<AdminIcon :name="suggestion.extension || suggestion.loaded ? 'circle-check' : 'lightbulb'" :class="{ 'is-met': suggestion.extension || suggestion.loaded }" />
			<div>
				<div class="dependencies__ref">
					<span class="visually-hidden">{{ status(suggestion) }}</span>
					<template v-if="suggestion.extension">
						<RouterLink :to="extensionRoute(suggestion.extension.kind, suggestion.extension.name)">{{ suggestion.extension.label }}</RouterLink>
						<span class="mono dependencies__name">{{ suggestion.name }}</span>
					</template>
					<span v-else-if="suggestion.name.startsWith('ext-')">{{ t('extension_suggestions.php_extension', 'PHP Extension: {name}', { name: suggestion.name.slice(4) }) }}</span>
					<span v-else class="mono">{{ suggestion.name }}</span>
					<span v-if="suggestion.loaded === false" class="dependencies__note">{{ t('extension_suggestions.not_loaded_note', "isn't loaded") }}</span>
				</div>
				<p v-if="suggestion.reason" class="dependencies__why">{{ suggestion.reason }}</p>
			</div>
		</li>
	</ul>
</template>
