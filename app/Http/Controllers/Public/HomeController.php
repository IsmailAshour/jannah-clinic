<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Public/Home', [
            'featuredServices' => [],
            'featuredDoctor' => null,
            'tip' => null,
        ]);
    }
}
