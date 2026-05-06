declare global {
    interface IEvent {
        id: string
        title: string
        description: string
        start_date: string
        end_date: string
        registration_start: string
        registration_end: string
        location: string
        quota: number
        registered_count: number
        banner: string
        banner_url: string | null
        price: number
        session: string[]
        category: string[]
        status: 'draft' | 'published'
        registration_status: 'not_yet_open' | 'open' | 'closed' | 'full'
        deleted_at: string | null
        created_at: string
        updated_at: string
    }

    interface IForm {
        id: string
        title: string
        description: string
        visible_for: string[]
        closed_at: string
        event_id: string
        banner_url: string | null
        banner_caption: string | null
    }

    type FormFieldOptionType = 'text' | 'image'

    interface IFormFieldOption {
        id: string
        type: FormFieldOptionType
        label: string
        imageUrl?: string
    }

    // Rich types used in the Builder UI
    type FormBuilderType = 
        | 'short_text' | 'long_text' | 'email' | 'phone' | 'number' | 'time'
        | 'dropdown' | 'checkbox' | 'radio'
        | 'image_upload' | 'file_upload'
        | 'date' | 'rating'
        | 'heading' | 'paragraph' | 'divider' | 'banner'

    // Simple types stored in the Database
    type FormApiType = 'input' | 'select' | 'textarea' | 'datePicker' | 'fileUpload' | 'radio' | 'checkbox'

    interface IFormField {
        id: string
        type: FormApiType | FormBuilderType
        label: string
        description?: string | null
        name: string
        order: number
        metadata: Record<string, unknown>
        required?: boolean
        placeholder?: string
        options?: IFormFieldOption[]
    }

    interface IRegistrant {
        id: string
        user: {
            id: string
            name: string
            email: string
            avatar: string | null
        }
        event_id: string
        status: 'pending' | 'approved' | 'rejected'
        submitted_at: string
        answers: Record<string, string>
    }

    interface IFormSubmission {
        id: string
        user: { id: string; name: string; email: string } | null
        answers: Record<string, unknown>
        submitted_at: string
    }

    interface IKpiCard {
        label: string
        value: string | number
        trend: number
        icon: string
    }
}

export {}
