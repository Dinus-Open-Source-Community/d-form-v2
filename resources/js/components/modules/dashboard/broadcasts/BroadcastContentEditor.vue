<script setup lang="ts">
import { ref, type Component } from 'vue'
import { useEditor, EditorContent } from '@tiptap/vue-3'
import { StarterKit } from '@tiptap/starter-kit'
import { Underline } from '@tiptap/extension-underline'
import { TextAlign } from '@tiptap/extension-text-align'
import { Highlight } from '@tiptap/extension-highlight'
import { Link } from '@tiptap/extension-link'
import { Image } from '@tiptap/extension-image'
import { Placeholder } from '@tiptap/extension-placeholder'
import { Table, TableRow, TableCell, TableHeader } from '@tiptap/extension-table'
import { TextStyle, Color } from '@tiptap/extension-text-style'
import { Typography } from '@tiptap/extension-typography'
import { Button } from '@/components/ui/button'
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
    Bold,
    Italic,
    Underline as UnderlineIcon,
    Strikethrough,
    Code,
    Heading1,
    Heading2,
    Heading3,
    List,
    ListOrdered,
    Quote,
    AlignLeft,
    AlignCenter,
    AlignRight,
    Link as LinkIcon,
    Image as ImageIcon,
    Table as TableIcon,
    Highlighter,
    Undo,
    Redo,
    Minus,
    Upload as UploadIcon,
} from 'lucide-vue-next'

/**
 * Editor konten broadcast. Khusus broadcast (bukan builder form):
 * gambar dari paste/drop/pilih-file langsung diunggah ke endpoint
 * `POST {uploadUrl}` (multipart field `file`) dan URL storage yang
 * disisipkan — base64 (`data:` URL) tidak pernah masuk ke DB.
 */

const props = defineProps<{ modelValue?: string; uploadUrl: string }>()
const emit = defineEmits<{ 'update:modelValue': [value: string] }>()

const MAX_IMAGE_BYTES = 2 * 1024 * 1024
const ACCEPTED_IMAGE_TYPES: ReadonlySet<string> = new Set([
    'image/png',
    'image/jpeg',
    'image/gif',
    'image/webp',
])

function readXsrfToken(): string | null {
    if (typeof document === 'undefined') return null
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/)
    return match?.[1] ? decodeURIComponent(match[1]) : null
}

function readApiErrorMessage(body: unknown, status: number): string {
    if (body && typeof body === 'object') {
        const bag = body as Record<string, unknown>
        if (typeof bag.message === 'string' && bag.message.trim() !== '') return bag.message.trim()
        const errors = bag.errors
        if (errors && typeof errors === 'object') {
            for (const entry of Object.values(errors as Record<string, unknown>)) {
                if (Array.isArray(entry) && typeof entry[0] === 'string') return entry[0]
                if (typeof entry === 'string' && entry.trim() !== '') return entry
            }
        }
    }
    if (status === 413) return 'Ukuran gambar terlalu besar (maks 2 MB).'
    return 'Gagal mengunggah gambar.'
}

/** Normalisasi input URL agar valid untuk TipTap (tambah https:// bila perlu). */
function normalizeUrl(input: string): string | null {
    const raw = input.trim()
    if (!raw) return null
    if (raw.startsWith('data:')) return null
    let candidate = raw
    if (candidate.startsWith('//')) {
        candidate = `https:${candidate}`
    } else if (!/^[a-z][a-z0-9+.-]*:/i.test(candidate)) {
        candidate = `https://${candidate}`
    }
    try {
        return new URL(candidate).href
    } catch {
        return null
    }
}

const linkDialogOpen = ref(false)
const linkUrlDraft = ref('')
const linkTextDraft = ref('')
const linkHasSelection = ref(false)
const linkUrlError = ref('')

const imageDialogOpen = ref(false)
const imageUrlDraft = ref('')
const imageUrlError = ref('')

const fileInput = ref<HTMLInputElement | null>(null)
const uploadingCount = ref(0)
const uploadError = ref('')

