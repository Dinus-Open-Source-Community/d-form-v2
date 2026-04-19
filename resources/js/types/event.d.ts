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
    }

    interface IFormField {
        id: string
        input_type: 'textInput' | 'selectInput' | 'textarea' | 'datePicker' | 'fileUpload'
        metadata: Record<string, unknown>
        form_id: string
    }

    interface IRegistrant {
        id: string
        user: IUser
        event_id: string
        status: 'pending' | 'approved' | 'rejected'
        submitted_at: string
        answers: Record<string, string>
    }

    interface IKpiCard {
        label: string
        value: string | number
        trend: number
        icon: string
    }
}

export {}
