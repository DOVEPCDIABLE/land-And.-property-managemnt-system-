<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class CompanyController extends Controller
{
    public function show(string $page): View
    {
        abort_unless(in_array($page, ['about', 'founder', 'workspaces'], true), 404);

        return view('marketplace.company', ['page' => $page, 'company' => config('company')]);
    }
}
