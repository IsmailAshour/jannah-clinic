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

    public function show(Service $service): Response
    {
        abort_unless($service->is_active, 404);

        $service->load([
            'category:id,name,slug,color_variant,icon_key',
            'doctors' => fn ($q) => $q->where('is_bookable', true)
                ->with('user:id,name')
                ->orderBy('doctor_profiles.display_order')
                ->select(['doctor_profiles.id', 'doctor_profiles.user_id', 'doctor_profiles.specialty', 'doctor_profiles.image_path', 'doctor_profiles.team_role', 'doctor_profiles.is_bookable', 'doctor_profiles.display_order']),
        ]);

        $related = Service::query()
            ->where('is_active', true)
            ->where('category_id', $service->category_id)
            ->where('id', '!=', $service->id)
            ->with('category:id,name,slug,color_variant,icon_key')
            ->orderBy('display_order')
            ->limit(4)
            ->get(['id', 'category_id', 'name', 'description', 'base_price', 'duration_minutes', 'image_path']);

        return Inertia::render('Public/ServiceShow', [
            'service' => $service,
            'related' => $related,
        ]);
    }
}
