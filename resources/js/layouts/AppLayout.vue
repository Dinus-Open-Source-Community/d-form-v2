<script setup lang="ts">
import './../../css/app.css';

import { ref } from 'vue';

let data = ref<string>();
</script>

<template>
    <div>
        <div class="text-center"></div>

        <slot></slot>
    </div>
</template>

<style>
* {
    padding: 0;
}
</style>
