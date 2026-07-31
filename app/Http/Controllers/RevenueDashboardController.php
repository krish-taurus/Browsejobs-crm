<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ReadsLmsDatabase;
use App\Models\Expense;
use App\Models\Lms\Instalment;
use App\Models\Lms\Payment;
use App\Models\Lms\ProductPurchase;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Visual revenue summary (founder request, Jul 2026): money drawn as a flow —
 * course fees + store packs → collected → minus expenses → net — with a
 * 6-month trend as pure-CSS bars. No tables; every number re-scopes with the
 * period filter.
 */
class RevenueDashboardController extends Controller
{
    use ReadsLmsDatabase;

    public function index(Request $request): View
    {
        return $this->renderLmsPage(function () use ($request) {
            $period = $request->input('period', '30d');

            $from = match ($period) {
                'today' => today(),
                '7d' => now()->subDays(7),
                '30d' => now()->subDays(30),
                default => null,
            };

            $fees = Payment::query()->where('status', 'captured')
                ->when($from, fn ($q) => $q->where('captured_at', '>=', $from))
                ->sum('amount_paise');

            $store = ProductPurchase::query()->where('status', 'captured')
                ->when($from, fn ($q) => $q->where('captured_at', '>=', $from))
                ->sum('total_paise');

            $collected = $fees + $store;

            $expenses = (float) Expense::query()
                ->when($from, fn ($q) => $q->where('expense_date', '>=', $from->toDateString()))
                ->sum('amount');
            $expensesPaise = (int) round($expenses * 100);

            $net = $collected - $expensesPaise;

            $outstanding = Instalment::query()->where('status', '!=', 'paid')->sum('amount_paise');
            $overdue = Instalment::query()->where('status', '!=', 'paid')
                ->whereDate('due_on', '<', today())
                ->sum('amount_paise');

            $paymentCount = Payment::query()->where('status', 'captured')
                ->when($from, fn ($q) => $q->where('captured_at', '>=', $from))
                ->count();
            $storeCount = ProductPurchase::query()->where('status', 'captured')
                ->when($from, fn ($q) => $q->where('captured_at', '>=', $from))
                ->count();

            // 6-month trend (collected per calendar month, both streams).
            // startOfMonth BEFORE subMonths: "Jul 30 − 5 months" overflows
            // February and lands in March, duplicating the month labels.
            $trend = collect(range(5, 0))->map(function (int $back) {
                $start = now()->startOfMonth()->subMonths($back);
                $end = (clone $start)->endOfMonth();
                $sum = Payment::query()->where('status', 'captured')->whereBetween('captured_at', [$start, $end])->sum('amount_paise')
                    + ProductPurchase::query()->where('status', 'captured')->whereBetween('captured_at', [$start, $end])->sum('total_paise');

                return ['label' => $start->format('M'), 'paise' => (int) $sum];
            });
            $trendMax = max(1, $trend->max('paise'));

            return view('revenue-dashboard.index', compact(
                'period', 'fees', 'store', 'collected', 'expensesPaise', 'net',
                'outstanding', 'overdue', 'paymentCount', 'storeCount', 'trend', 'trendMax'
            ));
        });
    }
}
