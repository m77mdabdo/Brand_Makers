<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Country;
use Illuminate\View\View;

class CountryController extends Controller
{
    public function index(): View
    {
        return view('public.countries.index', [
            'countries' => Country::active()->ordered()->get(),
        ]);
    }
}
