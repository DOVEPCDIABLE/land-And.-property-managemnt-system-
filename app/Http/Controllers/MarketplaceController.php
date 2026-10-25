<?php

namespace App\Http\Controllers;

use App\Models\PublicInquiry;
use App\Services\CurrencyService;
use Brick\Math\BigDecimal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MarketplaceController extends Controller
{
    private function catalog(): Collection
    {
        $currencies = app(CurrencyService::class);
        $target = request('currency', $currencies->default());
        if (! is_string($target) || ! array_key_exists($target, $currencies->enabled())) {
            $target = $currencies->default();
        }

        return collect(config('marketplace.properties'))->map(fn (array $p) => $currencies->listing($p, $target));
    }

    public function home(): View
    {
        return view('marketplace.home', ['properties' => $this->catalog(), 'articles' => config('marketplace.articles')]);
    }

    public function properties(Request $request): View
    {
        $filters = $request->validate(['currency' => ['nullable', Rule::in(array_keys(app(CurrencyService::class)->enabled()))], 'purpose' => 'nullable|in:buy,rent,development', 'location' => 'nullable|string|max:120', 'type' => ['nullable', Rule::in(array_keys(config('marketplace.types')))], 'min' => 'nullable|numeric|min:0|max:999999999999999|regex:/^\d{1,15}(?:\.\d{1,8})?$/D', 'max' => ['nullable', 'numeric', 'min:0', 'max:999999999999999', 'regex:/^\d{1,15}(?:\.\d{1,8})?$/D', ...($request->filled('min') ? ['gte:min'] : [])], 'beds' => 'nullable|integer|min:0|max:10', 'baths' => 'nullable|integer|min:0|max:10', 'size' => 'nullable|numeric|min:0', 'furnished' => 'nullable|in:1', 'amenity' => 'nullable|in:pool,parking,gym,garden']);
        $properties = $this->catalog()->filter(function (array $p) use ($filters): bool {
            foreach (['purpose', 'location', 'type', 'min', 'max', 'beds', 'baths', 'size', 'furnished', 'amenity'] as $key) {
                $value = $filters[$key] ?? null;
                if ($value === null || $value === '') {
                    continue;
                }
                $matches = match ($key) {
                    'purpose' => $p['purpose'] === $value,
                    'location' => str_contains(mb_strtolower($p['location']), mb_strtolower($value)),
                    'type' => $value === 'commercial' ? in_array($p['type'], ['office', 'shop', 'warehouse'], true) : $p['type'] === $value,
                    'min' => $p['conversion_available'] && BigDecimal::of($p['display_amount'])->isGreaterThanOrEqualTo((string) $value), 'max' => $p['conversion_available'] && BigDecimal::of($p['display_amount'])->isLessThanOrEqualTo((string) $value),
                    'beds' => $p['beds'] >= (int) $value,'baths' => $p['baths'] >= (int) $value,
                    'size' => $p['area'] >= (float) $value,'furnished' => $p['furnished'],'amenity' => in_array($value, $p['amenities'], true),
                };
                if (! $matches) {
                    return false;
                }
            }

            return true;
        });

        return view('marketplace.properties', compact('properties', 'filters'));
    }

    public function property(string $slug): View
    {
        $property = $this->catalog()->firstWhere('slug', $slug);
        abort_unless($property, 404);

        return view('marketplace.property', compact('property'));
    }

    public function article(string $slug): View
    {
        $article = config('marketplace.articles.'.$slug);
        abort_unless($article, 404);

        return view('marketplace.article', compact('article'));
    }

    public function info(string $page): View
    {
        abort_unless(in_array($page, ['privacy', 'terms', 'verification', 'safety', 'cookies'], true), 404);

        return view('marketplace.info', compact('page'));
    }

    public function inquire(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => 'required|string|max:120', 'email' => 'required|email|max:255', 'intent' => 'required|in:listing,viewing,valuation,professional,agency,referral,demo,alert,support,report', 'message' => 'nullable|string|max:3000', 'consent' => 'accepted', 'website' => 'nullable|size:0', 'preferences' => 'nullable|array:location,type,purpose,budget,beds,channel,property,topic,currency', 'preferences.*' => 'nullable|string|max:200', 'preferences.currency' => ['nullable', Rule::in(array_keys(app(CurrencyService::class)->enabled()))]]);
        if (! empty($data['preferences']['property'])) {
            abort_unless($this->catalog()->contains('slug', $data['preferences']['property']), 422);
        }
        PublicInquiry::create(collect($data)->only(['name', 'email', 'intent', 'message', 'preferences'])->all());

        return back()->with('status', 'Your request has been saved for the citiESTATE team. This is a preview; no viewing, valuation or alert delivery is confirmed yet.');
    }

    public function inquiries(): View
    {
        return view('inquiries', ['inquiries' => PublicInquiry::latest()->paginate(25)]);
    }
}
