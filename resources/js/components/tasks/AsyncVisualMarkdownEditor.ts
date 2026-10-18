import { useI18n } from '@/composables/useI18n';
import { defineAsyncComponent, defineComponent, h } from 'vue';

const EditorLoading = defineComponent({
    setup() {
        const { t } = useI18n();
        return () =>
            h(
                'div',
                {
                    class: 'flex min-h-36 items-center rounded-md border border-input px-3 text-sm text-muted-foreground',
                    role: 'status',
                    'aria-live': 'polite',
                },
                t('stage3.loading'),
            );
    },
});

// Crepe, Milkdown and their styles stay out of the initial task-details bundle.
// Vue loads them only when the edit form or discussion composer is opened.
export const AsyncVisualMarkdownEditor = defineAsyncComponent({
    loader: () => import('./VisualMarkdownEditor.vue'),
    loadingComponent: EditorLoading,
    delay: 0,
});
