<script setup lang="ts">
import { computed, ref } from 'vue';
import type { ComputedRef, Ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { readStorage, writeStorage } from '@/adapters/browser/storage';
import { notice as verificationNotice } from '@/routes/verification';

/** Kunci sesi "Ingatkan nanti": banner kembali muncul di sesi browser berikutnya. */
const DISMISS_STORAGE_KEY = 'email-verification-banner-dismissed';

const page = usePage();

const isDismissed: Ref<boolean> = ref<boolean>(readStorage({ area: 'session', key: DISMISS_STORAGE_KEY }) === '1');

const user = computed(() => page.props.auth.user);

/** Hanya akun yang emailnya belum terverifikasi dan belum memilih "Ingatkan nanti". */
const isVisible: ComputedRef<boolean> = computed<boolean>(
    () => user.value !== null && user.value.email_verified_at === null && !isDismissed.value
);

function dismiss(): void {
    writeStorage({ area: 'session', key: DISMISS_STORAGE_KEY, value: '1' });
    isDismissed.value = true;
}
</script>

<template>
    <div v-if="isVisible" data-testid="email-verification-banner" class="border-warning/40 bg-warning/10 border-b">
        <div
            class="mx-auto flex w-full max-w-7xl flex-col gap-3 px-4 py-3 md:flex-row md:items-center md:justify-between md:px-6 lg:px-8"
        >
            <p class="text-foreground text-sm font-medium">Email Anda belum diverifikasi.</p>

            <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                <Button as-child class="min-h-[44px] w-full sm:w-auto">
                    <Link :href="verificationNotice.url()" data-testid="email-verification-cta">
                        Verifikasi sekarang
                    </Link>
                </Button>

                <button
                    type="button"
                    data-testid="email-verification-dismiss"
                    class="text-muted-foreground hover:text-foreground focus-visible:ring-ring/40 focus-visible:ring-offset-background inline-flex min-h-[44px] items-center justify-center px-3 text-sm font-medium underline-offset-4 transition-colors hover:underline focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none"
                    @click="dismiss"
                >
                    Ingatkan nanti
                </button>
            </div>
        </div>
    </div>
</template>
