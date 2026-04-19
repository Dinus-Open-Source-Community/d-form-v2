<script setup lang="ts">
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import PageHeader from '@/components/modules/dashboard/PageHeader.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import TipTapEditor from '@/components/modules/dashboard/events/TipTapEditor.vue';
import ComboboxTagInput from '@/components/modules/dashboard/events/ComboboxTagInput.vue';
import { Upload, X, Save, Send } from 'lucide-vue-next';
import {
    update as updateEvent,
    show as showEvent,
} from '@/actions/App/Http/Controllers/Dashboard/Events/EventController';
import { dummyEvents } from '@/lib/dummyData';
import { showEventValidationToast } from '@/lib/eventValidationToast';
import { cn } from '@/lib/utils';

defineOptions({ layout: DashboardLayout });

const datetimeInputClass = cn(
    'border-input h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-xs shadow-xs transition-[color,box-shadow] outline-none',
    'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
    'dark:bg-input/30'
);

const props = defineProps<{
    event?: IEvent;
    options?: { categories: { value: string; label: string }[]; sessions: { value: string; label: string }[] };
}>();

const defaultSessions = [
    { value: 'general', label: 'General' },
    { value: 'programming', label: 'Programming' },
    { value: 'network', label: 'Networking' },
    { value: 'media_creative', label: 'Media Creative' },
    { value: 'data', label: 'Data' },
];

const defaultCategories = [
    { value: 'rkt', label: 'RKT' },
    { value: 'non-rkt', label: 'NON RKT' },
    { value: 'recruitment', label: 'Recruitment' },
    { value: 'etc', label: 'Etc' },
];

const sessions = props.options?.sessions ?? defaultSessions;
const categories = props.options?.categories ?? defaultCategories;
const maxCategoryTags = categories.length;
const maxSessionTags = sessions.length;

const eventData = props.event ?? dummyEvents[0];

function toTokenList(v: unknown): string[] {
    if (Array.isArray(v)) return v.map((s) => String(s).trim()).filter(Boolean);
    if (typeof v === 'string') return v.split(',').map((s) => s.trim()).filter(Boolean);
    return [];
}

const initialCategories = toTokenList(eventData.category);
const initialSessions = toTokenList(eventData.session);

const form = useForm({
    _method: 'PUT' as const,
    title: eventData.title,
    description: eventData.description,
    location: eventData.location,
    start_date: eventData.start_date,
    end_date: eventData.end_date,
    registration_start: eventData.registration_start.replace(/\+.*$/, '').slice(0, 16),
    registration_end: eventData.registration_end.replace(/\+.*$/, '').slice(0, 16),
    quota: eventData.quota,
    price: eventData.price,
    session: initialSessions.join(','),
    category: initialCategories.join(','),
    banner: null as File | null,
    publish: eventData.status === 'published',
});

const bannerPreview = ref<string | null>(eventData.banner_url);
const isDragging = ref(false);

function handleBannerChange(e: Event) {
    const input = e.target as HTMLInputElement;
    if (input.files?.[0]) {
        form.banner = input.files[0];
        bannerPreview.value = URL.createObjectURL(input.files[0]);
    }
}
function handleDrop(e: DragEvent) {
    isDragging.value = false;
    const file = e.dataTransfer?.files[0];
    if (file && file.type.startsWith('image/')) {
        form.banner = file;
        bannerPreview.value = URL.createObjectURL(file);
    }
}
function removeBanner() {
    form.banner = null;
    bannerPreview.value = null;
}

function submitForm(publish: boolean) {
    form.publish = publish;
    if (typeof form.start_date === 'string') form.start_date = form.start_date.trim();
    if (typeof form.end_date === 'string') form.end_date = form.end_date.trim();
    if (typeof form.registration_start === 'string') form.registration_start = form.registration_start.trim();
    if (typeof form.registration_end === 'string') form.registration_end = form.registration_end.trim();
    form.transform((data) => ({
        ...data,
        category: toTokenList(data.category),
        session: toTokenList(data.session),
    }));
    form.post(updateEvent(eventData.id).url, {
        forceFormData: true,
        onSuccess: () => toast.success(publish ? 'Event published successfully.' : 'Event updated successfully.'),
        onError: (errors) => showEventValidationToast(errors),
    });
}
</script>

