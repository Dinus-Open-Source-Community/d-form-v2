<script setup lang="ts">
defineProps<{
    event: { id: string; title: string }
    form: { id: string; title: string }
    submissions: {
        data: Array<{
            id: string
            user: { id: string; name: string; email: string } | null
            answers: Record<string, unknown>
            submitted_at: string
        }>
        current_page: number
        last_page: number
        per_page: number
        total: number
    }
}>()
</script>

<template>
    <!-- TODO: Implement submissions table UI (see docs/05-m4-registration.md §3.2) -->
    <div>
        <h1>{{ form.title }} — Submissions</h1>
    </div>
</template>
