<?php

namespace App\Http\Controllers\Dashboard\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckEmailRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class UserValidationController extends Controller
{
    /** Check if a user exists by email address. */
    public function checkEmail(CheckEmailRequest $request): JsonResponse
    {
        $email = (string) $request->validated('email');
        $normalizedEmail = mb_strtolower(trim($email));

        $user = User::resolveByNormalizedEmails([$normalizedEmail])[$normalizedEmail] ?? null;

        if ($user === null) {
            return response()->json([
                'exists' => false,
                'message' => __('No account exists for this email.'),
            ]);
        }

        return response()->json([
            'exists' => true,
            'message' => __('User account found.'),
            'data' => [
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at->toIso8601String(),
            ],
        ]);
    }
}
