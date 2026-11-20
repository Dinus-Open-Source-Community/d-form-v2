<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { MailOpen } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

defineProps<{
    invitationUrl: string;
}>();
</script>

<template>
    <Card class="gap-0 rounded-2xl border-amber-500/30 bg-amber-500/[0.06] py-0">
        <CardContent class="flex flex-col gap-3 px-5 py-4">
            <p class="text-[11px] font-semibold text-amber-800 dark:text-amber-400">Undangan tim</p>
            <p class="text-muted-foreground text-xs leading-relaxed">
                Anda diundang sebagai anggota tim atau paket pendaftaran. Anda belum tercatat sebagai peserta sampai
                undangan disetujui.
            </p>
            <Button class="h-10 w-full" as-child>
                <Link :href="invitationUrl">
                    <MailOpen class="size-4" aria-hidden="true" />
                    Lihat undangan
                </Link>
            </Button>
        </CardContent>
    </Card>
</template>
