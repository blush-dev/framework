<script setup lang="ts">
/**
 * The field sets added to a content type (D-337), as a panel on its
 * screen: each links to its set, with how many fields it adds. Which
 * types a set is added to is chosen on the set's screen, so this only
 * shows them.
 */

import { RouterLink } from 'vue-router';
import type { ContentTypeDetail } from '../api';
import { plural } from '../format';

defineProps<{ type: ContentTypeDetail }>();
</script>

<template>
	<section class="panel" aria-labelledby="sets-heading">
		<header class="panel__header">
			<h2 id="sets-heading">Field Sets</h2>
			<p class="panel__hint">Fields added after its own</p>
		</header>
		<div class="panel__body">
			<ul v-if="type.sets.length" class="type-sets">
				<li v-for="set in type.sets" :key="set.name">
					<RouterLink :to="{ name: 'field-set', params: { name: set.name } }">{{ set.label }}</RouterLink>
					<span class="field__help">{{ plural(set.fields, 'field') }}</span>
				</li>
			</ul>
			<p v-else class="field__help">None. A set chooses its types on its screen, under <RouterLink :to="{ name: 'fields' }">Fields</RouterLink>.</p>
		</div>
	</section>
</template>

<style scoped>
.type-sets {
	display: grid;
	gap: 6px;
	margin: 0;
	padding: 0;
	list-style: none;
}

.type-sets li {
	display: flex;
	justify-content: space-between;
	gap: 12px;
}
</style>
