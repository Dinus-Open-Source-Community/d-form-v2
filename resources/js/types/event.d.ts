declare global {
    interface IEvent {
        id: string
        slug: string
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
        /** Portal "acara diikuti": anggota tim belum terima/ tolak undangan */
        pending_team_invitation_url?: string | null
    }

    interface IForm {
        id: string
        title: string
        description: string
        success_content?: string | null
        visible_for: string[]
        closed_at: string
        event_id: string
        banner_url: string | null
        banner_caption: string | null
        metadata?: Record<string, unknown>
        registration_mode?: 'single' | 'bundle'
        purpose?: 'registration' | 'other'
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
        | 'url' | 'address' | 'yes_no'

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
        /** Team flow: member may edit on confirmation when true */
        is_append?: boolean
        required?: boolean
        placeholder?: string
        options?: IFormFieldOption[]
    }

    interface IRegistrant {
        id: string
        form_id: string
        form: { id: string; title: string }
        user: {
            id: string
            name: string
            email: string
            avatar: string | null
        }
        event_id: string
        status: 'pending' | 'accepted' | 'rejected'
        submitted_at: string
        answers: Record<string, string>
        registration_code?: string | null
        reviewed_at?: string | null
    }

    interface IFormSubmission {
        id: string
        user: { id: string; name: string; email: string; avatar?: string | null } | null
        answers: Record<string, unknown>
        submitted_at: string
        review_status?: 'pending' | 'accepted' | 'rejected' | null
        reviewed_at?: string | null
        reviewed_by?: string | null
        reviewer?: { id: string; name: string; email: string } | null
        registration_role?: 'leader' | 'member' | null
        member_confirmation_status?: 'pending' | 'accepted' | 'rejected' | 'expired' | null
        group_token?: string | null
    }

    interface IKpiCard {
        label: string
        value: string | number
        trend: number
        icon: string
    }

    interface IBundleSubmissionMember extends IFormSubmission {
        display_name?: string
        invited_email?: string | null
        can_open_detail: boolean
        can_review: boolean
        locked_reason?: string | null
    }

    interface IBundleSubmissionGroup {
        group_token: string
        leader: IBundleSubmissionMember
        members: IBundleSubmissionMember[]
        total_participants: number
        accepted_count: number
        pending_count: number
        rejected_count: number
        expired_count: number
        group_review_status: 'pending' | 'partial' | 'accepted' | 'rejected'
        submitted_at: string
    }
}

export {}
