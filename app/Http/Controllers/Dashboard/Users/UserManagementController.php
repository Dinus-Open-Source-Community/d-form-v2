<?php

namespace App\Http\Controllers\Dashboard\Users;

use App\Http\Controllers\Controller;
use App\Http\Requests\Users\IndexUserRequest;
use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Models\User;
use App\Services\User\UserManagementService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class UserManagementController extends Controller
{
    public function __construct(
        private readonly UserManagementService $userManagementService,
    ) {
    }

    public function index(IndexUserRequest $request): Response
    {
        $validated = $request->validated();
        $page = max(1, $request->integer('page', 1));

        /** @var User $actor */
        $actor = $request->user();

        $paginator = $this->userManagementService->paginateForAdminIndex($validated, $actor, $page, $request);

        return Inertia::render('Dashboard/Users/Index', [
            'users' => $paginator,
            'roleOptions' => $this->userManagementService->roleOptions(),
            'query' => [
                'search' => $validated['search'] ?? '',
                'role' => $validated['role'] ?? null,
                'per_page' => $paginator->perPage(),
                'edit' => $validated['edit'] ?? null,
                'create' => (bool) ($validated['create'] ?? false),
            ],
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $this->authorize('create', User::class);

        $this->userManagementService->create($request->validated());

        Inertia::flash('toast', [
            'message' => 'Akun berhasil dibuat.',
            'type' => 'success',
        ]);

        return redirect()->route(
            'dashboard.users.index',
            $this->returnFilters($request->validated())
        );
    }

    public function show(User $user): Response
    {
        $this->authorize('view', $user);

        /** @var User $actor */
        $actor = auth()->guard('web')->user();

        return Inertia::render(
            'Dashboard/Users/Show',
            [
                ...$this->userManagementService->toDetailPayload($user, $actor, request()),
                'roleOptions' => $this->userManagementService->roleOptions(),
            ]
        );
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $this->userManagementService->update($user, $request->validated());

        Inertia::flash('toast', [
            'message' => 'Akun berhasil diperbarui.',
            'type' => 'success',
        ]);

        return redirect()->route(
            'dashboard.users.index',
            $this->returnFilters($request->validated())
        );
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        $this->userManagementService->delete($user);

        Inertia::flash('toast', [
            'message' => 'Akun berhasil dihapus.',
            'type' => 'success',
        ]);

        return redirect()->route('dashboard.users.index');
    }

    /**
     * Filter asal dari form create/edit agar konteks direktori tidak hilang.
     * Hanya search/role/page yang diteruskan dan nilai kosong dibuang.
     *
     * @param  array<string, mixed>  $validated
     * @return array{search?: string, role?: string, page?: int}
     */
    private function returnFilters(array $validated): array
    {
        $submitted = $validated['return_filters'] ?? null;
        $filters = is_array($submitted) ? $submitted : [];

        $result = [];

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $result['search'] = $search;
        }

        $role = $filters['role'] ?? null;
        if (is_string($role) && $role !== '') {
            $result['role'] = $role;
        }

        $page = $filters['page'] ?? null;
        if (is_numeric($page) && (int) $page >= 1) {
            $result['page'] = (int) $page;
        }

        return $result;
    }
}
