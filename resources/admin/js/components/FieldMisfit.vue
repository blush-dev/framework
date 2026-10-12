<script setup lang="ts">
/**
 * The line under a field whose value, as last saved, doesn't fit it
 * (D-779): what's wrong, in the linter's words after the field's label,
 * that the site leaves the value out, and Site Health, which lists it,
 * for an account that may see it. Only a file edited by hand has one.
 */
import { RouterLink } from 'vue-router';
import AdminIcon from './AdminIcon.vue';
import { can } from '../session';

defineProps<{
	id: string;
	label: string;
	message: string;
}>();
</script>

<template>
	<p :id="id" class="field__warn">
		<AdminIcon name="triangle-alert" />
		<span>
			{{ label }} {{ message }} The site leaves it out until it's fixed<template v-if="can('site.health')">, and <RouterLink :to="{ name: 'health-check', params: { area: 'content', check: 'files' } }">Site Health</RouterLink> lists it</template>.
		</span>
	</p>
</template>