const editor = useEditor({
    content: props.modelValue ?? '',
    extensions: [
        StarterKit.configure({ heading: { levels: [1, 2, 3] } }),
        Underline,
        TextAlign.configure({ types: ['heading', 'paragraph'] }),
        Highlight.configure({ multicolor: true }),
        Link.configure({ openOnClick: false }),
        Image.configure({ inline: true }),
        Placeholder.configure({ placeholder: 'Tulis isi email broadcast di sini…' }),
        Table.configure({ resizable: true }),
        TableRow,
        TableCell,
        TableHeader,
        TextStyle,
        Color,
        Typography,
    ],
    onUpdate: ({ editor: e }) => emit('update:modelValue', e.getHTML()),
    editorProps: {
        attributes: {
            class:
                'dform-rich-text dform-tiptap-editor ProseMirror w-full max-w-none px-4 py-4 focus:outline-none',
        },
        handlePaste: (_view, event) => {
            const files = imageFilesFromClipboard(event)
            if (files.length === 0) return false
            event.preventDefault()
            void uploadAndInsert(files)
            return true
        },
        handleDrop: (view, event) => {
            const files = imageFilesFromDrop(event)
            if (files.length === 0) return false
            event.preventDefault()
            const coords = view.posAtCoords({ left: event.clientX, top: event.clientY })
            void uploadAndInsert(files, coords?.pos)
            return true
        },
    },
})

function imageFilesFromClipboard(event: ClipboardEvent): File[] {
    const items = event.clipboardData?.items
    if (!items) return []
    const out: File[] = []
    for (const item of items) {
        if (item.kind !== 'file') continue
        const file = item.getAsFile()
        if (file && file.type.startsWith('image/')) out.push(file)
    }
    return out
}

function imageFilesFromDrop(event: DragEvent): File[] {
    const list = event.dataTransfer?.files
    if (!list) return []
    return Array.from(list).filter((file) => file.type.startsWith('image/'))
}

function validateImageFile(file: File): string | null {
    if (!ACCEPTED_IMAGE_TYPES.has(file.type)) {
        return `Format ${file.type || 'file'} tidak didukung. Gunakan PNG, JPG, GIF, atau WebP.`
    }
    if (file.size > MAX_IMAGE_BYTES) {
        return `“${file.name}” melebihi 2 MB.`
    }
    return null
}

async function uploadImageFile(file: File): Promise<string | null> {
    const target = props.uploadUrl.trim()
    if (target === '') {
        uploadError.value = 'Endpoint unggah gambar belum dikonfigurasi.'
        return null
    }
    const payload = new FormData()
    payload.append('file', file)
    const token = readXsrfToken()
    uploadingCount.value += 1
    try {
        const res = await fetch(target, {
            method: 'POST',
            body: payload,
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(token ? { 'X-XSRF-TOKEN': token } : {}),
            },
        })
        let body: unknown = null
        try {
            body = await res.json()
        } catch {
            body = null
        }
        if (!res.ok) {
            uploadError.value = readApiErrorMessage(body, res.status)
            return null
        }
        const url = (body as { url?: unknown } | null)?.url
        if (typeof url !== 'string' || url.trim() === '' || url.startsWith('data:')) {
            uploadError.value = 'Respons upload tidak membawa URL gambar yang valid.'
            return null
        }
        return url
    } catch {
        uploadError.value = 'Gagal mengunggah gambar. Periksa koneksi lalu coba lagi.'
        return null
    } finally {
        uploadingCount.value = Math.max(0, uploadingCount.value - 1)
    }
}

async function uploadAndInsert(files: File[], atPos?: number | null): Promise<void> {
    const e = editor.value
    if (!e) return
    uploadError.value = ''
    for (const file of files) {
        const invalid = validateImageFile(file)
        if (invalid) {
            uploadError.value = invalid
            continue
        }
        const url = await uploadImageFile(file)
        if (!url) continue
        if (typeof atPos === 'number' && atPos >= 0) {
            e.chain().focus().setTextSelection(atPos).setImage({ src: url }).run()
        } else {
            e.chain().focus().setImage({ src: url }).run()
        }
    }
}

