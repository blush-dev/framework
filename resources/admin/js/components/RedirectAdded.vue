<script setup lang="ts">
/**
 * How a redirect was added, and by which account (D-686): "Renamed by
 * Jane", the account's name linked to its screen where `linked` and the
 * viewer may open it; no You tag, since you know your own name. A row
 * without an account says what added it alone ("When the entry was
 * renamed"); one whose account is gone says so.
 */

import { RouterLink } from 'vue-router';
import { addedVerb, type RedirectRow } from '../redirects';
import { can } from '../session';

const props = defineProps<{
	row: RedirectRow;
	linked?: boolean;
}>();

const ALONE: Record<string, string> = {
	rename: 'When the entry was renamed',
	move: 'When the entry moved',
	import: 'Imported'
};

const opens = (): boolean => props.linked === true && props.row.by?.username !== null && (props.row.by?.you === true || can('accounts.view'));
</script>

<template>
	<span v-if="row.by">
		{{ addedVerb(row) }} by
		<template v-if="row.by.name === null">a removed account</template>
		<RouterLink v-else-if="opens()" class="lnk" :to="{ name: 'account', params: { username: row.by.username } }">{{ row.by.name }}</RouterLink>
		<template v-else>{{ row.by.name }}</template>
	</span>
	<span v-else-if="row.via">{{ ALONE[row.via] }}</span>
</template>
