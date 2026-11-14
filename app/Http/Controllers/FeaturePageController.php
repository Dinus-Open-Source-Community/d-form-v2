<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class FeaturePageController extends Controller
{
    public function __invoke(Request $request)
    {
        return inertia('Features');
    }
}
