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
	<AdminModal :open="open" :title="empty ? t('pick_modal.title_none', 'No {noun} to Link', { noun }) : t('pick_modal.title', '{article, select, an {Link an {noun} } other {Link a {noun} } }', { article: /^[AEIOU]/.test(noun) ? 'an' : 'a', noun })" wide @close="emit('close')">
		<p v-if="items === null">{{ t('pick_modal.loading', 'Loading the {noun}s…', { noun: lower }) }}</p>
		<p v-else-if="empty">{{ none }}</p>
		<template v-else>
			<p><slot /></p>
			<div class="pick-list" role="group" :aria-label="t('pick_modal.list', '{noun}s', { noun })">
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
			<button v-else type="button" class="button button--primary" :disabled="pick === '' || busy" @click="emit('confirm')">{{ busy ? t('pick_modal.linking', 'Linking…') : t('pick_modal.link_the', 'Link the {noun}', { noun: lower }) }}</button>
		</template>
	</AdminModal>
</template>
