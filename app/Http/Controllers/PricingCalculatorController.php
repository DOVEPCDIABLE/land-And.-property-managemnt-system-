<?php

namespace App\Http\Controllers;

use App\Services\CurrencyService;
use App\Services\Payments\Money;
use App\Services\PlatformSettings;
use App\Services\PricingCalculator;
use Brick\Math\Exception\MathException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PricingCalculatorController extends Controller
{
    public function show(PlatformSettings $settings): View
    {
        abort_unless($settings->get('pricing_enabled'), 404);

        return view('marketplace.calculator', ['result' => null]);
    }

    public function calculate(Request $request, PlatformSettings $settings, CurrencyService $currencies, PricingCalculator $calculator): View
    {
        abort_unless($settings->get('pricing_enabled'), 404);
        $data = $request->validate(['currency' => ['required', Rule::in(array_keys($currencies->enabled()))], 'amount' => 'required|string|max:30']);
        try {
            Money::minor($data['amount'], $data['currency']);
            $result = $calculator->calculate($data['amount'], $data['currency']);
        } catch (\InvalidArgumentException|MathException $e) {
            throw ValidationException::withMessages(['amount' => $e->getMessage()]);
        }

        return view('marketplace.calculator', compact('result'));
    }
}
