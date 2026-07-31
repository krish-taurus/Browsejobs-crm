<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ReadsLmsDatabase;
use App\Models\Lms\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Whitelabel manager: per-tenant brand name, logo, and colour palette. The
 * student portal reads these live (GET /api/v1/branding), so a save here
 * re-skins the product for that customer with no rebuild — the core of
 * selling the platform to institutes under their own brand.
 */
class WhitelabelController extends Controller
{
    use ReadsLmsDatabase;

    /** Colour keys editable per tenant, with labels the founder understands. */
    private const COLORS = [
        'trust_blue' => 'Primary action colour (buttons, links)',
        'deep_navy' => 'Deep accent (gradients, headings)',
        'ink_navy' => 'Main text / dark panels',
        'sky' => 'Light highlight fills',
        'paper' => 'Page background',
        'verify_green' => 'Success / verified',
        'warn_red' => 'Errors / overdue',
        'amber' => 'Stars / coach notes',
        'line' => 'Borders',
        'muted' => 'Muted text',
    ];

    public function index(): View
    {
        return $this->renderLmsPage(function () {
            $tenants = Tenant::query()->orderBy('id')->get();

            return view('whitelabel.index', ['tenants' => $tenants, 'colorFields' => self::COLORS]);
        });
    }

    public function update(Request $request, int $tenant): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'logo_url' => ['nullable', 'url', 'max:500'],
            'colors' => ['nullable', 'array'],
            'colors.*' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        $model = Tenant::query()->findOrFail($tenant);

        $branding = (array) ($model->branding ?? []);
        $branding['logo_url'] = $validated['logo_url'] ?: null;

        $colors = (array) ($branding['colors'] ?? []);
        foreach (array_keys(self::COLORS) as $key) {
            $value = $validated['colors'][$key] ?? null;
            if (is_string($value) && $value !== '') {
                $colors[$key] = strtoupper($value);
            }
        }
        $branding['colors'] = $colors;

        $model->update(['name' => $validated['name'], 'branding' => $branding]);

        return back()->with('success', "{$model->name}: branding saved — students see it on their next page load.");
    }
}
