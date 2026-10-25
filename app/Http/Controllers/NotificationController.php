<?php

namespace App\Http\Controllers;

use App\Notifications\SystemNotification;
use App\Services\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request, Tenancy $tenancy): View
    {
        return view('notifications', ['notifications' => $request->user()->workspaceNotifications()->paginate(20), 'recipients' => $request->user()->hasPermission('notifications.send') ? $tenancy->recipients($request->user(), 'notifications.send')->get() : collect()]);
    }

    public function read(Request $request, string $notification): RedirectResponse
    {
        $request->user()->workspaceNotifications()->findOrFail($notification)->markAsRead();

        return back();
    }

    public function store(Request $request, Tenancy $tenancy): RedirectResponse
    {
        $data = $request->validate(['user_id' => 'required|integer', 'subject' => 'required|string|max:160', 'message' => 'required|string|max:5000', 'channels' => 'required|array|min:1', 'channels.*' => 'required|in:database,mail']);
        $user = $tenancy->recipients($request->user(), 'notifications.send')->findOrFail($data['user_id']);
        $user->notify(new SystemNotification($data['subject'], $data['message'], array_values(array_unique($data['channels'])), $request->user()->currentWorkspace()->kind === 'platform' ? null : $request->user()->currentWorkspace()->id));
        $tenancy->audit($request->user(), 'notification.queued', (string) $user->id);

        return back()->with('status', 'Notification queued.');
    }
}
