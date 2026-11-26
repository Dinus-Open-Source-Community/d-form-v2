<?php

namespace App\Enums;

enum EmailNotificationType: string
{
    case RegistrationSubmitted = 'registration_submitted';
    case RegistrationAccepted = 'registration_accepted';
    case RegistrationRejected = 'registration_rejected';
    case DuRedeemIssued = 'du_redeem_issued';
    case AttendanceConfirmed = 'attendance_confirmed';
    case AnswerCollectionReceived = 'answer_collection_received';
    case FeedbackReceived = 'feedback_received';
    case ClassAttendanceCard = 'class_attendance_card';
    case ClassAttendanceConfirmed = 'class_attendance_confirmed';
    case TeamInvitation = 'team_invitation';
    case InvitationDeclinedByInvitee = 'invitation_declined_by_invitee';
    case TeamMemberInvitationAcceptedLeaderNotice = 'team_member_invitation_accepted_leader_notice';
    case TeamMemberInvitationDeclinedLeaderNotice = 'team_member_invitation_declined_leader_notice';
    case RecruitmentApplicationSubmitted = 'recruitment_application_submitted';
    case RecruitmentRevisionRequired = 'recruitment_revision_required';
    case RecruitmentPassedScreening = 'recruitment_passed_screening';
    case RecruitmentRejectedScreening = 'recruitment_rejected_screening';
    case RecruitmentCorrectionRequestStaff = 'recruitment_correction_request_staff';
    case RecruitmentInterviewScheduled = 'recruitment_interview_scheduled';
    case RecruitmentInterviewRescheduled = 'recruitment_interview_rescheduled';
    case RecruitmentInterviewReminderH1 = 'recruitment_interview_reminder_h1';
    case RecruitmentInterviewReminderH2 = 'recruitment_interview_reminder_h2';
    case RecruitmentInterviewAssignment = 'recruitment_interview_assignment';
    case RecruitmentFinalAccepted = 'recruitment_final_accepted';
    case RecruitmentFinalRejected = 'recruitment_final_rejected';
    case RecruitmentGroupLink = 'recruitment_group_link';
    case BroadcastSent = 'broadcast_sent';
}
