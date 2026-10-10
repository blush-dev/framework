<script setup lang="ts">
/**
 * A content type's icon: the site icon its `icon` setting names
 * (`SiteIcon`), or else its kind's admin icon. Decoration, like
 * `AdminIcon`.
 */

import type { ContentTypeSummary } from '../api';
import { typeIcon } from '../types';
import SiteIcon from './SiteIcon.vue';

defineProps<{ type: Pick<ContentTypeSummary, 'kind' | 'icon'> }>();
</script>

<template>
	<SiteIcon :name="type.icon" :fallback="typeIcon(type)" />
</template>
