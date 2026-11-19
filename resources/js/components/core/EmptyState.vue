<script setup lang="ts">
import { computed } from 'vue';
import AppStatePanel from '@/components/core/AppStatePanel.vue';
import { resolveEmptyStateContent } from '@/lib/stateContent';
import type { IEmptyStateProps, IStateContent } from '@/types/state';

const props = withDefaults(defineProps<IEmptyStateProps>(), {
    title: undefined,
    description: undefined,
    query: undefined,
});

const content = computed<IStateContent>(() => {
    const fallback = resolveEmptyStateContent({ kind: props.kind, query: props.query });
    const customTitle = props.title?.trim() ?? '';
    const customDescription = props.description?.trim() ?? '';

    return {
        title: customTitle === '' ? fallback.title : customTitle,
        description: customDescription === '' ? fallback.description : customDescription,
    };
});
</script>

<template>
    <AppStatePanel
        illustration="empty"
        variant="inline"
        heading-tag="p"
        :title="content.title"
        :description="content.description"
    >
        <slot />
    </AppStatePanel>
</template>
