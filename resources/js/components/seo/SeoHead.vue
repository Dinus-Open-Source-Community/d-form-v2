<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { SharedSeoProps } from '@/types/seo';

const props = withDefaults(
    defineProps<{
        /** Judul halaman (tanpa suffix brand; suffix ditambahkan otomatis ke <title>) */
        title: string;
        description?: string;
        /** Path kanonis, harus diawali / (contoh /events, /events/slug-acara) */
        canonicalPath: string;
        /** URL absolut gambar OG; default dari konfigurasi jika kosong */
        ogImage?: string | null;
        ogType?: 'website' | 'article';
        /** Satu atau beberapa objek JSON-LD schema.org (akan di-render sebagai satu blok atau array) */
        jsonLd?: Record<string, unknown> | Record<string, unknown>[] | null;
        /** Tandai halaman privat (auth) agar tidak diindeks; canonical ikut dihilangkan. */
        noindex?: boolean;
    }>(),
    {
        description: undefined,
        ogImage: undefined,
        ogType: 'website',
        jsonLd: null,
        noindex: false,
    }
);

const page = usePage();

const seo = computed(() => (page.props as { seo?: SharedSeoProps }).seo);

const siteName = computed(() => seo.value?.siteName ?? 'App');

const pageTitle = computed(() => props.title.trim());

const documentTitle = computed(() => {
    const t = pageTitle.value;
    const sn = siteName.value;
    if (!t) {
        return sn;
    }
    return t.toLowerCase().includes(sn.toLowerCase()) ? t : `${t} · ${sn}`;
});

const description = computed(
    () => props.description?.trim() || seo.value?.defaultDescription || '—'
);

const canonicalUrl = computed(() => {
    const base = (seo.value?.siteUrl ?? '').replace(/\/$/, '');
    const path = props.canonicalPath.startsWith('/') ? props.canonicalPath : `/${props.canonicalPath}`;
    return `${base}${path === '//' ? '/' : path}`;
});

const ogImageResolved = computed(() => {
    if (props.ogImage) {
        return props.ogImage;
    }
    return seo.value?.defaultOgImage ?? null;
});

const jsonLdScript = computed<string | null>(() => {
    if (!props.jsonLd) {
        return null;
    }
    const payload = Array.isArray(props.jsonLd) ? props.jsonLd : [props.jsonLd];
    const data = payload.length === 1 ? payload[0] : payload;
    try {
        return JSON.stringify(data);
    } catch {
        return null;
    }
});

const ogLocale = computed(() => (seo.value?.locale ?? 'id_ID').replace('-', '_'));

const twitterSite = computed(() => seo.value?.twitterSite ?? null);
const twitterCreator = computed(() => seo.value?.twitterCreator ?? null);
</script>

<template>
    <Head>
        <title>{{ documentTitle }}</title>
        <meta name="description" :content="description" />
        <meta v-if="noindex" name="robots" content="noindex, nofollow" />
        <link v-if="!noindex" rel="canonical" :href="canonicalUrl" />

        <meta property="og:type" :content="props.ogType" />
        <meta property="og:site_name" :content="siteName" />
        <meta property="og:title" :content="pageTitle" />
        <meta property="og:description" :content="description" />
        <meta property="og:url" :content="canonicalUrl" />
        <meta property="og:locale" :content="ogLocale" />
        <template v-if="ogImageResolved">
            <meta property="og:image" :content="ogImageResolved" />
            <meta property="og:image:alt" :content="pageTitle" />
        </template>

        <meta name="twitter:card" content="summary_large_image" />
        <meta name="twitter:title" :content="pageTitle" />
        <meta name="twitter:description" :content="description" />
        <template v-if="ogImageResolved">
            <meta name="twitter:image" :content="ogImageResolved" />
        </template>
        <meta v-if="twitterSite" name="twitter:site" :content="twitterSite" />
        <meta v-if="twitterCreator" name="twitter:creator" :content="twitterCreator" />

        <component v-if="jsonLdScript" :is="'script'" type="application/ld+json">{{ jsonLdScript }}</component>
    </Head>
</template>
