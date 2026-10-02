<script setup lang="ts">
/**
 * One field set (D-337): what it's called, the content types it adds its
 * fields to, and the fields. A set from `user/data/fields` is edited here
 * (`FieldSetEditor`); the rest are defined in code and shown read-only.
 */

import { computed, ref, watch } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import AdminIcon from '../components/AdminIcon.vue';
import FieldSetEditor from '../components/FieldSetEditor.vue';
import { ApiError, request, type FieldSetDetail } from '../api';
import { label } from '../fields';
import { loadFieldTypes, typeName } from '../field-types';
import { plural } from '../format';
import { screenTitle } from '../screen';
import { loadTypes } from '../types';

const route = useRoute();
const set   = ref<FieldSetDetail | null>(null);
const error = ref('');

loadTypes().catch(() => undefined);
loadFieldTypes().catch(() => undefined);

watch(() => route.params.name, async (name) => {
	if (name === undefined) {
		return;
	}

	set.value   = null;
	error.value = '';

	try {
		set.value = await request<FieldSetDetail>('GET', `/fields/sets/${encodeURIComponent(String(name))}`);
	} catch (caught) {
		error.value = caught instanceof ApiError ? caught.message : 'The field set couldn\'t be loaded.';
	}
}, { immediate: true });

watch(set, (value) => {
	screenTitle.value = value?.label ?? null;
});

const origin = computed(() => ({
	extension: 'a plugin',
	config: 'config/fields.php',
	data: 'user/data/fields'
})[set.value?.origin ?? 'config']);

// The slot it's in (D-347), when its kind offers more than one.
const slot = computed(() => {
	const slots = set.value?.kinds.find((item) => item.kind === set.value?.kind)?.slots ?? [];

	return slots.length > 1 ? slots.find((item) => item.name === set.value?.slot) ?? null : null;
});

// A type's name in the address of its screen, from a target's key.
function typeOf(key: string): string {
	return key.startsWith('type:') ? key.slice(5) : '';
}
</script>

<template>
	<header class="page-header">
		<RouterLink class="page-back" :to="{ name: 'fields' }"><AdminIcon name="chevron-left" />All field sets</RouterLink>
		<div class="page-header__text">
			<h1 tabindex="-1">{{ set?.label ?? 'Field Set' }}</h1>
			<p v-if="set" class="page-header__hint">
				Field set · <span class="mono">{{ set.name }}</span> · from {{ origin }}
			</p>
			<p v-if="set?.description" class="page-header__hint">{{ set.description }}</p>
		</div>
	</header>

	<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>

	<FieldSetEditor v-if="set?.editable" :set="set" :options="set.options" :kinds="set.kinds" @saved="set = $event" />

	<div v-else-if="set" class="set-detail">
		<section class="panel" aria-labelledby="targets-heading">
			<header class="panel__header">
				<h2 id="targets-heading">Added To</h2>
				<p class="panel__hint">Defined in {{ origin }}, so it's shown here</p>
			</header>
			<div class="panel__body">
				<ul v-if="set.targets.length" class="chips">
					<li v-for="target in set.targets" :key="target.key">
						<RouterLink v-if="target.found && typeOf(target.key)" :to="{ name: 'content-type', params: { name: typeOf(target.key) } }">{{ target.label }}</RouterLink>
						<span v-else-if="target.found" class="chips__item">{{ target.label }}</span>
						<span v-else class="chips__missing mono" title="The site doesn't have this">{{ target.key }}<span class="visually-hidden"> (the site doesn't have this)</span></span>
					</li>
				</ul>
				<p v-else class="field__help">Nowhere yet.</p>
				<p v-if="slot" class="field__help">In the <strong>{{ slot.label }}</strong> slot: {{ slot.description }}</p>
			</div>
		</section>

		<section class="panel" aria-labelledby="fields-heading">
			<header class="panel__header">
				<h2 id="fields-heading">Fields</h2>
				<p class="panel__hint">{{ plural(set.fields.length, 'field') }}</p>
			</header>
			<div v-if="set.fields.length" class="table-wrap">
				<table class="table" aria-labelledby="fields-heading">
					<thead>
						<tr>
							<th scope="col">Field</th>
							<th scope="col">Type</th>
							<th scope="col">Required</th>
						</tr>
					</thead>
					<tbody>
						<tr v-for="field in set.fields" :key="field.name">
							<th scope="row">
								<span class="entry-title">
									<span class="entry-title__text">{{ label(field) }}</span>
									<span class="entry-title__path">{{ field.name }}</span>
								</span>
							</th>
							<td>{{ typeName(field) }}</td>
							<td>{{ field.required ? 'Yes' : '' }}</td>
						</tr>
					</tbody>
				</table>
			</div>
			<p v-else class="panel__body field__help">None yet.</p>
		</section>
	</div>

	<div v-else-if="!error" class="set-detail" aria-hidden="true">
		<div class="panel"><div class="panel__body"><span class="skeleton skeleton--heading" /><span class="skeleton" /></div></div>
		<div class="panel"><div class="panel__body"><span class="skeleton skeleton--label" /><span class="skeleton" /><span class="skeleton" /></div></div>
	</div>
</template>

<style scoped>
/* Widths as classes: the admin's CSP blocks inline style attributes. */
.skeleton--heading {
	width: 40%;
}

.skeleton--label {
	width: 30%;
}

.set-detail {
	display: grid;
	gap: 16px;
}

.chips {
	display: flex;
	flex-wrap: wrap;
	gap: 6px;
	margin: 0;
	padding: 0;
	list-style: none;
}

.chips a,
.chips__item,
.chips__missing {
	display: inline-block;
	padding: 2px 10px;
	border: 1px solid var(--border);
	border-radius: 99px;
	background: var(--surface-2);
	color: var(--fg-2);
	font-size: var(--text-sm);
	text-decoration: none;
}

.chips a:hover {
	border-color: var(--border-strong);
	color: var(--fg);
}

.chips__missing {
	border-style: dashed;
	color: var(--fg-3);
}
</style>
