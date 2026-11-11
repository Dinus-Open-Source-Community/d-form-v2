<script setup lang="ts">
import { PenLine, Settings } from 'lucide-vue-next';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { FORM_VISIBILITY_OPTIONS } from '@/components/modules/builder/formBuilderPalette';
import { toggleVisibilitySelection } from '@/lib/formVisibility';
import FormSettingsPanel from './FormSettingsPanel.vue';
import type { IFormRegistrationMetadata, IFormSiblingOption } from '@/types/form';

/**
 * Shell tab halaman builder form yang belum punya daftar jawaban: `editor` (kanvas)
 * dan `settings` (pengaturan form). Aksi simpan/pratinjau pemanggil masuk lewat slot
 * `actions` agar tetap terjangkau dari kedua tab.
 */
const activeTab = defineModel<'editor' | 'settings'>('activeTab', { required: true });
const closedAt = defineModel<string>('closedAt', { required: true });
const visibleFor = defineModel<string[]>('visibleFor', { required: true });
const formMetadata = defineModel<IFormRegistrationMetadata>('formMetadata', { required: true });

const props = withDefaults(
    defineProps<{
        idPrefix: string;
        fieldErrors?: Partial<Record<'closed_at' | 'visible_for', string>>;
        siblingForms?: IFormSiblingOption[];
    }>(),
    {
        fieldErrors: () => ({}),
        siblingForms: () => [],
    }
);

/** Panel pengaturan hanya punya model `visibleFor`; toggle memakai helper bersama. */
function toggleVisibility(value: string, checked: boolean): void {
    visibleFor.value = toggleVisibilitySelection(visibleFor.value, value, checked);
}
</script>

<template>
    <Tabs
        v-model="activeTab"
        class="flex min-h-0 w-full flex-1 flex-col gap-4"
        :unmount-on-hide="false"
        aria-label="Konten form"
    >
        <div class="border-border/60 flex flex-wrap items-center justify-between gap-3 border-b pb-3">
            <TabsList class="bg-muted/40 h-auto min-h-10 flex-wrap gap-1 rounded-xl p-1">
                <TabsTrigger
                    value="editor"
                    class="data-[state=active]:bg-card gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium data-[state=active]:shadow-sm"
                >
                    <PenLine class="size-4 shrink-0" aria-hidden="true" />
                    Editor
                </TabsTrigger>
                <TabsTrigger
                    value="settings"
                    class="data-[state=active]:bg-card gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium data-[state=active]:shadow-sm"
                >
                    <Settings class="size-4 shrink-0" aria-hidden="true" />
                    Pengaturan Form
                </TabsTrigger>
            </TabsList>

            <div v-if="$slots.actions" class="flex shrink-0 items-center gap-2">
                <slot name="actions" />
            </div>
        </div>

        <TabsContent value="editor" class="mt-0 min-h-0 flex-1">
            <slot />
        </TabsContent>

        <TabsContent value="settings" class="mt-0 min-h-0 flex-1 overflow-y-auto">
            <div class="mx-auto w-full max-w-2xl">
                <FormSettingsPanel
                    v-model:closed-at="closedAt"
                    v-model:visible-for="visibleFor"
                    v-model:form-metadata="formMetadata"
                    :id-prefix="props.idPrefix"
                    :field-errors="props.fieldErrors"
                    :visibility-options="FORM_VISIBILITY_OPTIONS"
                    :sibling-forms="props.siblingForms"
                    @toggle-visibility="toggleVisibility"
                />
            </div>
        </TabsContent>
    </Tabs>
</template>
