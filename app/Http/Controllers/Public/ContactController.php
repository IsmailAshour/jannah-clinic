<?php

namespace App\Http\Controllers\Public;

use App\Enums\ContactMessageStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StoreContactMessageRequest;
use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;

class ContactController extends Controller
{
    public function store(StoreContactMessageRequest $request): RedirectResponse
    {
        ContactMessage::create([
            'user_id' => $request->user()?->id,
            'name' => $request->string('name')->trim()->value(),
            'email' => $request->string('email')->trim()->lower()->value(),
            'phone' => $request->filled('phone') ? $request->string('phone')->trim()->value() : null,
            'subject' => $request->string('subject')->trim()->value(),
            'body' => $request->string('body')->trim()->value(),
            'status' => ContactMessageStatus::New,
        ]);

        return back()->with('success', 'تم استلام رسالتك — سنرد عليك قريبًا.');
    }
}
