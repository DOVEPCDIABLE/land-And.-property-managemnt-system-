<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Services\OperationalAccess;
use App\Services\OperationalService;
use App\Services\Payments\Money;
use App\Services\Tenancy;
use App\Services\WorkspaceAccess;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OperationalController extends Controller
{
    public function index(Request $request, string $entity, OperationalAccess $access): View
    {
        $d = $access->definition($entity);
        abort_unless($request->user()->hasPermission($d['module'].'.view'), 403);
        $filters = $request->validate(['q' => 'nullable|string|max:150', 'status' => 'nullable|string|max:30', 'on' => 'nullable|date_format:Y-m-d']);
        $query = $access->query($entity, $request->user());
        if (! empty($filters['q'])) {
            $query->where('name', 'like', '%'.$filters['q'].'%');
        }
        if (isset($d['states']) && ! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if ($request->filled('property_id') && isset($d['fields']['property_id'])) {
            $request->validate(['property_id' => 'integer']);
            $query->where('property_id', $request->input('property_id'));
        }
        if ($entity === 'appointments' && ! empty($filters['on'])) {
            $day = CarbonImmutable::parse($filters['on'], config('ecosystem.display_timezone'));
            $query->whereBetween('starts_at', [$day->startOfDay()->utc(), $day->endOfDay()->utc()]);
        }
        $records = ($entity === 'appointments' ? $query->orderBy('starts_at') : $query->latest())->paginate(25)->withQueryString();
        $metrics = [];
        if (in_array($entity, ['properties', 'units', 'leases'], true)) {
            $permission = $d['module'].'.view';
            $actor = $request->user();
            $properties = app(WorkspaceAccess::class)->records(Property::query(), $actor, $permission)->count();
            $units = app(WorkspaceAccess::class)->records(PropertyUnit::query(), $actor, $permission);
            $occupied = app(WorkspaceAccess::class)->records(Lease::query(), $actor, $permission)->where('status', 'active')->whereDate('starts_on', '<=', today())->whereDate('ends_on', '>=', today());
            $metrics = ['Properties' => $properties, 'Units' => $units->count(), 'Occupied units' => (clone $occupied)->whereNotNull('unit_id')->distinct()->count('unit_id'), 'Active tenancies' => $occupied->count()];
        }

        return view('operations.index', compact('d', 'entity', 'records', 'filters', 'metrics'));
    }

    public function create(Request $request, string $entity, OperationalAccess $access): View
    {
        $d = $access->definition($entity);
        abort_unless($request->user()->hasPermission($d['module'].'.create'), 403);

        abort_if($d['readonly'] ?? false, 403);
        $defaults = [];
        if ($entity === 'payroll' && $request->filled('staff_member_id')) {
            $staff = $access->record('staff', (int) $request->input('staff_member_id'), $request->user());
            $defaults = ['staff_member_id' => $staff->id, 'gross' => Money::major($staff->salary_minor, $staff->currency, $staff->minor_unit), 'currency' => $staff->currency, 'period' => today()->format('Y-m'), 'name' => 'Payroll for '.$staff->name];
        }
        if ($entity === 'invoices' && $request->filled('lease_id')) {
            $lease = app(WorkspaceAccess::class)->records(Lease::query(), $request->user(), 'payments.create')->findOrFail((int) $request->input('lease_id'));
            $defaults = ['lease_id' => $lease->id, 'contact_id' => $lease->contact_id, 'property_id' => $lease->property_id, 'currency' => $lease->currency, 'name' => 'Rent for '.$lease->name, 'issued_on' => today()->format('Y-m-d'), 'due_on' => today()->addDays(14)->format('Y-m-d'), 'tax_percent' => '0', 'items' => [['description' => $lease->period.' rent', 'quantity' => 1, 'unit_price' => Money::major($lease->rent_minor, $lease->currency, $lease->minor_unit)]]];
        }

        return view('operations.form', ['defaults' => $defaults, 'd' => $d, 'entity' => $entity, 'record' => null, 'choices' => $access->choices($entity, $request->user())]);
    }

    public function store(Request $request, string $entity, OperationalService $service): RedirectResponse
    {
        $record = $service->save($request, $entity);

        return redirect()->route('operations.show', [$entity, $record->id])->with('status', 'Record created.');
    }

    public function show(Request $request, string $entity, int $record, OperationalAccess $access): View
    {
        $record = $access->record($entity, $record, $request->user());
        $d = $access->definition($entity);

        return view('operations.show', compact('record', 'entity', 'd'));
    }

    public function edit(Request $request, string $entity, int $record, OperationalAccess $access): View
    {
        $record = $access->record($entity, $record, $request->user(), 'edit');
        $d = $access->definition($entity);
        abort_if($d['readonly'] ?? false, 403);

        return view('operations.form', ['record' => $record, 'entity' => $entity, 'd' => $d, 'choices' => $access->choices($entity, $request->user())]);
    }

    public function update(Request $request, string $entity, int $record, OperationalAccess $access, OperationalService $service): RedirectResponse
    {
        $service->save($request, $entity, $access->record($entity, $record, $request->user(), 'edit'));

        return redirect()->route('operations.show', [$entity, $record])->with('status', 'Record updated.');
    }

    public function destroy(Request $request, string $entity, int $record, OperationalAccess $access, Tenancy $tenancy): RedirectResponse
    {
        $model = $access->record($entity, $record, $request->user(), 'delete');
        abort_unless(in_array($entity, ['contacts', 'documents', 'services', 'properties'], true), 422, 'Keep operational and financial history; close records using their status instead.');
        if ($entity === 'contacts' && (Lease::where('contact_id', $model->id)->where('status', 'active')->exists() || Invoice::where('contact_id', $model->id)->whereIn('status', ['draft', 'issued'])->exists())) {
            abort(422, 'Retain contacts used by active leases or invoices.');
        }
        if ($entity === 'properties') {
            $model->update(['publication_status' => 'archived']);
        } else {
            $model->delete();
        }
        $tenancy->audit($request->user(), $entity.'.archived', (string) $record);

        return redirect()->route('operations.index', $entity)->with('status', 'Record archived.');
    }

    public function download(Request $request, int $document, OperationalAccess $access): StreamedResponse
    {
        $record = $access->record('documents', $document, $request->user());
        abort_unless(Storage::disk('local')->exists($record->path), 404);

        return Storage::disk('local')->download($record->path, basename($record->original_name), ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }

    public function export(Request $request, string $entity, OperationalAccess $access): StreamedResponse
    {
        $d = $access->definition($entity);
        abort_unless($request->user()->hasPermission($d['module'].'.export'), 403);
        $query = $access->query($entity, $request->user(), 'export');
        app(Tenancy::class)->audit($request->user(), $entity.'.exported', 'csv');

        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ID', 'Name', 'Status', 'Currency', 'Amount', 'Created'], ',', '"', '');
            foreach ($query->orderBy('id')->cursor() as $row) {
                $amount = '';
                foreach (['total_minor', 'rent_minor', 'net_minor', 'salary_minor', 'budget_minor', 'price_minor', 'amount_minor', 'cost_minor', 'fee_minor', 'quote_minor'] as $column) {
                    if (isset($row->$column)) {
                        $amount = Money::major($row->$column, $row->currency, $row->minor_unit);
                        break;
                    }
                }
                $values = [$row->id, $row->name, $row->status ?? '', $row->currency ?? '', $amount, $row->created_at->toIso8601String()];
                fputcsv($out, array_map(fn ($v) => preg_match('/^[\s]*[=+\-@]/', (string) $v) ? "'".$v : $v, $values), ',', '"', '');
            }
            fclose($out);
        }, $entity.'.csv', ['Content-Type' => 'text/csv']);
    }

    public function finance(Request $request, WorkspaceAccess $access): View
    {
        abort_unless($request->user()->hasPermission('payments.view'), 403);
        $actor = $request->user();
        $revenue = $access->records(Payment::query(), $actor, 'payments.view')->whereIn('status', ['recorded', 'paid'])->selectRaw('currency, minor_unit, SUM(amount_minor) as total')->groupBy('currency', 'minor_unit')->get();
        $expenses = $access->records(Expense::query(), $actor, 'payments.view')->where('status', 'recorded')->selectRaw('currency, minor_unit, SUM(amount_minor) as total')->groupBy('currency', 'minor_unit')->get();
        $invoices = $access->records(Invoice::query(), $actor, 'payments.view')->where('status', 'issued')->withSum('receipts', 'amount_minor')->get();

        return view('operations.finance', compact('revenue', 'expenses', 'invoices'));
    }
}
