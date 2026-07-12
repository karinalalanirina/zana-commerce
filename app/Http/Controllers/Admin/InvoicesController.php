<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Package;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvoicesController extends Controller
{
    public function index(Request $request): View
    {
        $statusF = (string) $request->query('status', 'all');
        $window  = $request->query('window', 'this_month');
        $q       = trim((string) $request->query('q', ''));

        [$from, $to] = $this->windowRange($window);

        // We treat the orders table as the "invoice" source for now —
        // every paid/pending order generates an INV-YYYY-MMNNNNN slug
        // computed in the view.
        $query = Order::query()->with('workspace')->whereBetween('created_at', [$from, $to]);

        // Status pills mapping.
        if ($statusF === 'paid')        $query->where('status', 'paid');
        if ($statusF === 'outstanding') $query->where('status', 'pending');
        if ($statusF === 'overdue')     $query->where('status', 'pending')->where('created_at', '<', now()->subDays(15));
        if ($statusF === 'refunded')    $query->where('status', 'refunded');
        if ($statusF === 'void')        $query->where('status', 'failed');

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('order_number', 'like', "%{$q}%")
                  ->orWhere('customer_email', 'like', "%{$q}%")
                  ->orWhereHas('workspace', fn ($wq) => $wq->where('name', 'like', "%{$q}%"));
            });
        }

        $invoices = $query->orderByDesc('created_at')->paginate(12)->withQueryString();

        return view('admin.invoices.index', [
            'window'   => $window,
            'statusF'  => $statusF,
            'q'        => $q,
            'invoices' => $invoices,
            'stats'    => $this->kpis(),
        ]);
    }

    public function show(string $id): View
    {
        $invoice = Order::with(['workspace', 'user', 'gateway'])->findOrFail($id);
        $package = $invoice->package_id ? Package::find($invoice->package_id) : null;
        return view('admin.invoices.view', [
            'invoice' => $invoice,
            'package' => $package,
            'billing' => \App\Support\Brand::billing(),
        ]);
    }

    private function windowRange(string $window): array
    {
        $now = now();
        return match ($window) {
            'last_month'   => [$now->copy()->subMonth()->startOfMonth(), $now->copy()->subMonth()->endOfMonth()],
            'this_quarter' => [$now->copy()->firstOfQuarter(), $now->copy()->endOfDay()],
            'this_year'    => [$now->copy()->startOfYear(),     $now->copy()->endOfDay()],
            default        => [$now->copy()->startOfMonth(),    $now->copy()->endOfDay()],
        };
    }

    /** Top KPI strip stats. */
    private function kpis(): array
    {
        $total    = Order::query()->count();
        $paid     = Order::query()->where('status', 'paid')->count();
        $pending  = Order::query()->where('status', 'pending')->count();
        $overdue  = Order::query()->where('status', 'pending')->where('created_at', '<', now()->subDays(15))->count();
        $outstandingAmt = (float) Order::query()->where('status', 'pending')->sum(DB::raw('COALESCE(total_amount, amount)'));

        $monthSum = (float) Order::query()->where('status', 'paid')
            ->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfDay()])
            ->sum(DB::raw('COALESCE(total_amount, amount)'));
        $prevMonthSum = (float) Order::query()->where('status', 'paid')
            ->whereBetween('paid_at', [now()->copy()->subMonth()->startOfMonth(), now()->copy()->subMonth()->endOfMonth()])
            ->sum(DB::raw('COALESCE(total_amount, amount)'));
        $monthDelta = $prevMonthSum > 0 ? round((($monthSum - $prevMonthSum) / $prevMonthSum) * 100, 1) : ($monthSum > 0 ? 100.0 : 0);

        return [
            'total'        => number_format($total),
            'paid'         => number_format($paid),
            'paidPct'      => $total > 0 ? round($paid / $total * 100, 1) . '%' : '0%',
            'outstanding'  => number_format($pending),
            'outstandingAmt' => \App\Support\FormatSettings::symbol() . self::short($outstandingAmt) . ' due',
            'overdue'      => number_format($overdue),
            'monthSum'     => \App\Support\FormatSettings::symbol() . self::short($monthSum),
            'monthDelta'   => ($monthDelta >= 0 ? '+' : '') . $monthDelta . '% MoM',
            'monthDeltaPos'=> $monthDelta >= 0,
        ];
    }

    public static function short(float $n): string
    {
        if ($n >= 1_000_000) return number_format($n / 1_000_000, 1) . 'M';
        if ($n >= 1_000)     return number_format($n / 1_000, 1) . 'k';
        return number_format($n, 0);
    }

    /** Map order status → invoice status badge tone. */
    public static function badge(Order $o): array
    {
        return match ($o->status) {
            'paid'     => ['label' => 'paid',         'class' => 'bg-wa-mint text-wa-deep', 'dot' => 'bg-wa-green'],
            'pending'  => $o->created_at->lt(now()->subDays(15))
                            ? ['label' => 'overdue',  'class' => 'bg-accent-coral/10 text-accent-coral', 'dot' => 'bg-accent-coral']
                            : ['label' => 'outstanding','class' => 'bg-accent-amber/10 text-accent-amber', 'dot' => 'bg-accent-amber'],
            'refunded' => ['label' => 'refunded',     'class' => 'bg-[#F3E9FF] text-[#5B3D8A]', 'dot' => 'bg-[#9C7DB8]'],
            'failed'   => ['label' => 'void',         'class' => 'bg-paper-100 text-ink-700', 'dot' => 'bg-paper-300'],
            default    => ['label' => $o->status,     'class' => 'bg-paper-100 text-ink-700', 'dot' => 'bg-paper-300'],
        };
    }
}
