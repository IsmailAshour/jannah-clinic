<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ServiceCategoryController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Catalog/Categories', [
            'categories' => ServiceCategory::orderBy('display_order')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|alpha_dash|unique:service_categories,slug',
            'color_variant' => 'required|in:brand,gold',
            'display_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        ServiceCategory::create($data);

        return back();
    }

    public function update(Request $request, ServiceCategory $category): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|alpha_dash|unique:service_categories,slug,'.$category->id,
            'color_variant' => 'required|in:brand,gold',
            'display_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        $category->update($data);

        return back();
    }

    public function destroy(ServiceCategory $category): RedirectResponse
    {
        abort_if($category->services()->exists(), 409, 'لا يمكن حذف فئة بها خدمات.');

        $category->delete();

        return back();
    }
}
