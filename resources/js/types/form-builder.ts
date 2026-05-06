export type FieldOptionType = 'text' | 'image'

export interface FieldOptionEntry {
    id: string
    type: FieldOptionType
    label: string
    imageUrl?: string
}

export interface BuilderField {
    id: string
    type: string
    label: string
    description: string
    name: string
    placeholder: string
    required: boolean
    options: FieldOptionEntry[]
    metadata: Record<string, unknown>
}

export type BackendFieldType = 'input' | 'select' | 'textarea' | 'datePicker' | 'fileUpload' | 'checkbox' | 'radio'

export interface BackendField {
    id: string
    type: BackendFieldType
    label: string
    description: string | null
    name: string
    order: number
    metadata: Record<string, unknown>
}
