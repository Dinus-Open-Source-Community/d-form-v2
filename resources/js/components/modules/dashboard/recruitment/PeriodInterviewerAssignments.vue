<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';
import { SearchableSelect } from '@/components/ui/searchable-select';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { Plus, Trash2, UserCheck } from 'lucide-vue-next';

interface IOption {
    value: string;
    label: string;
    sublabel?: string;
    initials?: string;
    disabled?: boolean;
}
interface IAssignment {
    id: string;
    user_name: string;
    user_email: string;
    division_name: string;
}
interface IGroup {
    key: string;
    divisionName: string;
    divisionCode: string;
    count: number;
    rows: IAssignment[];
}

defineProps<{
    canManage: boolean;
    assignmentUserId: string;
    assignmentDivisionId: string;
    userOptions: IOption[];
    divisionOptions: IOption[];
    userError: string | undefined;
    divisionError: string | undefined;
    duplicate: boolean;
    canSubmit: boolean;
    processing: boolean;
    groups: IGroup[];
    initialsOf: (name: string) => string;
    pendingDescription: string;
    unassignOpen: boolean;
    unassigning: boolean;
    onCreate: () => void;
    onAssign: () => void;
    onUnassign: (assignmentId: string) => void;
    onConfirmUnassign: () => void;
    onCancelUnassign: () => void;
    onUserChange: (value: string) => void;
    onDivisionChange: (value: string) => void;
}>();

defineEmits<{ 'update:unassignOpen': [value: boolean] }>();
</script>

<template>
    <Card v-if="canManage" class="border-border/70 rounded-2xl">
        <CardHeader class="pb-1">
            <CardTitle class="text-base">Tugaskan interviewer</CardTitle>
            <p class="text-muted-foreground text-sm">
                Pilih orang dan divisi, lalu tugaskan. Kombinasi yang sudah ada tidak bisa dipilih lagi.
            </p>
        </CardHeader>
        <CardContent class="space-y-5">
            <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="onAssign">
                <div class="space-y-2">
                    <Label for="assign-user">Interviewer</Label>
                    <SearchableSelect
                        id="assign-user"
                        :model-value="assignmentUserId"
                        @update:model-value="onUserChange"
                        :options="userOptions"
                        placeholder="Pilih interviewer"
                        search-placeholder="Cari nama atau email…"
                        :aria-invalid="userError ? true : undefined"
                        @create="onCreate"
                    >
                        <template #action>
                            <Plus class="size-4" aria-hidden="true" />
                            <span>Tambah interviewer baru</span>
                        </template>
                    </SearchableSelect>
                    <p v-if="userError" role="alert" class="text-destructive text-xs">
                        {{ userError }}
                    </p>
                </div>
                <div class="space-y-2">
                    <Label for="assign-division">Divisi</Label>
                    <SearchableSelect
                        id="assign-division"
                        :model-value="assignmentDivisionId"
                        @update:model-value="onDivisionChange"
                        :options="divisionOptions"
                        placeholder="Pilih divisi"
                        search-placeholder="Cari divisi…"
                        :aria-invalid="divisionError ? true : undefined"
                    />
                    <p v-if="divisionError" role="alert" class="text-destructive text-xs">
                        {{ divisionError }}
                    </p>
                </div>
                <div class="sm:col-span-2">
                    <p v-if="duplicate" role="status" class="text-muted-foreground mb-2 text-xs">
                        Kombinasi interviewer dan divisi ini sudah ditugaskan.
                    </p>
                    <Button type="submit" size="sm" :disabled="!canSubmit">
                        {{ processing ? 'Menugaskan…' : 'Tugaskan' }}
                    </Button>
                </div>
            </form>

            <TooltipProvider v-if="groups.length > 0">
                <div class="space-y-4">
                    <section
                        v-for="group in groups"
                        :key="group.key"
                        aria-label="Interviewer divisi"
                        class="border-border/70 overflow-hidden rounded-xl border"
                    >
                        <header
                            class="border-border/60 bg-muted/40 flex items-center justify-between gap-3 border-b px-4 py-2.5"
                        >
                            <div class="flex min-w-0 items-center gap-2">
                                <span
                                    aria-hidden="true"
                                    class="bg-muted text-muted-foreground flex size-7 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold tracking-wide"
                                >
                                    {{ group.divisionCode.slice(0, 2).toUpperCase() }}
                                </span>
                                <h3 class="truncate text-sm font-semibold">{{ group.divisionName }}</h3>
                            </div>
                            <Badge variant="secondary" class="shrink-0 tabular-nums">
                                {{ group.count.toLocaleString('id-ID') }} orang
                            </Badge>
                        </header>
                        <ul class="divide-border/70 divide-y">
                            <li
                                v-for="row in group.rows"
                                :key="row.id"
                                class="flex items-center justify-between gap-3 px-4 py-3"
                            >
                                <div class="flex min-w-0 items-center gap-3">
                                    <Avatar class="size-8 shrink-0">
                                        <AvatarFallback class="text-[11px] font-semibold">
                                            {{ initialsOf(row.user_name) }}
                                        </AvatarFallback>
                                    </Avatar>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium">{{ row.user_name }}</p>
                                        <p class="text-muted-foreground truncate text-xs">
                                            {{ row.user_email }}
                                        </p>
                                    </div>
                                </div>
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            :aria-label="`Hapus ${row.user_name} dari ${row.division_name}`"
                                            @click="onUnassign(row.id)"
                                        >
                                            <Trash2 class="size-4" aria-hidden="true" />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>Hapus penugasan</p>
                                    </TooltipContent>
                                </Tooltip>
                            </li>
                        </ul>
                    </section>
                </div>
            </TooltipProvider>
            <div
                v-else
                class="border-border/80 flex flex-col items-center gap-2 rounded-xl border border-dashed px-4 py-8 text-center"
            >
                <span aria-hidden="true" class="bg-muted flex size-10 items-center justify-center rounded-full">
                    <UserCheck class="text-muted-foreground size-5" />
                </span>
                <p class="text-sm font-medium">Belum ada interviewer yang ditugaskan.</p>
                <p class="text-muted-foreground max-w-sm text-xs leading-relaxed">
                    Pilih interviewer dan divisi di atas untuk menugaskan.
                </p>
            </div>
        </CardContent>
    </Card>
</template>
