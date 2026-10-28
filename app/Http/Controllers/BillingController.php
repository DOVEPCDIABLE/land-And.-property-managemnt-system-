<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Receipt;
use App\Services\OperationalAccess;
use App\Services\Payments\Money;
use App\Services\PlatformSettings;
use App\Services\Tenancy;
use App\Services\WorkspaceAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BillingController extends Controller
{
    public function receive(Request $request, int $invoice, OperationalAccess $access): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('payments.create'), 403);
        $data = $request->validate(['amount' => 'required|string', 'received_on' => 'required|date_format:Y-m-d', 'notes' => 'nullable|string|max:3000', 'payment_id' => 'nullable|integer']);
        $receipt = DB::transaction(function () use ($request, $invoice, $data, $access): Receipt {
            $bill = $access->query('invoices', $request->user(), 'create')->lockForUpdate()->findOrFail($invoice);
            if ($bill->status !== 'issued') {
                throw ValidationException::withMessages(['amount' => 'Issue the invoice before recording a receipt.']);
            }
            try {
                $minor = Money::minor($data['amount'], $bill->currency, $bill->minor_unit);
            } catch (\InvalidArgumentException $e) {
                throw ValidationException::withMessages(['amount' => $e->getMessage()]);
            }
            if ($minor <= 0 || $minor > $bill->balanceMinor()) {
                throw ValidationException::withMessages(['amount' => 'Enter an amount greater than zero and no greater than the outstanding balance.']);
            }
            $actor = $request->user();
            $attributes = $bill->only(['workspace_id', 'branch_id', 'team_id', 'assigned_to']);
            if (! empty($data['payment_id'])) {
                $payment = app(WorkspaceAccess::class)->records(Payment::query(), $actor, 'payments.create')->lockForUpdate()->findOrFail($data['payment_id']);
                if (! in_array($payment->status, ['paid', 'recorded'], true) || $payment->currency !== $bill->currency || $payment->minor_unit !== $bill->minor_unit || $payment->amount_minor !== $minor || Receipt::where('payment_id', $payment->id)->exists()) {
                    throw ValidationException::withMessages(['payment_id' => 'Payment must be confirmed, unallocated, and match this exact amount and currency.']);
                }
            } else {
                $payment = Payment::create([...$attributes, 'agency_id' => $actor->currentWorkspace()->agency_id, 'recorded_by' => $actor->id, 'user_id' => $bill->tenant_user_id ?? $actor->id, 'reference' => (string) Str::uuid(), 'provider' => 'manual', 'amount_minor' => $minor, 'currency' => $bill->currency, 'minor_unit' => $bill->minor_unit, 'purpose' => 'Invoice '.$bill->reference, 'status' => 'recorded', 'paid_at' => $data['received_on']]);
            }
            $receipt = Receipt::create([...$attributes, 'user_id' => $actor->id, 'tenant_user_id' => $bill->tenant_user_id, 'name' => 'Receipt for '.$bill->reference, 'reference' => app(PlatformSettings::class)->get('receipt_prefix').'-'.Str::upper((string) Str::ulid()), 'invoice_id' => $bill->id, 'payment_id' => $payment->id, 'amount_minor' => $minor, 'currency' => $bill->currency, 'minor_unit' => $bill->minor_unit, 'received_on' => $data['received_on'], 'notes' => $data['notes'] ?? null]);
            app(Tenancy::class)->audit($actor, 'receipt.recorded', $receipt->reference);

            return $receipt;
        });

        return redirect()->route('billing.receipt', $receipt->id)->with('status', 'Receipt recorded. No gateway was contacted.');
    }

    public function invoice(Request $request, int $invoice, OperationalAccess $access): View
    {
        $invoice = $access->record('invoices', $invoice, $request->user());

        return view('operations.print', ['invoice' => $invoice, 'receipt' => null]);
    }

    public function receipt(Request $request, int $receipt): View
    {
        $receipt = app(WorkspaceAccess::class)->records(Receipt::query(), $request->user(), 'payments.view')->with('invoice.contact')->findOrFail($receipt);

        return view('operations.print', ['invoice' => $receipt->invoice, 'receipt' => $receipt]);
    }

    public function transition(Request $request, int $invoice, OperationalAccess $access): RedirectResponse
    {
        $action = $request->validate(['action' => 'required|in:issue,void'])['action'];
        DB::transaction(function () use ($request, $invoice, $action, $access): void {
            $bill = $access->query('invoices', $request->user(), 'edit')->lockForUpdate()->findOrFail($invoice);
            if ($action === 'issue') {
                if ($bill->status !== 'draft') {
                    throw ValidationException::withMessages(['action' => 'Only draft invoices can be issued.']);
                }
                $site = app(PlatformSettings::class);
                $workspace = $request->user()->currentWorkspace();
                $bill->update(['status' => 'issued', 'customer_snapshot' => $bill->contact->only(['name', 'email', 'phone']), 'issuer_snapshot' => ['name' => $workspace->kind === 'organisation' ? $workspace->name : $site->get('company_name'), 'address' => $workspace->kind === 'organisation' ? '' : $site->get('address'), 'phone' => $workspace->kind === 'organisation' ? '' : $site->get('support_phone')]]);
            } else {
                if ($bill->status === 'void' || $bill->receipts()->exists()) {
                    throw ValidationException::withMessages(['action' => 'Only invoices without receipts can be voided.']);
                }
                $bill->update(['status' => 'void']);
            }
            app(Tenancy::class)->audit($request->user(), 'invoice.'.$action, $bill->reference);
        });

        return back()->with('status', 'Invoice updated.');
    }
}
