<script setup lang="ts">
import { computed } from 'vue';
import { Card, CardContent } from '@/components/ui/card';
import { Instagram } from 'lucide-vue-next';
import type { IApplicationDetail } from '@/types/recruitment';

const props = defineProps<{ application: IApplicationDetail; readonly: boolean }>();
const instagramHandle = computed<string>(() => props.application.instagram_username.replace(/^@+/, '').trim());
const instagramUrl = computed<string>(() => `https://instagram.com/${instagramHandle.value}`);
</script>

<template>
    <Card class="border-border/70 rounded-2xl">
        <CardContent class="space-y-5 p-6">
            <div class="space-y-3">
                <p class="text-muted-foreground text-sm font-semibold tracking-wide uppercase">Detail pendaftaran</p>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div v-if="!readonly">
                        <p class="text-muted-foreground text-xs uppercase">NIM</p>
                        <p class="font-medium">{{ application.nim }}</p>
                    </div>
                    <div>
                        <p class="text-muted-foreground text-xs uppercase">Semester</p>
                        <p class="font-medium">{{ application.semester }}</p>
                    </div>
                    <div>
                        <p class="text-muted-foreground text-xs uppercase">Divisi utama</p>
                        <p class="font-medium">{{ application.primary_division?.name ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-muted-foreground text-xs uppercase">Divisi cadangan</p>
                        <p class="font-medium">{{ application.secondary_division?.name ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-muted-foreground text-xs uppercase">Periode</p>
                        <p class="font-medium">{{ application.period?.name ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-muted-foreground text-xs uppercase">Hasil</p>
                        <p class="font-medium">{{ application.result_label }}</p>
                    </div>
                </div>
            </div>
            <div class="border-border/60 space-y-3 border-t pt-5">
                <p class="text-muted-foreground text-sm font-semibold tracking-wide uppercase">Kontak</p>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <p class="text-muted-foreground text-xs uppercase">Telepon</p>
                        <p class="font-medium">{{ application.phone }}</p>
                    </div>
                    <div>
                        <p class="text-muted-foreground text-xs uppercase">Instagram</p>
                        <a
                            v-if="instagramHandle"
                            :href="instagramUrl"
                            target="_blank"
                            rel="noopener"
                            class="mt-0.5 inline-flex items-center gap-1.5 font-medium underline-offset-4 hover:underline"
                        >
                            <Instagram class="size-4 shrink-0" aria-hidden="true" />
                            <span>@{{ instagramHandle }}</span>
                        </a>
                        <p v-else class="font-medium">—</p>
                    </div>
                    <div>
                        <p class="text-muted-foreground text-xs uppercase">Email pribadi</p>
                        <p class="font-medium">{{ application.personal_email }}</p>
                    </div>
                    <div>
                        <p class="text-muted-foreground text-xs uppercase">Email kampus</p>
                        <p class="font-medium">{{ application.student_email }}</p>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
