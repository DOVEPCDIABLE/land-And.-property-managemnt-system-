<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePaymentRequest;
use App\Models\Payment;
use App\Services\CurrencyService;
use App\Services\Payments\GatewayManager;
use App\Services\Payments\Money;
use App\Services\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class PaymentController extends Controller
{
    public function index(Request $request, Tenancy $tenancy): View
    {
        return view('payments', ['payments' => $tenancy->records(Payment::query(), $request->user())->with('user')->latest()->paginate(20), 'recipients' => $tenancy->recipients($request->user(), 'payments.create')->get()]);
    }

    public function store(StorePaymentRequest $request, Tenancy $tenancy, GatewayManager $gateways): RedirectResponse
    {
        $data = $request->validated();
        $manual = $data['provider'] === 'manual';
        abort_unless($manual || $request->user()->currentWorkspace()->kind !== 'personal', 403, 'Personal workspaces do not initiate gateway collection requests.');
        abort_unless($manual || config('payments.enabled'), 422, 'Gateway processing is disabled. Manual payment recording is available.');
        $recipient = $tenancy->recipients($request->user(), 'payments.create')->findOrFail($data['user_id']);
        $attributes = [...$tenancy->attributes($request->user(), $recipient), 'user_id' => $recipient->id, 'recorded_by' => $request->user()->id, 'provider' => $data['provider'], 'amount_minor' => Money::minor($data['amount'], $data['currency']), 'currency' => $data['currency'], 'minor_unit' => app(CurrencyService::class)->minorUnit($data['currency']), 'purpose' => $data['purpose']];
        $payment = Payment::firstOrCreate(['reference' => $data['reference']], [...$attributes, 'status' => $manual ? 'recorded' : 'initializing', 'paid_at' => $manual ? now() : null]);
        if (! $payment->wasRecentlyCreated) {
            abort_unless($payment->recorded_by === $request->user()->id && collect($attributes)->every(fn ($value, $key): bool => $value === $payment->$key), 409, 'This reference belongs to a different payment request.');

            return back()->with('status', 'This payment request has already been recorded.');
        }
        $tenancy->audit($request->user(), $manual ? 'payment.recorded' : 'payment.initiated', $payment->reference);
        if (! $manual) {
            try {
                $result = $gateways->driver($data['provider'])->initialize($payment);
                $payment->update([...$result, 'status' => 'pending']);
            } catch (Throwable $exception) {
                $payment->update(['status' => 'initialization_failed']);
                report($exception);

                return back()->withErrors(['provider' => 'The gateway could not initialize this payment. Review its status before creating another request.']);
            }
        }

        return back()->with('status', $manual ? 'Payment recorded. No gateway was contacted.' : 'Payment initialized. Share the checkout link or bank instructions with the customer.');
    }

    public function returned(): RedirectResponse
    {
        return redirect()->route('dashboard')->with('status', 'Payment return received. Only server verification can confirm payment.');
    }
}
