<script setup lang="ts">
/**
 * New Field Set (D-337): its own screen, as every New is (admin.md §8),
 * with the set's editor (`FieldSetEditor`) empty. Creating it writes
 * `user/data/fields/{key}.yaml` and moves to the set's screen.
 */

import { ref } from 'vue';
import { RouterLink } from 'vue-router';
import AdminIcon from '../components/AdminIcon.vue';
import FieldSetEditor from '../components/FieldSetEditor.vue';
import { errorMessage, request, type FieldSetList } from '../api';
import { loadTypes } from '../types';

const list  = ref<FieldSetList | null>(null);
const error = ref('');

loadTypes().catch(() => undefined);

request<FieldSetList>('GET', '/fields/sets').then((answer) => {
	list.value  = answer;
	error.value = answer.create ? '' : 'Field sets can\'t be created here: FieldConfig "dataSets" is off, so user/data/fields isn\'t read.';
}, (caught: unknown) => {
	error.value = errorMessage(caught, 'The content types couldn\'t be loaded.');
});
</script>

<template>
	<header class="page-header">
		<RouterLink class="page-back" :to="{ name: 'fields' }"><AdminIcon name="chevron-left" />All field sets</RouterLink>
		<div class="page-header__text">
			<h1 tabindex="-1">New Field Set</h1>
			<p class="page-header__hint">Fields to add to one or more content types, beside their own.</p>
		</div>
	</header>

	<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>

	<FieldSetEditor v-if="list?.create" :set="null" :options="list.targets" :kinds="list.kinds" />
</template>
