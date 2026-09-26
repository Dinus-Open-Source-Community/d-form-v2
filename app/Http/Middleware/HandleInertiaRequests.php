<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\User\UserAvatarService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    private static function absolutePublicUrl(\Illuminate\Http\Request $request, string $pathOrUrl): ?string
    {
        $pathOrUrl = trim($pathOrUrl);
        if ($pathOrUrl === '') {
            return null;
        }

        if (str_starts_with($pathOrUrl, 'http://') || str_starts_with($pathOrUrl, 'https://')) {
            return $pathOrUrl;
        }

        return $request->getSchemeAndHttpHost().'/'.ltrim($pathOrUrl, '/');
    }

    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        /** @var User|null $user */
        $user = auth()->guard('web')->user();

        return [
            ...parent::share($request),
            'auth.user' => $user ? array_merge(
                $user->toArray(),
                [
                    'roles' => $user->getRoleNames()->toArray(),
                    'has_local_password' => filled($user->getRawOriginal('password')),
                    'can_manage_events' => $user->can('events.list'),
                    'can_manage_users' => $user->can('users.list'),
                    'can_access_recruitment' => $user->can('recruitment.dashboard.view'),
                    'is_recruitment_interviewer_only' => app(\App\Services\Recruitment\RecruitmentDashboardService::class)
                        ->isInterviewerOnly($user),
                    'can_manage_recruitment_periods' => $user->can('recruitment.periods.list'),
                    'can_list_recruitment_applications' => $user->can('recruitment.applications.list'),
                    'can_screen_recruitment_applications' => $user->can('recruitment.screening.decide'),
                    'can_review_recruitment_corrections' => $user->can('recruitment.corrections.review'),
                    'can_schedule_recruitment_interviews' => $user->can('recruitment.interviews.schedule'),
                    'can_view_recruitment_queue' => $user->can('recruitment.queue.view'),
                    'can_manage_recruitment_queue' => $user->can('recruitment.queue.manage'),
                    'can_scan_recruitment_attendance' => $user->can('recruitment.attendance.scan'),
                    'can_evaluate_recruitment_interviews' => $user->can('recruitment.evaluations.submit'),
                    'can_view_my_recruitment_interviews' => $user->can('recruitment.evaluations.view'),
                    'can_decide_recruitment_final' => $user->can('recruitment.final.decide'),
                    'can_view_recruitment_reports' => $user->can('recruitment.reports.view'),
                    'can_view_recruitment_activity' => $user->can('recruitment.activity.view'),
                    'avatar' => UserAvatarService::resolvePublicUrl($user->avatar, $request),
                ],
            ) : null,
            'seo' => [
                'siteName' => (string) config('seo.site_name'),
                'siteUrl' => rtrim((string) config('app.url'), '/'),
                'defaultDescription' => (string) config('seo.default_description'),
                'locale' => (string) config('seo.locale'),
                'htmlLang' => (string) config('seo.html_lang'),
                'defaultOgImage' => self::absolutePublicUrl($request, (string) config('seo.default_og_image')),
                'twitterSite' => config('seo.twitter_site') ? '@'.ltrim((string) config('seo.twitter_site'), '@') : null,
                'twitterCreator' => config('seo.twitter_creator') ? '@'.ltrim((string) config('seo.twitter_creator'), '@') : null,
                'googleSiteVerification' => config('seo.google_site_verification'),
                'bingSiteVerification' => config('seo.bing_site_verification'),
                'yandexVerification' => config('seo.yandex_verification'),
            ],
        ];
    }
}
