<?php

namespace App\Http\Controllers\Portal;

use App\Domain\Notification\Services\NotificationService;
use App\Enums\NotificationCategory;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $category = $request->input('category');
        $onlyUnread = $request->boolean('unread');

        $query = $user->notifications();
        if ($category && in_array($category, NotificationCategory::values(), true)) {
            $query->where('data->category', $category);
        }
        if ($onlyUnread) {
            $query->whereNull('read_at');
        }

        $notifications = $query->orderByDesc('created_at')->paginate(20)->withQueryString();
        $notifications->through(fn (DatabaseNotification $n) => [
            'id' => $n->id,
            'data' => $n->data,
            'read_at' => $n->read_at?->toIso8601String(),
            'created_at' => $n->created_at->toIso8601String(),
        ]);

        return Inertia::render('Portal/Notifications/Index', [
            'feed' => $notifications,
            'filters' => ['category' => $category, 'unread' => $onlyUnread],
            'categories' => NotificationCategory::values(),
        ]);
    }

    public function markRead(Request $request, string $id): RedirectResponse
    {
        /** @var DatabaseNotification|null $n */
        $n = DatabaseNotification::query()->find($id);
        abort_unless($n !== null, 404);
        $this->notifications->markAsRead($n, $request->user());

        return redirect($n->data['action_url'] ?? route('portal.notifications.index'));
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $this->notifications->markAllAsRead($request->user());

        return redirect()->route('portal.notifications.index');
    }
}
