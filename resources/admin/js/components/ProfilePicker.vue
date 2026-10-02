<script setup lang="ts">
/**
 * Picks the profile an account is linked to (D-356), in the admin's own
 * select (`AdminSelect`), not a typed slug. Every profile is listed by
 * name, with its status when it isn't live yet; one already linked to
 * another account is shown but can't be chosen, since a profile belongs
 * to one account. `''` is none, and, when it may `create`, `NEW_PROFILE`
 * asks for a new one (New Account, D-369).
 */

import { computed, ref } from 'vue';
import AdminSelect, { type SelectOption } from './AdminSelect.vue';
import { loadLinkable, NEW_PROFILE, type LinkableProfile } from '../people';

const props = defineProps<{
	id: string;
	// The account being linked, whose own profile stays choosable.
	username?: string;
	// What none is called ("None", "Choose a profile…").
	none?: string;
	describedBy?: string;
	invalid?: boolean;
	// Whether to offer "Create a new profile…".
	create?: boolean;
}>();

const emit = defineEmits<{
	loaded: [profiles: LinkableProfile[]];
}>();

const model    = defineModel<string>({ required: true });
const profiles = ref<LinkableProfile[] | null>(null);
const failed   = ref(false);

loadLinkable().then((list) => {
	profiles.value = list;
	emit('loaded', list);
}, () => {
	failed.value = true;
});

function labelOf(profile: LinkableProfile): string {
	const state = profile.status === null ? ' (no profile file yet)' : (profile.status === 'published' ? '' : ` (${profile.status})`);
	const owner = profile.account && profile.account.username !== props.username ? ` · linked to ${profile.account.displayName}` : '';

	return `${profile.title}${state}${owner}`;
}

const options = computed<SelectOption[]>(() => [
	{ value: '', label: failed.value ? 'The profiles couldn\'t be loaded' : (profiles.value === null ? 'Loading the profiles…' : (props.none ?? 'None')) },
	...(props.create ? [{ value: NEW_PROFILE, label: 'Create a new profile…' }] : []),
	...(profiles.value ?? []).map((profile) => ({
		value: profile.slug,
		label: labelOf(profile),
		disabled: profile.account !== null && profile.account.username !== props.username
	}))
]);
</script>

<template>
	<AdminSelect :id="id" v-model="model" :options="options" :described-by="describedBy" :invalid="invalid" :disabled="profiles === null" />
</template>
