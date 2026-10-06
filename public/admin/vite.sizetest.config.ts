import base from './vite.config';
import { mergeConfig } from 'vite';
export default mergeConfig(base, { define: { __VUE_OPTIONS_API__: 'false', __VUE_PROD_DEVTOOLS__: 'false', __VUE_PROD_HYDRATION_MISMATCH_DETAILS__: 'false' }, build: { outDir: '/private/tmp/claude-501/-Applications-XAMPP-xamppfiles-htdocs-blush-framework/7732f5f6-9912-461b-b77c-114b94c60651/scratchpad/sizebuild2', emptyOutDir: true, cssTarget: ['chrome120', 'firefox121', 'safari17.2'], target: ['chrome120', 'firefox121', 'safari17.2'] } });
