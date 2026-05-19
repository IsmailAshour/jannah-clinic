<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ServiceController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Catalog/Services', [
            'services' => Service::with('category')->orderBy('display_order')->get(),
            'categories' => ServiceCategory::orderBy('display_order')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'category_id' => 'required|exists:service_categories,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'base_price' => 'required|numeric|min:0',
            'duration_minutes' => 'required|integer|min:1',
            'home_service_enabled' => 'boolean',
            'icon_key' => 'nullable|string|max:64',
            'is_active' => 'boolean',
            'display_order' => 'nullable|integer|min:0',
        ]);

        Service::create($data);

        return back();
    }

    public function update(Request $request, Service $service): RedirectResponse
    {
        $data = $request->validate([
            'category_id' => 'required|exists:service_categories,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'base_price' => 'required|numeric|min:0',
            'duration_minutes' => 'required|integer|min:1',
            'home_service_enabled' => 'boolean',
            'icon_key' => 'nullable|string|max:64',
            'is_active' => 'boolean',
            'display_order' => 'nullable|integer|min:0',
        ]);

        $service->update($data);

        return back();
    }

    public function destroy(Service $service): RedirectResponse
    {
        $service->delete();

        return back();
    }
}
