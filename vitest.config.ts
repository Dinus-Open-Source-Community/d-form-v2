import { fileURLToPath } from 'node:url';
import vue from '@vitejs/plugin-vue';
import { defineConfig } from 'vitest/config';

/** Node runner; browser globals are stubbed and Vue SFCs are compiled by the plugin. */
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
        // Coverage includes only tested runtime surfaces, not tests or declarations.
        coverage: {
            provider: 'v8',
            reporter: ['text', 'json-summary'],
            reportsDirectory: 'coverage',
            include: ['resources/js/lib/**/*.ts', 'resources/js/adapters/**/*.ts', 'resources/js/hooks/**/*.ts'],
            exclude: ['resources/js/**/__tests__/**', 'resources/js/**/*.d.ts'],
        },
    },
});
