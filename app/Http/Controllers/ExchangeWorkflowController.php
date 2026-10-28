<?php

namespace App\Http\Controllers;

use App\Models\Referral;
use App\Models\ServiceBooking;
use App\Models\ServiceListing;
use App\Services\OperationalAccess;
use App\Services\Payments\Money;
use App\Services\Tenancy;
use App\Services\WorkspaceAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ExchangeWorkflowController extends Controller
{
    public function directory(Request $request): View
    {
        $filters = $request->validate(['q' => 'nullable|string|max:120', 'profession' => 'nullable|string|max:80']);
        $listings = ServiceListing::where('status', 'published')->whereHas('workspace', fn ($q) => $q->where('is_active', true)->where(fn ($w) => $w->where('kind', '!=', 'organisation')->orWhereHas('agency', fn ($a) => $a->where('status', 'active'))))
            ->when($filters['q'] ?? null, fn ($q, $value) => $q->where(fn ($s) => $s->where('name', 'like', '%'.$value.'%')->orWhere('location', 'like', '%'.$value.'%')))
            ->when($filters['profession'] ?? null, fn ($q, $value) => $q->where('profession', $value))->paginate(18)->withQueryString();

        return view('marketplace.services', compact('listings', 'filters'));
    }

    public function inbox(Request $request, string $entity, WorkspaceAccess $access): View
    {
        abort_unless(in_array($entity, ['referrals', 'bookings'], true), 404);
        $module = $entity === 'referrals' ? 'referrals' : 'services';
        abort_unless($request->user()->hasPermission($module.'.view'), 403);
        $actor = $request->user();
        if ($entity === 'referrals') {
            $query = Referral::where('target_workspace_id', $actor->currentWorkspace()->id)->where('sharing_consent', true);
            if (! in_array($access->scope($actor, 'referrals.view'), ['organisation', 'platform'], true)) {
                $query->whereRaw('1=0');
            }
        } else {
            $visible = $access->records(ServiceListing::query(), $actor, 'services.view')->select('id');
            $query = ServiceBooking::where('sharing_consent', true)->whereIn('service_listing_id', $visible);
        }
        $records = $query->latest()->paginate(25);

        return view('operations.inbox', compact('records', 'entity'));
    }

    public function respond(Request $request, string $entity, int $record, OperationalAccess $operations, WorkspaceAccess $access): RedirectResponse
    {
        abort_unless(in_array($entity, ['referrals', 'bookings'], true), 404);
        $data = $request->validate(['status' => 'required|string', 'quote' => 'nullable|string', 'response' => 'nullable|string|max:3000']);
        DB::transaction(function () use ($request, $entity, $record, $operations, $access, $data): void {
            $actor = $request->user();
            $module = $entity === 'referrals' ? 'referrals' : 'services';
            abort_unless($actor->hasPermission($module.'.edit'), 403);
            if ($entity === 'referrals') {
                $row = Referral::lockForUpdate()->findOrFail($record);
                $source = $operations->query('referrals', $actor, 'edit')->whereKey($record)->exists();
                $receiver = $row->target_workspace_id === $actor->currentWorkspace()->id && in_array($access->scope($actor, 'referrals.edit'), ['organisation', 'platform'], true);
                $allowed = $receiver && $row->status === 'submitted' ? ['accepted', 'declined'] : ($source ? match ($row->status) {
                    'submitted' => ['cancelled'],'accepted' => ['completed', 'cancelled'],default => []
                } : []);
            } else {
                $row = ServiceBooking::lockForUpdate()->findOrFail($record);
                $source = $operations->query('bookings', $actor, 'edit')->whereKey($record)->exists();
                $receiver = $row->sharing_consent && $access->records(ServiceListing::query(), $actor, 'services.edit')->whereKey($row->service_listing_id)->exists();
                $allowed = [];
                if ($receiver) {
                    $allowed = match ($row->status) {
                        'requested' => ['quoted', 'declined'],'accepted' => ['completed'],default => []
                    };
                }
                if ($source) {
                    $allowed = array_merge($allowed, match ($row->status) {
                        'requested' => ['cancelled'],'quoted' => ['accepted', 'cancelled'],'accepted' => ['cancelled'],default => []
                    });
                }
                if ($receiver && $data['status'] === 'quoted') {
                    try {
                        $quote = Money::minor($data['quote'] ?? '', $row->currency, $row->minor_unit);
                    } catch (\InvalidArgumentException $e) {
                        throw ValidationException::withMessages(['quote' => $e->getMessage()]);
                    }
                    $row->quote_minor = $quote;
                    $row->response = $data['response'] ?? null;
                }
            }
            abort_unless($source || $receiver, 404);
            if (! in_array($data['status'], $allowed, true)) {
                throw ValidationException::withMessages(['status' => 'That action is not available at this stage or for this workspace.']);
            }
            $row->status = $data['status'];
            $row->save();
            app(Tenancy::class)->audit($actor, $entity.'.'.$data['status'], (string) $row->id);
        });

        return back()->with('status', 'Workflow updated.');
    }
}