function triggerFilePicker(): void {
    uploadError.value = ''
    fileInput.value?.click()
}

function onFilePicked(event: Event): void {
    const input = event.target as HTMLInputElement
    const files = input.files ? Array.from(input.files) : []
    input.value = ''
    if (files.length > 0) void uploadAndInsert(files)
}

function openLinkDialog(): void {
    const e = editor.value
    if (!e) return
    linkUrlError.value = ''
    const { from, to } = e.state.selection
    linkHasSelection.value = from !== to
    const attrs = e.getAttributes('link')
    const href = typeof attrs.href === 'string' ? attrs.href : ''
    linkUrlDraft.value = href
    if (from !== to) {
        linkTextDraft.value = e.state.doc.textBetween(from, to, '')
    } else {
        linkTextDraft.value = ''
    }
    linkDialogOpen.value = true
}

function confirmLink(): void {
    const e = editor.value
    if (!e) return
    const href = normalizeUrl(linkUrlDraft.value)
    if (!href) {
        linkUrlError.value = 'Masukkan URL yang valid (mis. contoh.com atau https://…).'
        return
    }
    linkUrlError.value = ''
    const { from, to } = e.state.selection
    const hasSelection = from !== to
    if (hasSelection) {
        e.chain().focus().setLink({ href }).run()
    } else {
        const label = linkTextDraft.value.trim() || href
        e.chain()
            .focus()
            .insertContent({
                type: 'text',
                text: label,
                marks: [{ type: 'link', attrs: { href } }],
            })
            .run()
    }
    linkDialogOpen.value = false
}

function openImageDialog(): void {
    imageUrlError.value = ''
    imageUrlDraft.value = ''
    imageDialogOpen.value = true
}

function confirmImage(): void {
    if (imageUrlDraft.value.trim().startsWith('data:')) {
        imageUrlError.value = 'Data URL tidak disimpan. Unggah gambar lewat tombol unggah agar tersimpan di storage.'
        return
    }
    const src = normalizeUrl(imageUrlDraft.value)
    if (!src) {
        imageUrlError.value = 'Masukkan URL gambar yang valid (https://…).'
        return
    }
    imageUrlError.value = ''
    editor.value?.chain().focus().setImage({ src }).run()
    imageDialogOpen.value = false
}

function addTable(): void {
    editor.value?.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run()
}

type ToolbarTool = {
    icon: Component
    title: string
    action: () => void
    active: () => boolean
}

