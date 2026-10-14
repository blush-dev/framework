<script setup lang="ts">
/**
 * The signed-in account's language (D-831), shown on Your Account: the
 * site's, or one the admin has text in, each named in its own
 * language. It belongs to the account, so it follows it to any device.
 * The admin's text is printed by the page, so a change loads it again.
 */

import { ref } from 'vue';
import { useAction } from '../action';
import { request, type Preferences } from '../api';
import { config } from '../config';
import { t } from '../messages';
import AdminSelect, { type SelectOption } from './AdminSelect.vue';

// The site's language, named in the admin's.
const site = new Intl.DisplayNames([config.dates.locale], { type: 'language' }).of(config.dates.locale) ?? config.dates.locale;

const options: SelectOption[] = [
	{ value: '', label: t('account_language.site', 'The site\'s ({site})', { site }) },
	...config.adminLanguages.map((language) => ({ value: language.code, label: language.label, lang: language.code.replace('_', '-') }))
];

const chosen = ref(config.language ?? '');

const { busy: saving, error, run } = useAction();

async function save(): Promise<void> {
	await run(t('account_language.failed', 'Your language couldn\'t be saved.'), async () => {
		await request<{ preferences: Preferences }>('PATCH', '/preferences', { language: chosen.value === '' ? null : chosen.value });
		window.location.reload();
	}, () => {
		chosen.value = config.language ?? '';
	});
}
</script>

<template>
	<section class="panel" aria-labelledby="language-heading">
		<header class="panel__header">
			<h2 id="language-heading">{{ t('account_language.heading', 'Language') }}</h2>
			<p class="panel__hint">{{ t('account_language.hint', 'Just for you, on any device') }}</p>
		</header>
		<div class="panel__body form-stack">
			<div class="field">
				<label for="account-language">{{ t('account_language.label', "The admin's language") }}</label>
				<AdminSelect id="account-language" v-model="chosen" :options="options" :disabled="saving" described-by="account-language-help" @update:model-value="save" />
				<p id="account-language-help" class="field__help">{{ t('account_language.help', "The languages the admin has text in. Content is written in the site's languages, whatever this is.") }}</p>
			</div>
			<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>
		</div>
	</section>
</template>
