<div class="absolute top-4 right-4" x-data>
    <button
        class="btn btn-ghost text-base-content aspect-square p-0"
        x-on:click="$store.themeController.toggle()"
        x-show="$store.themeController.active === 'light'"
    >
        @svg('heroicon-o-sun', 'h-6')
    </button>

    <button
        class="btn btn-ghost text-base-content aspect-square p-0"
        x-on:click="$store.themeController.toggle()"
        x-show="$store.themeController.active === 'dark'"
    >
        @svg('heroicon-o-moon', 'h-6')
    </button>
</div>
