<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * PageController is responsible for rendering the authentication page.
 *
 * Login logic and Registration logic are handled by its form components in /app/Http/Livewire/Auth/LoginForm.php and /app/Http/Livewire/Auth/RegisterForm.php respectively.
 */
class PageController extends Controller
{
    public function __invoke(): View
    {
        return view('pages.auth');
    }
}
