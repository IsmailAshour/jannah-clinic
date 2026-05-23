<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ServiceController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Catalog/Services', [
            'services' => Service::with('category')->orderBy('display_order')->orderBy('id')->get(),
            'categories' => ServiceCategory::orderBy('display_order')->orderBy('id')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatePayload($request);
        $image = $request->file('image');

        $payload = collect($data)->except(['image', 'remove_image'])->all();
        if ($image !== null) {
            $payload['image_path'] = $image->store('services', 'public');
        }

        Service::create($payload);

        return back()->with('success', 'تم إنشاء الخدمة.');
    }

    public function update(Request $request, Service $service): RedirectResponse
    {
        $data = $this->validatePayload($request);
        $image = $request->file('image');
        $removeImage = (bool) ($data['remove_image'] ?? false);

        $payload = collect($data)->except(['image', 'remove_image'])->all();

        if ($image !== null) {
            if ($service->image_path) {
                Storage::disk('public')->delete($service->image_path);
            }
            $payload['image_path'] = $image->store('services', 'public');
        } elseif ($removeImage && $service->image_path) {
            Storage::disk('public')->delete($service->image_path);
            $payload['image_path'] = null;
        }

        $service->update($payload);

        return back()->with('success', 'تم حفظ التعديلات.');
    }

    public function destroy(Service $service): RedirectResponse
    {
        try {
            $imagePath = $service->image_path;
            $service->delete();
            if ($imagePath) {
                Storage::disk('public')->delete($imagePath);
            }
        } catch (QueryException $e) {
            return back()->withErrors(['delete' => 'لا يمكن حذف خدمة مرتبطة بسجلات أخرى.']);
        }

        return back();
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'category_id' => 'required|exists:service_categories,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'content' => 'nullable|string|max:10000',
            'base_price' => 'required|numeric|min:0',
            'duration_minutes' => 'required|integer|in:30,60',
            'home_service_enabled' => 'boolean',
            'online_service_enabled' => 'boolean',
            'is_featured' => 'boolean',
            'icon_key' => 'nullable|string|max:64',
            'is_active' => 'boolean',
            'display_order' => 'nullable|integer|min:0',
            'loyalty_enabled' => ['required', 'boolean'],
            'loyalty_redemption_points' => ['nullable', 'integer', 'min:1'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'remove_image' => ['nullable', 'boolean'],
        ]);
    }
}
