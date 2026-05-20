<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ServiceBrowseController extends Controller
{
    public function index(Request $request): Response
    {
        $services = Service::query()
            ->where('is_active', true)
            ->with('category:id,name,color_variant')
            ->orderBy('display_order')
            ->get();
        $categories = ServiceCategory::query()->orderBy('id')->get(['id', 'name', 'color_variant']);

        return Inertia::render('Public/Services', [
            'services' => $services,
            'categories' => $categories,
            'filters' => ['category' => $request->input('category')],
        ]);
    }
}
