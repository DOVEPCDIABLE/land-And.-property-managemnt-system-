<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Contact;
use App\Models\DevelopmentProject;
use App\Models\Expense;
use App\Models\Lease;
use App\Models\OperationalModel;
use App\Models\PayrollEntry;
use App\Models\Property;
use App\Models\PropertyMedia;
use App\Models\PropertyUnit;
use App\Models\ServiceListing;
use App\Models\StaffMember;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Payments\Money;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class OperationalService
{
    public function validate(Request $request, string $entity, ?OperationalModel $record = null): array
    {
        $d = app(OperationalAccess::class)->definition($entity);
        $rules = [];
        foreach ($d['fields'] as $key => $field) {
            $required = ($field['required'] ?? false) && ! ($record && $field['type'] === 'file');
            $base = [$required ? 'required' : 'nullable'];
            $type = $field['type'];
            $extra = match ($type) {
                'email' => ['email', 'max:255'], 'textarea' => ['string', 'max:10000'],
                'date' => ['date_format:Y-m-d'], 'datetime-local' => ['date'], 'month' => ['date_format:Y-m'],
                'integer' => ['integer', 'min:0', 'max:10000'], 'progress' => ['integer', 'min:0', 'max:100'],
                'decimal' => ['numeric', 'min:0', 'max:99999999999'], 'percent' => ['numeric', 'between:0,100', 'decimal:0,4'],
                'money' => ['string', 'regex:/^\d{1,15}(?:\.\d{1,8})?$/D'],
                'currency' => [Rule::in(array_keys(app(CurrencyService::class)->enabled()))],
                'select' => [Rule::in(array_keys($field['options']))], 'checkbox' => ['boolean'],'consent' => ['accepted'],
                'reference','organisation','public_service','member' => ['integer', 'min:1'],
                'file' => ['file', 'mimes:pdf,doc,docx,jpg,jpeg,png,webp', 'max:10240'],
                'media' => ['array', 'max:12'], 'items' => ['array', 'min:1', 'max:100'],
                'slug' => ['string', 'max:180', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', Rule::unique('properties', 'slug')->ignore($record?->id)],
                'tags' => ['nullable', 'string', 'max:1000'], default => ['string', 'max:255'],
            };
            $rules[$key] = [...$base, ...$extra];
        }
        if (isset($d['states'])) {
            $rules['status'] = ['sometimes', Rule::in($d['states'])];
        }
        $rules['assigned_to'] = ['nullable', 'integer'];
        if ($entity === 'invoices') {
            $rules['items.*.description'] = ['required', 'string', 'max:255'];
            $rules['items.*.quantity'] = ['required', 'integer', 'between:1,100000'];
            $rules['items.*.unit_price'] = ['required', 'string', 'regex:/^\d{1,15}(?:\.\d{1,8})?$/D'];
        }
        if ($entity === 'properties') {
            $rules['uploads.*'] = ['file', 'mimes:jpg,jpeg,png,webp,mp4', 'max:30720'];
        }

        return $request->validate($rules);
    }

    public function save(Request $request, string $entity, ?OperationalModel $record = null): OperationalModel
    {
        $d = app(OperationalAccess::class)->definition($entity);
        $actor = $request->user();
        abort_unless($actor->hasPermission($d['module'].'.'.($record ? 'edit' : 'create')), 403);
        abort_if($d['readonly'] ?? false, 403);
        $data = $this->validate($request, $entity, $record);
        $newFiles = [];
        try {
            return DB::transaction(function () use ($request, $entity, $record, $data, $d, $actor, &$newFiles): OperationalModel {
                if ($record) {
                    $record = app(OperationalAccess::class)->query($entity, $actor, 'edit')->lockForUpdate()->findOrFail($record->id);
                }
                $payload = collect($data)->except(['uploads', 'file', 'assigned_to'])->all();
                if ($entity === 'contacts' && ! empty($payload['linked_user_id'])) {
                    $actor->currentWorkspace()->memberships()->where('is_active', true)->where('user_id', $payload['linked_user_id'])->firstOrFail();
                }
                if (in_array($entity, ['leases', 'invoices'], true)) {
                    $payload['tenant_user_id'] = Contact::findOrFail($payload['contact_id'])->linked_user_id;
                }
                foreach ($d['fields'] as $key => $field) {
                    if ($field['type'] === 'reference' && ! empty($data[$key])) {
                        $model = app(OperationalAccess::class)->definition($field['entity'])['model'];
                        app(WorkspaceAccess::class)->records($model::query(), $actor, $d['module'].'.'.($record ? 'edit' : 'create'))->findOrFail($data[$key]);
                    }
                    if ($field['type'] === 'checkbox') {
                        $payload[$key] = $request->boolean($key);
                    }
                    if ($field['type'] === 'tags') {
                        $payload[$key] = array_values(array_filter(array_map('trim', explode(',', $data[$key] ?? ''))));
                    }
                    if ($field['type'] === 'datetime-local' && ! empty($data[$key])) {
                        $payload[$key] = CarbonImmutable::parse($data[$key], config('ecosystem.display_timezone'))->utc();
                    }
                    if ($field['type'] === 'money') {
                        try {
                            $payload[$field['column']] = Money::minor($data[$key] ?? '0', $data['currency']);
                        } catch (InvalidArgumentException $e) {
                            throw ValidationException::withMessages([$key => $e->getMessage()]);
                        }
                        unset($payload[$key]);
                    }
                }
                if (isset($data['currency'])) {
                    $payload['minor_unit'] = app(CurrencyService::class)->minorUnit($data['currency']);
                }
                $attributes = $record ? $record->only(['workspace_id', 'user_id', 'branch_id', 'team_id', 'assigned_to']) : [...app(Tenancy::class)->attributes($actor), 'user_id' => $actor->id];
                unset($attributes['agency_id']);
                if (array_key_exists('assigned_to', $data)) {
                    $assigned = (int) ($data['assigned_to'] ?? 0);
                    if ($assigned && $assigned !== $actor->id) {
                        abort_unless($actor->hasPermission($d['module'].'.assign'), 403);
                        $member = $actor->currentWorkspace()->memberships()->where('user_id', $assigned)->where('is_active', true)->firstOrFail();
                        $scope = app(WorkspaceAccess::class)->scope($actor, $d['module'].'.assign');
                        abort_unless(! in_array($scope, ['own', 'assigned'], true) && ($scope !== 'branch' || $member->branch_id === $actor->currentMembership()->branch_id) && ($scope !== 'team' || $member->team_id === $actor->currentMembership()->team_id), 403);
                    }
                    $attributes['assigned_to'] = $assigned ?: null;
                    if (isset($member)) {
                        $attributes['branch_id'] = $member->branch_id;
                        $attributes['team_id'] = $member->team_id;
                    }
                }
                if (! empty($payload['property_id'])) {
                    $parent = Property::findOrFail($payload['property_id']);
                    $attributes['branch_id'] = $parent->branch_id ?? $attributes['branch_id'];
                    $attributes['team_id'] = $parent->team_id ?? $attributes['team_id'];
                }
                $payload = [...$payload, ...$attributes];
                if (isset($d['initial'])) {
                    $payload['status'] = $data['status'] ?? $record?->status ?? $d['initial'];
                }
                if (! empty($payload['unit_id'])) {
                    $unit = PropertyUnit::findOrFail($payload['unit_id']);
                    if ($unit->property_id !== (int) $payload['property_id']) {
                        throw ValidationException::withMessages(['unit_id' => 'Select a unit belonging to this property.']);
                    }
                }
                if (! empty($payload['lease_id']) && ! empty($payload['property_id']) && Lease::findOrFail($payload['lease_id'])->property_id !== (int) $payload['property_id']) {
                    throw ValidationException::withMessages(['lease_id' => 'Lease and property must match.']);
                }
                if ($entity === 'properties' && ($payload['publication_status'] !== ($record?->publication_status ?? 'draft'))) {
                    abort_unless($actor->hasPermission('properties.publish'), 403);
                }
                if ($entity === 'services' && $payload['status'] !== ($record?->status ?? 'draft')) {
                    abort_unless($actor->hasPermission('provider.publish'), 403);
                }
                if ($record && in_array($entity, ['referrals', 'bookings'], true)) {
                    abort_unless(in_array($record->status, ['submitted', 'requested'], true), 422, 'This shared workflow is already being processed.');
                    $payload['status'] = $record->status;
                }
                if ($entity === 'units') {
                    if ($record && $record->property_id !== (int) $payload['property_id'] && Lease::where('unit_id', $record->id)->exists()) {
                        throw ValidationException::withMessages(['property_id' => 'Units with tenancy history cannot be moved to a different property.']);
                    }
                    if ($payload['status'] === 'sold' && (empty($payload['buyer_name']) || empty($payload['sold_on']) || $payload['sale_price_minor'] <= 0)) {
                        throw ValidationException::withMessages(['status' => 'Enter buyer, sale date and agreed sale price before marking a unit sold.']);
                    }
                }
                if ($entity === 'leases') {
                    $this->lease($payload, $record);
                }
                if ($entity === 'inspections' && $payload['status'] === 'completed' && empty($payload['findings'])) {
                    throw ValidationException::withMessages(['findings' => 'Record findings before completing an inspection.']);
                }
                if ($entity === 'invoices') {
                    $payload = $this->invoice($payload, $record);
                }
                if ($entity === 'payroll') {
                    $payload = $this->payroll($payload, $record, $actor);
                }
                if ($entity === 'expenses' && $record?->payroll_entry_id) {
                    abort(422, 'Payroll expenses are maintained through payroll.');
                }
                if ($entity === 'appointments') {
                    $this->appointment($payload, $record, $actor);
                }
                if ($entity === 'referrals') {
                    Workspace::where('kind', 'organisation')->where('is_active', true)->whereHas('agency', fn ($q) => $q->where('status', 'active'))->whereKeyNot($actor->currentWorkspace()->id)->findOrFail($payload['target_workspace_id']);
                    $payload['sharing_consent'] = true;
                    if (! $record) {
                        $payload['status'] = 'submitted';
                    }
                }
                if ($entity === 'bookings') {
                    $listing = ServiceListing::with('workspace')->where('status', 'published')->findOrFail($payload['service_listing_id']);
                    abort_unless($listing->workspace->available(), 404);
                    if (! $record) {
                        $payload['status'] = 'requested';
                        $payload['sharing_consent'] = true;
                        $payload['requester_snapshot'] = $actor->only(['name', 'email']);
                        $payload['currency'] = $listing->currency;
                        $payload['minor_unit'] = $listing->minor_unit;
                    }
                }
                if ($entity === 'projects' && ! empty($payload['target_date']) && ! empty($payload['starts_on']) && $payload['target_date'] < $payload['starts_on']) {
                    throw ValidationException::withMessages(['target_date' => 'Completion date must follow the start date.']);
                }
                if ($entity === 'progress') {
                    $project = DevelopmentProject::whereKey($payload['project_id'])->lockForUpdate()->firstOrFail();
                    if ($payload['currency'] !== $project->currency) {
                        throw ValidationException::withMessages(['currency' => 'Use the project budget currency for progress costs.']);
                    }
                    if ($record) {
                        abort(422, 'Progress logs are append-only. Add a corrective update instead.');
                    }
                }
                if ($entity === 'documents' && $request->hasFile('file')) {
                    $file = $request->file('file');
                    $path = $file->store('documents', 'local');
                    if (! $path) {
                        throw new \RuntimeException('Document storage failed.');
                    }
                    $newFiles[] = ['local', $path];
                    $payload = [...$payload, 'path' => $path, 'original_name' => $file->getClientOriginalName(), 'mime_type' => $file->getMimeType(), 'size' => $file->getSize()];
                }
                $model = $record ?? new $d['model'];
                $model->fill($payload);
                $model->save();
                if ($entity === 'properties') {
                    foreach ($request->file('uploads', []) as $file) {
                        $name = $file->hashName();
                        $directory = public_path('media/properties');
                        if (! is_dir($directory)) {
                            mkdir($directory, 0755, true);
                        }
                        $mime = $file->getMimeType();
                        $file->move($directory, $name);
                        $newFiles[] = ['public_file', $directory.'/'.$name];
                        PropertyMedia::create([...$attributes, 'name' => $file->getClientOriginalName(), 'property_id' => $model->id, 'path' => 'media/properties/'.$name, 'mime_type' => $mime, 'kind' => str_starts_with($mime, 'video/') ? 'video' : 'image', 'is_cover' => ! $model->media()->where('kind', 'image')->exists() && ! str_starts_with($mime, 'video/'), 'is_public' => true]);
                    }
                }
                if ($entity === 'progress') {
                    $project->update(['progress' => $payload['progress'], 'status' => (int) $payload['progress'] === 100 ? 'completed' : ($payload['progress'] > 0 ? 'in_progress' : $project->status)]);
                }
                if ($entity === 'payroll' && $model->status === 'paid') {
                    Expense::firstOrCreate(['payroll_entry_id' => $model->id], [...$attributes, 'name' => $model->name, 'category' => 'salary', 'incurred_on' => $model->paid_on, 'amount_minor' => $model->net_minor, 'currency' => $model->currency, 'minor_unit' => $model->minor_unit, 'status' => 'recorded']);
                }
                app(Tenancy::class)->audit($actor, $entity.'.'.($record ? 'updated' : 'created'), (string) $model->id);

                return $model;
            });
        } catch (\Throwable $e) {
            foreach ($newFiles as [$disk,$path]) {
                if ($disk === 'public_file') {
                    @unlink($path);
                } else {
                    Storage::disk($disk)->delete($path);
                }
            }
            if ($e instanceof MathException) {
                throw ValidationException::withMessages(['amount' => 'The calculated total is too large. Reduce the amount or quantity.']);
            }
            throw $e;
        }
    }

    private function lease(array $p, ?OperationalModel $record): void
    {
        if ($p['ends_on'] < $p['starts_on']) {
            throw ValidationException::withMessages(['ends_on' => 'Lease end must follow its start.']);
        }
        $property = Property::whereKey($p['property_id'])->lockForUpdate()->firstOrFail();
        if ($record && in_array($record->status, ['ended', 'cancelled'], true) && $p['status'] !== $record->status) {
            throw ValidationException::withMessages(['status' => 'Closed leases cannot be reactivated.']);
        }
        if ($p['status'] === 'active') {
            if ($property->units()->exists() && empty($p['unit_id'])) {
                throw ValidationException::withMessages(['unit_id' => 'Select a unit for this multi-unit property.']);
            }
            $unit = ! empty($p['unit_id']) ? PropertyUnit::findOrFail($p['unit_id']) : null;
            if (in_array($unit?->status ?? $property->status, ['sold', 'unavailable'], true)) {
                throw ValidationException::withMessages(['property_id' => 'This property or unit is unavailable for a lease.']);
            }
            $conflict = Lease::where('property_id', $property->id)->where('status', 'active')->where('starts_on', '<=', $p['ends_on'])->where('ends_on', '>=', $p['starts_on'])->when($record, fn ($q) => $q->whereKeyNot($record->id))->when($unit, fn ($q) => $q->where(fn ($u) => $u->whereNull('unit_id')->orWhere('unit_id', $unit->id)))->exists();
            if ($conflict) {
                throw ValidationException::withMessages(['starts_on' => 'An active lease already overlaps these dates.']);
            }
        }
    }

    private function invoice(array $p, ?OperationalModel $record): array
    {
        if ($record && $record->status !== 'draft') {
            if ($p['status'] === 'void' && $record->balanceMinor() === $record->total_minor) {
                return [...$record->getAttributes(), 'status' => 'void'];
            }
            throw ValidationException::withMessages(['status' => 'Issued invoices cannot be edited. Only unpaid invoices can be voided.']);
        }
        if ($p['due_on'] < $p['issued_on']) {
            throw ValidationException::withMessages(['due_on' => 'Due date must not precede the invoice date.']);
        }
        $items = [];
        $subtotal = BigDecimal::of('0');
        foreach ($p['items'] as $item) {
            try {
                $unit = Money::minor($item['unit_price'], $p['currency']);
            } catch (InvalidArgumentException $e) {
                throw ValidationException::withMessages(['items' => $e->getMessage()]);
            }
            $line = BigDecimal::of((string) $unit)->multipliedBy((string) $item['quantity'])->toInt();
            $items[] = ['description' => $item['description'], 'quantity' => (int) $item['quantity'], 'unit_minor' => $unit, 'line_minor' => $line];
            $subtotal = $subtotal->plus((string) $line);
        }
        $tax = $subtotal->multipliedBy((string) $p['tax_percent'])->dividedBy('100', 0, RoundingMode::HalfUp);
        if (! $subtotal->isGreaterThan('0')) {
            throw ValidationException::withMessages(['items' => 'Invoice total must be greater than zero.']);
        }
        if ($p['status'] === 'issued') {
            $p['customer_snapshot'] = Contact::findOrFail($p['contact_id'])->only(['name', 'email', 'phone']);
            $settings = app(PlatformSettings::class);
            $workspace = auth()->user()->currentWorkspace();
            $p['issuer_snapshot'] = ['name' => $workspace->kind === 'organisation' ? $workspace->name : $settings->get('company_name'), 'address' => $workspace->kind === 'organisation' ? '' : $settings->get('address'), 'phone' => $workspace->kind === 'organisation' ? '' : $settings->get('support_phone')];
        }

        return [...$p, 'items' => $items, 'subtotal_minor' => $subtotal->toInt(), 'tax_minor' => $tax->toInt(), 'total_minor' => $subtotal->plus($tax)->toInt(), 'reference' => $record?->reference ?? app(PlatformSettings::class)->get('invoice_prefix').'-'.Str::upper((string) Str::ulid())];
    }

    private function payroll(array $p, ?OperationalModel $record, User $actor): array
    {
        StaffMember::whereKey($p['staff_member_id'])->lockForUpdate()->firstOrFail();
        if ($record && $record->status === 'approved' && $p['status'] !== 'draft') {
            foreach (['staff_member_id', 'period', 'gross_minor', 'allowances_minor', 'deductions_minor', 'currency'] as $key) {
                if ((string) $record->$key !== (string) $p[$key]) {
                    throw ValidationException::withMessages(['status' => 'Return approved payroll to draft before changing its calculation.']);
                }
            }
        }
        if ($record && in_array($record->status, ['paid', 'cancelled'], true)) {
            throw ValidationException::withMessages(['status' => 'Closed payroll records cannot be edited.']);
        }
        if (PayrollEntry::where('staff_member_id', $p['staff_member_id'])->where('period', $p['period'])->when($record, fn ($q) => $q->whereKeyNot($record->id))->exists()) {
            throw ValidationException::withMessages(['period' => 'This staff member already has a payroll entry for this month.']);
        }
        if (in_array($p['status'], ['approved', 'paid'], true)) {
            abort_unless($actor->hasPermission('payroll.approve'), 403);
        }
        if ($p['status'] === 'paid' && ($record?->status !== 'approved' || empty($p['paid_on']))) {
            throw ValidationException::withMessages(['status' => 'Approve payroll and enter a payment date before recording it paid.']);
        }
        $net = BigDecimal::of((string) $p['gross_minor'])->plus((string) $p['allowances_minor'])->minus((string) $p['deductions_minor']);
        if ($net->isLessThan('0')) {
            throw ValidationException::withMessages(['deductions' => 'Deductions exceed gross pay and allowances.']);
        }

        return [...$p, 'net_minor' => $net->toInt()];
    }

    private function appointment(array &$p, ?OperationalModel $record, User $actor): void
    {
        $p['assigned_to'] = $p['assigned_to'] ?? $actor->id;
        User::whereKey($p['assigned_to'])->lockForUpdate()->firstOrFail();
        if ($p['ends_at'] <= $p['starts_at']) {
            throw ValidationException::withMessages(['ends_at' => 'End time must follow the start time.']);
        }
        if (! empty($p['reminder_at']) && $p['reminder_at'] >= $p['starts_at']) {
            throw ValidationException::withMessages(['reminder_at' => 'Reminder must be before the appointment starts.']);
        }
        if ($p['status'] === 'scheduled' && Appointment::where('assigned_to', $p['assigned_to'])->where('status', 'scheduled')->where('starts_at', '<', $p['ends_at'])->where('ends_at', '>', $p['starts_at'])->when($record, fn ($q) => $q->whereKeyNot($record->id))->exists()) {
            throw ValidationException::withMessages(['starts_at' => 'This staff member already has a booking in that time slot.']);
        }
        $p['reminder_version'] = ($record?->reminder_version ?? 0) + 1;
        $p['reminder_queued_at'] = null;
        $p['reminded_at'] = null;
    }
}
