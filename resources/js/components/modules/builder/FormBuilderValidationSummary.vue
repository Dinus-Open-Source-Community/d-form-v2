<script setup lang="ts">
import type { FormBuilderValidationIssue } from '@/utils/composables/useFormBuilderWorkspace'

defineProps<{
    issues: FormBuilderValidationIssue[]
    /** `comfortable`: mobile padding; `compact`: desktop inspector */
    density?: 'comfortable' | 'compact'
}>()
</script>

<template>
    <div
        :class="
            density === 'compact'
                ? 'border-warning/20 bg-warning/8 rounded-xl border p-3 text-[11px]'
                : 'border-warning/20 bg-warning/8 rounded-xl border p-4 text-xs'
        "
    >
        <p class="text-warning flex items-center gap-1.5 font-semibold">
            <span class="size-1.5 rounded-full bg-current" />
            <template v-if="density === 'compact'">{{ issues.length }} required left</template>
            <template v-else>
                {{ issues.length }} required
                {{ issues.length === 1 ? 'item' : 'items' }} left
            </template>
        </p>
        <ul
            :class="
                density === 'compact'
                    ? 'text-muted-foreground mt-1.5 space-y-0.5 pl-4'
                    : 'text-muted-foreground mt-2 space-y-1 pl-4'
            "
        >
            <li v-for="issue in issues" :key="issue.key" class="list-disc">
                {{ issue.label }}
            </li>
        </ul>
    </div>
</template>
