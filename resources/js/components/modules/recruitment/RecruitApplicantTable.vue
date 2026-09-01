<script setup lang="ts">
import RecruitStatusBadge from './RecruitStatusBadge.vue';
import { divisionLabels } from '@/lib/dummyRecruitment';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Button } from '@/components/ui/button';
import type { IApplication } from '@/types/recruitment';

defineProps<{
    applications: IApplication[];
}>();

defineEmits<{
    open: [id: string];
}>();
</script>

<template>
    <div class="overflow-x-auto">
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>No. Pendaftaran</TableHead>
                    <TableHead>Nama</TableHead>
                    <TableHead>NIM</TableHead>
                    <TableHead>Divisi Utama</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead class="text-right">Aksi</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow v-for="app in applications" :key="app.id">
                    <TableCell class="font-mono text-xs">{{ app.registrationNumber }}</TableCell>
                    <TableCell class="font-medium">{{ app.applicant.fullName }}</TableCell>
                    <TableCell class="text-muted-foreground">{{ app.applicant.nim }}</TableCell>
                    <TableCell>{{ divisionLabels[app.applicant.primaryDivision] ?? app.applicant.primaryDivision }}</TableCell>
                    <TableCell><RecruitStatusBadge :stage="app.stage" /></TableCell>
                    <TableCell class="text-right">
                        <Button variant="ghost" size="sm" @click="$emit('open', app.id)">Detail</Button>
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>
    </div>
</template>
