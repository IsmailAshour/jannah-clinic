<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupportController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Public/Support', [
            'faqs' => config('clinic.faqs', []),
            'contact' => [
                'phone' => config('clinic.contact.phone'),
                'whatsapp' => config('clinic.contact.whatsapp'),
                'address' => config('clinic.contact.address'),
            ],
        ]);
    }
}
