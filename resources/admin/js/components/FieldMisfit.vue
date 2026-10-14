<script setup lang="ts">
/**
 * The line under a field whose value, as last saved, doesn't fit it
 * (D-779): what's wrong, in the linter's words after the field's label,
 * that the site leaves the value out, and Site Health, which lists it,
 * for an account that may see it. Only a file edited by hand has one.
 */
import { RouterLink } from 'vue-router';
import AdminIcon from './AdminIcon.vue';
import MessageText from './MessageText.vue';
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
			<MessageText v-if="can('site.health')" :text="t('field_misfit.left_out_listed', '{label} {message} The site leaves it out until it\'s fixed, and {health} lists it.', { label, message })">
				<template #health><RouterLink :to="{ name: 'health-check', params: { area: 'content', check: 'files' } }">{{ t('field_misfit.site_health', 'Site Health') }}</RouterLink></template>
			</MessageText>
			<template v-else>{{ t('field_misfit.left_out', '{label} {message} The site leaves it out until it\'s fixed.', { label, message }) }}</template>
		</span>
	</p>
</template>
