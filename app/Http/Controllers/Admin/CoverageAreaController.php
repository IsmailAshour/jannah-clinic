<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HomeServiceCoverageArea;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CoverageAreaController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Coverage/Index', [
            'areas' => HomeServiceCoverageArea::orderBy('display_order')->orderBy('id')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'is_active' => 'boolean',
            'display_order' => 'nullable|integer',
        ]);

        HomeServiceCoverageArea::create($data);

        return back()->with('success', 'تمت إضافة المنطقة.');
    }

    public function update(Request $request, HomeServiceCoverageArea $area): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'is_active' => 'boolean',
            'display_order' => 'nullable|integer',
        ]);

        $area->update($data);

        return back()->with('success', 'تم تحديث المنطقة.');
    }

    public function destroy(HomeServiceCoverageArea $area): RedirectResponse
    {
        try {
            $area->delete();
        } catch (QueryException $e) { // @phpstan-ignore catch.neverThrown (FK constraint — thrown at runtime by Postgres when T6 adds ServiceAddress.coverage_area_id restrictOnDelete)
            return back()->withErrors(['delete' => 'لا يمكن حذف منطقة مرتبطة بحجوزات.']);
        }

        return back();
    }
}
