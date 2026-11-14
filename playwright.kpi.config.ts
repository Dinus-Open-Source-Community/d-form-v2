import { defineConfig } from '@playwright/test';
import baseConfig from './playwright.config';

export default defineConfig({
    ...baseConfig,
    use: {
        ...baseConfig.use,
        launchOptions: {
            args: ['--host-resolver-rules=MAP 192.168.0.170 127.0.0.1'],
        },
    },
});
