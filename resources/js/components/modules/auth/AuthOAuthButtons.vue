<script setup lang="ts">
import { Button } from '@/components/ui/button';
import ProviderBrandIcon from '@/components/core/ProviderBrandIcon.vue';
import { redirectToGithub, redirectToGoogle } from '@/actions/App/Http/Controllers/Auth/OAuthController';
import { assignUrl } from '@/adapters/browser/navigation';

function goToGoogle() {
    assignUrl(redirectToGoogle.url());
}

function goToGithub() {
    assignUrl(redirectToGithub.url());
}
</script>

<template>
    <div>
        <div class="my-6 flex items-center gap-3">
            <span class="bg-border h-px flex-1"></span>
            <span class="text-muted-foreground text-[11px] font-medium tracking-[0.16em] uppercase"
                >atau lanjutkan dengan</span
            >
            <span class="bg-border h-px flex-1"></span>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <Button variant="outline" class="w-full" @click="goToGoogle">
                <ProviderBrandIcon provider="google" />
                <span>Google</span>
            </Button>

            <Button variant="outline" class="w-full" @click="goToGithub">
                <ProviderBrandIcon provider="github" />
                <span>GitHub</span>
            </Button>
        </div>
    </div>
</template>
