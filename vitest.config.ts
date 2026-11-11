import { fileURLToPath } from 'node:url';
import vue from '@vitejs/plugin-vue';
import { defineConfig } from 'vitest/config';

/**
 * Runner unit test frontend. Environment `node` dipakai karena adapter diuji
 * dengan global browser yang di-stub per test; tidak perlu jsdom. Komponen
 * `.vue` dirender lewat `@vue/server-renderer`, jadi plugin Vue tetap wajib
 * aktif agar SFC bisa dikompilasi.
 */
export default defineConfig({
    plugins: [vue()],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    test: {
        environment: 'node',
        include: ['resources/js/**/*.test.ts'],
        clearMocks: true,
        restoreMocks: true,
        // Baseline FE-AUDIT-002: hanya surface yang memang punya test harness
        // (lib murni, adapter, hook). File test dan deklarasi tipe tidak dihitung.
        coverage: {
            provider: 'v8',
            reporter: ['text', 'json-summary'],
            reportsDirectory: 'coverage',
            include: ['resources/js/lib/**/*.ts', 'resources/js/adapters/**/*.ts', 'resources/js/hooks/**/*.ts'],
            exclude: ['resources/js/**/__tests__/**', 'resources/js/**/*.d.ts'],
        },
    },
});
