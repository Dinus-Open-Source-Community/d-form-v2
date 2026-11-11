/**
 * Fallback untuk `tsc` biasa (mis. `typecheck:unit`): berkas `.vue` hanya
 * berupa path, jadi tipenya dideklarasikan sebagai komponen Vue generik.
 * `vue-tsc` tetap memakai tipe SFC asli karena resolusi berkas menang atas wildcard.
 */
declare module '*.vue' {
    import type { DefineComponent } from 'vue';

    const component: DefineComponent<Record<string, unknown>, Record<string, unknown>, unknown>;
    export default component;
}
