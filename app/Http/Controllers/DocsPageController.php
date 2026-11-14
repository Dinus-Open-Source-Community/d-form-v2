<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DocsPageController extends Controller
{
    public function __invoke(Request $request)
    {
        return inertia('Docs');
    }
}
