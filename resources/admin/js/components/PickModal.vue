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
import AdminAvatar from './AdminAvatar.vue';
import AdminModal from './AdminModal.vue';

export interface PickItem {
	key: string;
	name: string;
	// The picture's URL, or `null` for initials.
	image?: string | null;
	meta: string;
	aside?: string;
	guest?: boolean;
}

/**
 * What the modal says, each a whole sentence from the screen, so no
 * noun is fitted into words around it.
 */
export interface PickModalWords {
	// "Link a Profile".
	title: string;
	// "No Profile to Link".
	titleNone: string;
	// "Loading the profiles…".
	loading: string;
	// "Profiles", labeling the list.
	list: string;
	// "Link the profile".
	confirm: string;
}

const props = defineProps<{
	open: boolean;
	words: PickModalWords;
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
</script>

<template>
	<AdminModal :open="open" :title="empty ? words.titleNone : words.title" wide @close="emit('close')">
		<p v-if="items === null">{{ words.loading }}</p>
		<p v-else-if="empty">{{ none }}</p>
		<template v-else>
			<p><slot /></p>
			<div class="pick-list" role="group" :aria-label="words.list">
				<button v-for="item in items" :key="item.key" type="button" class="pick" :aria-pressed="pick === item.key" @click="pick = item.key">
					<AdminAvatar :class="{ 'avatar--guest': item.guest }" :name="item.name" :image="item.image" />
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
			<button type="button" class="button" @click="emit('close')">{{ empty && !slots.empty ? t('common.close', 'Close') : t('common.cancel', 'Cancel') }}</button>
			<slot v-if="empty" name="empty" />
			<button v-else type="button" class="button button--primary" :disabled="pick === '' || busy" @click="emit('confirm')">{{ busy ? t('pick_modal.linking', 'Linking…') : words.confirm }}</button>
		</template>
	</AdminModal>
</template>
