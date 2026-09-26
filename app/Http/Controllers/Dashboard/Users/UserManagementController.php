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
        $page = $request->integer('page', 1);

        $paginator = $this->userManagementService->paginateForAdminIndex($validated, $page);

        return Inertia::render('Dashboard/Users/Index', [
            'users' => $paginator,
            'roleOptions' => $this->userManagementService->roleOptions(),
            'query' => [
                'search' => $validated['search'] ?? '',
                'role' => $validated['role'] ?? null,
                'per_page' => $validated['per_page'] ?? 10,
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', User::class);

        return Inertia::render('Dashboard/Users/Create', [
            'roleOptions' => $this->userManagementService->roleOptions(),
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

        return redirect()->route('dashboard.users.index');
    }

    public function show(User $user): Response
    {
        $this->authorize('view', $user);

        /** @var User $actor */
        $actor = auth()->guard('web')->user();

        return Inertia::render(
            'Dashboard/Users/Show',
            $this->userManagementService->toDetailPayload($user, $actor, request())
        );
    }

    public function edit(User $user): Response
    {
        $this->authorize('update', $user);

        $user->load('roles:id,name');

        return Inertia::render('Dashboard/Users/Edit', [
            'managedUser' => $this->userManagementService->toInertiaArray($user),
            'roleOptions' => $this->userManagementService->roleOptions(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $this->userManagementService->update($user, $request->validated());

        Inertia::flash('toast', [
            'message' => 'Akun berhasil diperbarui.',
            'type' => 'success',
        ]);

        return redirect()->route('dashboard.users.index');
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
}
