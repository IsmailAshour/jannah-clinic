<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\DoctorProfile;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DoctorBrowseController extends Controller
{
    public function index(Request $request): Response
    {
        $doctors = DoctorProfile::query()
            ->where('is_bookable', true)
            ->with('user:id,name')
            ->orderBy('display_order')
            ->get(['id', 'user_id', 'specialty', 'bio', 'image_path', 'team_role']);

        return Inertia::render('Public/Doctors', [
            'doctors' => $doctors,
        ]);
    }
}
