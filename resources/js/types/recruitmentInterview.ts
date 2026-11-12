export interface IInterviewDetailPayload {
    application: {
        id: string;
        registration_number: string;
        full_name: string;
        nim: string;
        semester: number;
        primary_division: string | null;
        secondary_division: string | null;
    };
    documents: {
        has_cv?: boolean;
        has_portfolio?: boolean;
        portfolio_is_url?: boolean;
        has_instagram_follow?: boolean;
        cv_download_url: string | null;
        portfolio_download_url: string | null;
        portfolio_url: string | null;
        twibbon_url?: string | null;
        cv_original_name?: string | null;
        cv_size_bytes?: number | null;
        cv_preview_url?: string | null;
        portfolio_original_name?: string | null;
        portfolio_size_bytes?: number | null;
        portfolio_preview_url?: string | null;
        instagram_follow_download_url?: string | null;
        instagram_follow_preview_url?: string | null;
        instagram_follow_original_name?: string | null;
        instagram_follow_size_bytes?: number | null;
    };
    interview: {
        scheduled_at: string;
        location: string;
        room: string;
        status: string;
        status_label: string;
        session: { id: string; session_date: string; division: string | null } | null;
    } | null;
    queue: { queue_number: number; status_label: string } | null;
    attendance?: {
        has_attendance?: boolean;
        checked_in_at?: string | null;
    };
    evaluation: {
        speaking_score?: number;
        technical_score?: number;
        attitude_score?: number;
        recommendation?: string;
        recommendation_label?: string;
        notes?: string | null;
        is_locked?: boolean;
        can_edit?: boolean;
        save_count?: number;
        saves_remaining?: number;
    };
    primary_evaluation?: {
        recommendation?: string;
        recommendation_label?: string;
        notes?: string | null;
    } | null;
}
