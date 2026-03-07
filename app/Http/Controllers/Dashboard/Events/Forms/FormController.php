<?php

namespace App\Http\Controllers\Dashboard\Events\Forms;

use App\Http\Controllers\Controller;
use App\Models\Form;

class FormController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('pages.dashboard.events.forms.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('pages.dashboard.events.forms.create');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $form = Form::query()->where('id', $id);

        // if (auth()->guard()->user())
        $form = $form->withTrashed()->get()->first();
        //}

        return view('pages.dashboard.events.forms.view', compact('form'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Form $form)
    {
        return view('pages.dashboard.events.forms.edit', compact('form'));
    }
}
