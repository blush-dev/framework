<script setup lang="ts">
/**
 * Linking an account and a profile (D-353, D-373, D-509), from either
 * side: a modal listing the ones free to link, each a person with an
 * avatar (dashed for a guest), a name, what identifies them, and a note
 * at the end; one is chosen, then linked. With none free, it says why,
 * and offers what the `empty` slot has, else only Close. The intro over
 * the list is the default slot.
 */

import { computed, useSlots } from 'vue';
import AdminModal from './AdminModal.vue';

export interface PickItem {
	key: string;
	initials: string;
	name: string;
	meta: string;
	aside?: string;
	guest?: boolean;
}

const props = defineProps<{
	open: boolean;
	// What's linked, capitalized for the title: "Profile", "Account".
	noun: string;
	// `null` while they load.
	items: PickItem[] | null;
	// Why none is free.
	none: string;
	busy: boolean;
	error?: string;
}>();

const pick = defineModel<string>('pick', { required: true });

const emit = defineEmits<{ close: []; confirm: [] }>();

const slots = useSlots();
const empty = computed(() => props.items !== null && props.items.length === 0);
const lower = computed(() => props.noun.toLowerCase());
</script>

<template>
	<AdminModal :open="open" :title="empty ? `No ${noun} to Link` : `Link ${/^[AEIOU]/.test(noun) ? 'an' : 'a'} ${noun}`" wide @close="emit('close')">
		<p v-if="items === null">Loading the {{ lower }}s…</p>
		<p v-else-if="empty">{{ none }}</p>
		<template v-else>
			<p><slot /></p>
			<div class="pick-list" role="group" :aria-label="`${noun}s`">
				<button v-for="item in items" :key="item.key" type="button" class="pick" :aria-pressed="pick === item.key" @click="pick = item.key">
					<span class="avatar" :class="{ 'avatar--guest': item.guest }" aria-hidden="true">{{ item.initials }}</span>
					<span class="pick__text">
						<span class="pick__name">{{ item.name }}</span>
						<span class="pick__meta">{{ item.meta }}</span>
					</span>
					<span v-if="item.aside" class="pick__aside">{{ item.aside }}</span>
				</button>
			</div>
		</template>
		<p v-if="error" class="field__error" role="alert">{{ error }}</p>
		<template #footer>
			<button type="button" class="button" @click="emit('close')">{{ empty && !slots.empty ? 'Close' : 'Cancel' }}</button>
			<slot v-if="empty" name="empty" />
			<button v-else type="button" class="button button--primary" :disabled="pick === '' || busy" @click="emit('confirm')">{{ busy ? 'Linking…' : `Link the ${lower}` }}</button>
		</template>
	</AdminModal>
</template>
