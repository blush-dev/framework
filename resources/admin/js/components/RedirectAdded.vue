<script setup lang="ts">
/**
 * How a redirect was added, and by which account (D-686): "Renamed by
 * Jane", the account's name linked to its screen where `linked` and the
 * viewer may open it; no You tag, since you know your own name. A row
 * without an account says what added it alone ("When the entry was
 * renamed"); one whose account is gone says so.
 */

import { RouterLink } from 'vue-router';
import { t } from '../messages';
import type { RedirectRow } from '../redirects';
import { can } from '../session';
import MessageText from './MessageText.vue';

const props = defineProps<{
	row: RedirectRow;
	linked?: boolean;
}>();

const ALONE: Record<string, string> = {
	rename: t('redirect_added.when_renamed', 'When the entry was renamed'),
	move: t('redirect_added.when_moved', 'When the entry moved'),
	import: t('redirect_added.imported', 'Imported')
};

const opens = (): boolean => props.linked === true && props.row.by?.username !== null && (props.row.by?.you === true || can('accounts.view'));
</script>

<template>
	<span v-if="row.by">
		<MessageText :text="t('redirect_added.by', '{via, select, rename {Renamed by {account} } move {Moved by {account} } import {Imported by {account} } other {Added by {account} } }', { via: row.via ?? 'other' })">
			<template #account>
				<template v-if="row.by.name === null">{{ t('redirect_added.removed_account', 'a removed account') }}</template>
				<RouterLink v-else-if="opens()" class="lnk" :to="{ name: 'account', params: { username: row.by.username } }">{{ row.by.name }}</RouterLink>
				<template v-else>{{ row.by.name }}</template>
			</template>
		</MessageText>
	</span>
	<span v-else-if="row.via">{{ ALONE[row.via] }}</span>
</template>
