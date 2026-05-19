<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\ServiceCategory;
use Inertia\Inertia;
use Inertia\Response;

class ServiceBrowseController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Portal/Services/Index', [
            'categories' => ServiceCategory::where('is_active', true)
                ->with(['services' => fn ($q) => $q->where('is_active', true)->orderBy('display_order')])
                ->orderBy('display_order')
                ->get(),
        ]);
    }
}
