import type { Component } from 'vue'
import {
    Type,
    AlignLeft,
    Mail,
    Phone,
    Hash,
    ChevronDown,
    SquareCheck,
    CircleDot,
    ImagePlus,
    Upload,
    Calendar,
    Clock,
    Star,
    Heading as HeadingIcon,
    TextCursorInput,
    Minus,
} from 'lucide-vue-next'

export interface FormBuilderPaletteField {
    type: FormBuilderType
    label: string
    icon: Component
    description: string
}

export interface FormBuilderPaletteCategory {
    name: string
    icon: Component
    isOpen: boolean
    fields: FormBuilderPaletteField[]
}

const SOURCE: FormBuilderPaletteCategory[] = [
    {
        name: 'Text Inputs',
        icon: Type,
        isOpen: true,
        fields: [
            { type: 'short_text', label: 'Short Text', icon: Type, description: 'Single line text' },
            { type: 'long_text', label: 'Long Text', icon: AlignLeft, description: 'Multi-line text area' },
            { type: 'email', label: 'Email', icon: Mail, description: 'Email address input' },
            { type: 'phone', label: 'Phone', icon: Phone, description: 'Phone number input' },
            { type: 'number', label: 'Number', icon: Hash, description: 'Numeric value input' },
        ],
    },
    {
        name: 'Choice',
        icon: ChevronDown,
        isOpen: true,
        fields: [
            { type: 'dropdown', label: 'Dropdown', icon: ChevronDown, description: 'Select from a list' },
            { type: 'checkbox', label: 'Checkbox', icon: SquareCheck, description: 'Multiple selection' },
            { type: 'radio', label: 'Radio', icon: CircleDot, description: 'Single selection' },
        ],
    },
    {
        name: 'Media',
        icon: ImagePlus,
        isOpen: true,
        fields: [
            { type: 'image_upload', label: 'Image Upload', icon: ImagePlus, description: 'Upload an image file' },
            { type: 'file_upload', label: 'File Upload', icon: Upload, description: 'Upload a document' },
        ],
    },
    {
        name: 'Date & Time',
        icon: Calendar,
        isOpen: false,
        fields: [
            { type: 'date', label: 'Date', icon: Calendar, description: 'Date picker' },
            { type: 'time', label: 'Time', icon: Clock, description: 'Time picker' },
        ],
    },
    {
        name: 'Content',
        icon: TextCursorInput,
        isOpen: false,
        fields: [
            { type: 'heading', label: 'Heading', icon: HeadingIcon, description: 'Section title' },
            { type: 'paragraph', label: 'Paragraph', icon: TextCursorInput, description: 'Descriptive text block' },
            { type: 'divider', label: 'Divider', icon: Minus, description: 'Visual separator line' },
            { type: 'rating', label: 'Star Rating', icon: Star, description: 'Rate with stars' },
        ],
    },
]

/** Flat list of draggable palette entries (search / mobile pickers). */
export const ALL_FORM_BUILDER_FIELD_TEMPLATES: ReadonlyArray<FormBuilderPaletteField> = SOURCE.flatMap((c) => c.fields)

export const FORM_VISIBILITY_OPTIONS = [
    { value: 'public', label: 'Public' },
    { value: 'participant', label: 'Participant' },
    { value: 'admin', label: 'Admin' },
] as const

/** Fresh mutable tree for `ref()` (categories toggle `isOpen` per instance). */
export function cloneFormBuilderPalette(): FormBuilderPaletteCategory[] {
    return SOURCE.map((c) => ({
        ...c,
        fields: c.fields.map((f) => ({ ...f })),
    }))
}
