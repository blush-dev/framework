<script setup lang="ts">
/**
 * A Redirects screen message (D-686): its parts, plain, a path in code
 * type, or a title in bold, and the one fix it offers, a link button
 * after the words (`fix`).
 */

import type { RedirectMessage } from '../redirects';

defineProps<{ message: RedirectMessage }>();

defineEmits<{ fix: [fix: NonNullable<RedirectMessage['fix']>] }>();
</script>

<template>
	<span><template v-for="(part, index) in message.parts" :key="index"><template v-if="typeof part === 'string'">{{ part }}</template><code v-else-if="'code' in part" class="mono">{{ part.code }}</code><strong v-else>{{ part.strong }}</strong></template><template v-if="message.fix">{{ ' ' }}<button type="button" class="lnk" @click="$emit('fix', message.fix)">{{ message.fix.label }}</button></template></span>
</template>
