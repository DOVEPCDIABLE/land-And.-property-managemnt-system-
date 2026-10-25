<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFollowUpRequest;
use App\Models\FollowUp;
use App\Services\Tenancy;
use App\Services\WorkspaceAccess;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FollowUpController extends Controller
{
    public function index(Request $request, Tenancy $tenancy): View
    {
        $recipients = $tenancy->recipients($request->user(), 'followups.create')->get();

        return view('followups', ['followups' => $tenancy->records(FollowUp::query(), $request->user())->with('user')->latest()->paginate(20), 'recipients' => $recipients]);
    }

    public function store(StoreFollowUpRequest $request, Tenancy $tenancy): RedirectResponse
    {
        $data = $request->validated();
        $actor = $request->user();
        $recipient = $tenancy->recipients($actor, 'followups.create')->findOrFail($data['user_id']);
        abort_unless(! in_array(app(WorkspaceAccess::class)->scope($actor, 'followups.create'), ['own', 'assigned'], true) || $recipient->id === $actor->id, 403);
        $followup = FollowUp::create([...$data, 'due_at' => CarbonImmutable::parse($data['due_at'], config('ecosystem.display_timezone'))->utc(), ...$tenancy->attributes($actor, $recipient), 'created_by' => $actor->id]);
        $tenancy->audit($actor, 'followup.created', (string) $followup->id);

        return back()->with('status', 'Follow-up scheduled.');
    }

    public function cancel(Request $request, string $followup, Tenancy $tenancy): RedirectResponse
    {
        $item = app(WorkspaceAccess::class)->records(FollowUp::query(), $request->user(), 'followups.edit')->findOrFail($followup);
        $changed = $item->whereKey($item->id)->where('status', 'pending')->update(['status' => 'cancelled']);
        if (! $changed) {
            return back()->with('info', 'This follow-up is already queued or completed and cannot be cancelled here.');
        }
        $tenancy->audit($request->user(), 'followup.cancelled', (string) $item->id);

        return back()->with('status', 'Pending follow-up cancelled.');
    }
}
