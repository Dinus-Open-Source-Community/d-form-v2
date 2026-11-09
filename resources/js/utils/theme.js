import { writeThemeAttribute } from '@/adapters/browser/documentState'
import { readStorage, writeStorage } from '@/adapters/browser/storage'

export function initTheme() {
    let theme = getTheme();

    writeThemeAttribute(theme);
}

export function setTheme(theme) {
    if (!['light', 'dark'].includes(theme)) {
        theme = 'light';
    }

    writeStorage({ area: 'local', key: 'theme', value: theme });

    writeThemeAttribute(theme);
}

export function getTheme() {
    let theme = readStorage({ area: 'local', key: 'theme' });

    if (!theme || !['light', 'dark'].includes(theme)) {
        theme = setTheme('light');
    }

    return theme;
}