<template>
    <Head title="Edit Event" />

    <div class="flex flex-col gap-6">
        <PageHeader
            title="Edit Event"
            subtitle="Update the event details below."
            :backHref="showEvent.url(eventData.id)"
        />

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="flex flex-col gap-6 lg:col-span-2">
                <div class="flex flex-col gap-2">
                    <Label for="title">Title</Label>
                    <Input id="title" v-model="form.title" placeholder="Enter event title" />
                    <p v-if="form.errors.title" class="text-destructive text-xs">{{ form.errors.title }}</p>
                </div>
                <div class="flex flex-col gap-2">
                    <Label>Description</Label>
                    <TipTapEditor v-model="form.description" />
                    <p v-if="form.errors.description" class="text-destructive text-xs">{{ form.errors.description }}</p>
                </div>
                <div class="flex flex-col gap-2">
                    <Label>Banner Image</Label>
                    <div
                        v-if="!bannerPreview"
                        :class="[
                            'flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed px-6 py-12 text-center transition-colors',
                            isDragging
                                ? 'border-primary bg-primary/5'
                                : 'border-border hover:border-primary/50 hover:bg-muted/30',
                        ]"
                        @dragover.prevent="isDragging = true"
                        @dragleave="isDragging = false"
                        @drop.prevent="handleDrop"
                        @click="($refs.bannerInput as HTMLInputElement)?.click()"
                    >
                        <div class="bg-muted flex size-12 items-center justify-center rounded-xl">
                            <Upload class="text-muted-foreground size-5" />
                        </div>
                        <p class="mt-3 text-sm font-medium">Click to upload or drag and drop</p>
                        <input
                            ref="bannerInput"
                            type="file"
                            accept="image/*"
                            class="hidden"
                            @change="handleBannerChange"
                        />
                    </div>
                    <div v-else class="relative overflow-hidden rounded-xl">
                        <img :src="bannerPreview" alt="Banner preview" class="h-48 w-full object-cover" />
                        <Button
                            variant="destructive"
                            size="icon"
                            class="absolute top-2 right-2 size-7"
                            @click="removeBanner"
                            ><X class="size-3.5"
                        /></Button>
                    </div>
                    <p v-if="form.errors.banner" class="text-destructive text-xs">{{ form.errors.banner }}</p>
                </div>
                <div class="flex flex-col gap-2">
                    <Label for="location">Location</Label>
                    <Input id="location" v-model="form.location" placeholder="e.g. Online — Zoom" />
                    <p v-if="form.errors.location" class="text-destructive text-xs">{{ form.errors.location }}</p>
                </div>
            </div>

            <div class="flex flex-col gap-4">
                <Card class="rounded-xl border shadow-xs">
                    <CardHeader class="pb-3"
                        ><CardTitle class="text-sm font-medium">Event Details</CardTitle></CardHeader
                    >
                    <CardContent class="flex flex-col gap-4">
                        <div class="grid grid-cols-2 gap-3">
                            <div class="flex flex-col gap-1.5">
                                <Label class="text-xs">Start Date</Label>
                                <Input type="date" v-model="form.start_date" class="text-xs" />
                                <p v-if="form.errors.start_date" class="text-destructive text-xs">
                                    {{ form.errors.start_date }}
                                </p>
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <Label class="text-xs">End Date</Label>
                                <Input type="date" v-model="form.end_date" class="text-xs" />
                                <p v-if="form.errors.end_date" class="text-destructive text-xs">
                                    {{ form.errors.end_date }}
                                </p>
                            </div>
                        </div>
                        <Separator />
                        <div class="grid grid-cols-2 gap-3">
                            <div class="flex flex-col gap-1.5">
                                <Label class="text-xs">Reg. Start</Label>
                                <input
                                    v-model="form.registration_start"
                                    type="date"
                                    :class="datetimeInputClass"
                                />
                                <p v-if="form.errors.registration_start" class="text-destructive text-xs">
                                    {{ form.errors.registration_start }}
                                </p>
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <Label class="text-xs">Reg. End</Label>
                                <input
                                    v-model="form.registration_end"
                                    type="date"
                                    :class="datetimeInputClass"
                                />
                                <p v-if="form.errors.registration_end" class="text-destructive text-xs">
                                    {{ form.errors.registration_end }}
                                </p>
                            </div>
                        </div>
                        <Separator />
                        <div class="grid grid-cols-2 gap-3">
                            <div class="flex flex-col gap-1.5">
                                <Label class="text-xs">Quota</Label
                                ><Input type="number" v-model.number="form.quota" min="1" class="text-xs" />
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <Label class="text-xs">Price (Rp)</Label
                                ><Input type="number" v-model.number="form.price" min="0" class="text-xs" />
                            </div>
                        </div>
                        <Separator />
                        <div class="flex flex-col gap-1.5">
                            <Label class="text-xs">Sessions</Label>
                            <ComboboxTagInput
                                v-model="form.session"
                                :suggestions="sessions"
                                :max-tags="maxSessionTags"
                                :allow-custom="false"
                                placeholder="Search or select sessions…"
                            />
                            <p v-if="form.errors.session" class="text-destructive text-xs">{{ form.errors.session }}</p>
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <Label class="text-xs">Categories</Label>
                            <ComboboxTagInput
                                v-model="form.category"
                                :suggestions="categories"
                                :max-tags="maxCategoryTags"
                                :allow-custom="false"
                                placeholder="Search or select categories…"
                            />
                            <p v-if="form.errors.category" class="text-destructive text-xs">
                                {{ form.errors.category }}
                            </p>
                        </div>
                    </CardContent>
                </Card>
                <div class="flex flex-col gap-2">
                    <Button :disabled="form.processing" @click="submitForm(true)" class="w-full"
                        ><Send class="mr-1.5 size-4" />Update & Publish</Button
                    >
                    <Button variant="outline" :disabled="form.processing" @click="submitForm(false)" class="w-full"
                        ><Save class="mr-1.5 size-4" />Save Changes</Button
                    >
                </div>
            </div>
        </div>
    </div>
</template>