const toolbarGroups: ToolbarTool[][] = [
    [
        {
            icon: Bold,
            title: 'Tebal',
            action: () => editor.value?.chain().focus().toggleBold().run(),
            active: () => editor.value?.isActive('bold') ?? false,
        },
        {
            icon: Italic,
            title: 'Miring',
            action: () => editor.value?.chain().focus().toggleItalic().run(),
            active: () => editor.value?.isActive('italic') ?? false,
        },
        {
            icon: UnderlineIcon,
            title: 'Garis bawah',
            action: () => editor.value?.chain().focus().toggleUnderline().run(),
            active: () => editor.value?.isActive('underline') ?? false,
        },
        {
            icon: Strikethrough,
            title: 'Coret',
            action: () => editor.value?.chain().focus().toggleStrike().run(),
            active: () => editor.value?.isActive('strike') ?? false,
        },
        {
            icon: Highlighter,
            title: 'Sorot',
            action: () => editor.value?.chain().focus().toggleHighlight().run(),
            active: () => editor.value?.isActive('highlight') ?? false,
        },
        {
            icon: Code,
            title: 'Kode inline',
            action: () => editor.value?.chain().focus().toggleCode().run(),
            active: () => editor.value?.isActive('code') ?? false,
        },
    ],
    [
        {
            icon: Heading1,
            title: 'Judul 1',
            action: () => editor.value?.chain().focus().toggleHeading({ level: 1 }).run(),
            active: () => editor.value?.isActive('heading', { level: 1 }) ?? false,
        },
        {
            icon: Heading2,
            title: 'Judul 2',
            action: () => editor.value?.chain().focus().toggleHeading({ level: 2 }).run(),
            active: () => editor.value?.isActive('heading', { level: 2 }) ?? false,
        },
        {
            icon: Heading3,
            title: 'Judul 3',
            action: () => editor.value?.chain().focus().toggleHeading({ level: 3 }).run(),
            active: () => editor.value?.isActive('heading', { level: 3 }) ?? false,
        },
    ],
    [
        {
            icon: List,
            title: 'Daftar bullet',
            action: () => editor.value?.chain().focus().toggleBulletList().run(),
            active: () => editor.value?.isActive('bulletList') ?? false,
        },
        {
            icon: ListOrdered,
            title: 'Daftar nomor',
            action: () => editor.value?.chain().focus().toggleOrderedList().run(),
            active: () => editor.value?.isActive('orderedList') ?? false,
        },
        {
            icon: Quote,
            title: 'Kutipan',
            action: () => editor.value?.chain().focus().toggleBlockquote().run(),
            active: () => editor.value?.isActive('blockquote') ?? false,
        },
        {
            icon: Minus,
            title: 'Garis pemisah',
            action: () => editor.value?.chain().focus().setHorizontalRule().run(),
            active: () => false,
        },
    ],
    [
        {
            icon: AlignLeft,
            title: 'Rata kiri',
            action: () => editor.value?.chain().focus().setTextAlign('left').run(),
            active: () => editor.value?.isActive({ textAlign: 'left' }) ?? false,
        },
        {
            icon: AlignCenter,
            title: 'Rata tengah',
            action: () => editor.value?.chain().focus().setTextAlign('center').run(),
            active: () => editor.value?.isActive({ textAlign: 'center' }) ?? false,
        },
        {
            icon: AlignRight,
            title: 'Rata kanan',
            action: () => editor.value?.chain().focus().setTextAlign('right').run(),
            active: () => editor.value?.isActive({ textAlign: 'right' }) ?? false,
        },
    ],
    [
        {
            icon: LinkIcon,
            title: 'Tautan',
            action: openLinkDialog,
            active: () => editor.value?.isActive('link') ?? false,
        },
        {
            icon: UploadIcon,
            title: 'Unggah gambar (maks 2 MB)',
            action: triggerFilePicker,
            active: () => uploadingCount.value > 0,
        },
        {
            icon: ImageIcon,
            title: 'Gambar dari URL',
            action: openImageDialog,
            active: () => false,
        },
        {
            icon: TableIcon,
            title: 'Sisipkan tabel',
            action: addTable,
            active: () => false,
        },
    ],
    [
        {
            icon: Undo,
            title: 'Urungkan',
            action: () => editor.value?.chain().focus().undo().run(),
            active: () => false,
        },
        {
            icon: Redo,
            title: 'Ulangi',
            action: () => editor.value?.chain().focus().redo().run(),
            active: () => false,
        },
    ],
]
</script>

