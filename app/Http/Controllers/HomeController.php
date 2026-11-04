<?php

namespace App\Http\Controllers;

use App\Models\Service;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    /**
     * The public landing page of the studio, with its service catalogue.
     */
    public function __invoke(): View
    {
        return view('welcome', [
            'services' => Service::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }
}
