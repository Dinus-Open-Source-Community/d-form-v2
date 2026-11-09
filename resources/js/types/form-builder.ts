export type TFieldOptionType = 'text' | 'image';

export interface IFieldOptionEntry {
    id: string;
    type: TFieldOptionType;
    label: string;
    imageUrl?: string;
}

/** A persisted form section used by the dashboard builder and public form flow. */
export interface IFormBuilderSection {
    id: string;
    title: string;
}

export interface IBuilderField {
    id: string;
    type: string;
    label: string;
    description: string;
    name: string;
    placeholder: string;
    required: boolean;
    options: IFieldOptionEntry[];
    metadata: Record<string, unknown>;
    /** Field may be edited by invited members (team flow); persisted as `form_fields.is_append`. */
    is_append?: boolean;
}

export type TBackendFieldType = 'input' | 'select' | 'textarea' | 'datePicker' | 'fileUpload' | 'checkbox' | 'radio';

export interface IBackendField {
    id: string;
    type: TBackendFieldType;
    label: string;
    description: string | null;
    name: string;
    order: number;
    metadata: Record<string, unknown>;
    is_append?: boolean;
}