<template>
    <div
        class="overflow-hidden rounded-xl border border-border/80 bg-card shadow-sm ring-1 ring-black/[0.04] dark:ring-white/[0.06]"
    >
        <div
            v-if="editor"
            class="flex flex-wrap items-center gap-2 border-b border-border/60 bg-gradient-to-b from-muted/45 via-muted/25 to-muted/10 px-2 py-2 md:px-3"
            role="toolbar"
            aria-label="Toolbar editor broadcast"
        >
            <template v-for="(group, gIdx) in toolbarGroups" :key="gIdx">
                <div
                    v-if="gIdx > 0"
                    class="hidden h-6 w-px bg-border/80 sm:block"
                    aria-hidden="true"
                />
                <div
                    class="flex flex-wrap items-center gap-0.5 rounded-xl bg-background/75 p-0.5 ring-1 ring-border/55 shadow-inner backdrop-blur-sm"
                >
                    <Button radius="icon"
                        v-for="(tool, tIdx) in group"
                        :key="tIdx"
                        type="button"
                        variant="ghost"
                        size="icon"
                        :title="tool.title"
                        class="size-8 text-muted-foreground transition-[color,background-color,box-shadow] duration-150 hover:bg-muted/90 hover:text-foreground"
                        :class="{
  'bg-primary !text-primary-foreground shadow-md ring-1 ring-primary/30 hover:bg-primary hover:!text-primary-foreground':
  tool.active(),
  }"
                        @click="tool.action()"
                    >
                        <component :is="tool.icon" class="size-3.5" stroke-width="2" />
                    </Button>
                </div>
            </template>
        </div>
        <input
            ref="fileInput"
            type="file"
            accept="image/png,image/jpeg,image/gif,image/webp"
            class="hidden"
            multiple
            @change="onFilePicked"
        />
        <EditorContent :editor="editor" />
        <p v-if="uploadingCount > 0" class="border-t border-border/60 px-4 py-2 text-xs text-muted-foreground">
            Mengunggah gambar…
        </p>
        <p v-if="uploadError" class="border-t border-border/60 px-4 py-2 text-xs font-medium text-destructive">
            {{ uploadError }}
        </p>

        <Dialog v-model:open="linkDialogOpen">
            <DialogContent class="gap-4 sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Tautan</DialogTitle>
                    <DialogDescription>
                        Tambah atau ubah tautan. Jika tidak ada teks yang dipilih, isi teks tampilan atau biarkan kosong
                        agar memakai URL.
                    </DialogDescription>
                </DialogHeader>
                <div class="grid gap-3">
                    <div class="grid gap-2">
                        <Label for="broadcast-link-url">URL</Label>
                        <Input
                            id="broadcast-link-url"
                            v-model="linkUrlDraft"
                            type="url"
                            autocomplete="url"
                            placeholder="https://…"
                            :aria-invalid="linkUrlError ? true : undefined"
                            @keydown.enter.prevent="confirmLink"
                        />
                        <p v-if="linkUrlError" class="text-xs font-medium text-destructive">
                            {{ linkUrlError }}
                        </p>
                    </div>
                    <div class="grid gap-2">
                        <Label for="broadcast-link-text">Teks tampilan</Label>
                        <Input
                            id="broadcast-link-text"
                            v-model="linkTextDraft"
                            type="text"
                            :disabled="linkHasSelection"
                            :placeholder="linkHasSelection ? 'Mengikuti seleksi di editor' : 'Opsional'"
                            @keydown.enter.prevent="confirmLink"
                        />
                        <p v-if="linkHasSelection" class="text-xs text-muted-foreground">
                            Teks tautan mengikuti yang sudah Anda pilih di editor.
                        </p>
                    </div>
                </div>
                <DialogFooter class="gap-2 sm:gap-0">
                    <DialogClose as-child>
                        <Button type="button" variant="outline">Batal</Button>
                    </DialogClose>
                    <Button type="button" @click="confirmLink">Simpan</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="imageDialogOpen">
            <DialogContent class="gap-4 sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Gambar dari URL</DialogTitle>
                    <DialogDescription>
                        Tempel URL gambar publik (https://…). Untuk file dari perangkat, tutup dialog ini lalu pakai
                        tombol unggah gambar di toolbar, tempel (paste), atau seret (drop) langsung ke editor.
                    </DialogDescription>
                </DialogHeader>
                <div class="grid gap-2">
                    <Label for="broadcast-image-url">URL gambar</Label>
                    <Input
                        id="broadcast-image-url"
                        v-model="imageUrlDraft"
                        type="url"
                        autocomplete="off"
                        placeholder="https://…"
                        :aria-invalid="imageUrlError ? true : undefined"
                        @keydown.enter.prevent="confirmImage"
                    />
                    <p v-if="imageUrlError" class="text-xs font-medium text-destructive">
                        {{ imageUrlError }}
                    </p>
                </div>
                <DialogFooter class="gap-2 sm:gap-0">
                    <DialogClose as-child>
                        <Button type="button" variant="outline">Batal</Button>
                    </DialogClose>
                    <Button type="button" @click="confirmImage">Simpan</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
