<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ContactMessageStatus;
use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ContactMessageController extends Controller
{
    public function index(Request $request): Response
    {
        $q = (string) $request->input('q', '');
        $status = (string) $request->input('status', '');

        $query = ContactMessage::query()->orderByDesc('id');

        if ($q !== '') {
            $like = '%'.$q.'%';
            $query->where(function ($w) use ($like) {
                $w->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('subject', 'like', $like);
            });
        }

        if ($status !== '' && in_array($status, array_column(ContactMessageStatus::cases(), 'value'), true)) {
            $query->where('status', $status);
        }

        $messages = $query->paginate(20)->withQueryString();

        $base = ContactMessage::query();
        $stats = [
            'total' => (clone $base)->count(),
            'new' => (clone $base)->where('status', ContactMessageStatus::New)->count(),
            'replied' => (clone $base)->where('status', ContactMessageStatus::Replied)->count(),
            'this_week' => (clone $base)->where('created_at', '>=', now()->subDays(7))->count(),
        ];

        return Inertia::render('Admin/Messages/Index', [
            'messages' => $messages,
            'filters' => $request->only(['q', 'status']),
            'stats' => $stats,
        ]);
    }

    public function show(Request $request, ContactMessage $message): Response
    {
        if ($message->status === ContactMessageStatus::New) {
            $message->forceFill([
                'status' => ContactMessageStatus::Read,
                'read_at' => now(),
            ])->save();
        }

        return Inertia::render('Admin/Messages/Show', [
            'message' => $message->load(['user:id,name', 'handler:id,name']),
        ]);
    }

    public function updateStatus(Request $request, ContactMessage $message): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_column(ContactMessageStatus::cases(), 'value'))],
        ]);

        $next = ContactMessageStatus::from($data['status']);

        $patch = ['status' => $next];
        if ($next === ContactMessageStatus::Replied && $message->replied_at === null) {
            $patch['replied_at'] = now();
            $patch['handled_by'] = $request->user()->id;
        }
        if ($next === ContactMessageStatus::Read && $message->read_at === null) {
            $patch['read_at'] = now();
        }

        $message->forceFill($patch)->save();

        return back()->with('success', 'تم تحديث حالة الرسالة.');
    }

    public function destroy(ContactMessage $message): RedirectResponse
    {
        $message->delete();

        return redirect()->route('admin.messages.index')->with('success', 'تم حذف الرسالة.');
    }
}
