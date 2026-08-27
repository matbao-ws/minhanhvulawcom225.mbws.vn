<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Support\Csv;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Customers are derived from orders, not from a table of their own: a shop's
 * customer is anyone who has bought, whether or not they registered an account.
 * Rows are grouped by email, which is the only identifier both guests and members
 * always have.
 */
class CustomerController extends Controller
{
    private const EXPORT_CHUNK = 500;

    public function index(Request $request)
    {
        $customers = $this->customerQuery($request)
            ->paginate(15)
            ->withQueryString();

        $this->markRegisteredAccounts($customers->getCollection());

        return view('admin.customers.index', compact('customers'));
    }

    /**
     * Stream the same list the screen is showing, filters included.
     *
     * Streamed and chunked rather than collected: the export exists precisely for
     * shops with enough customers that reading them all into memory is the problem.
     */
    public function export(Request $request): StreamedResponse
    {
        $query = $this->customerQuery($request);

        return response()->stream(function () use ($query): void {
            $handle = fopen('php://output', 'w');
            Csv::writeUtf8Bom($handle);
            Csv::writeRow($handle, [
                __('admin.customers.export_columns.name'),
                __('admin.customers.export_columns.email'),
                __('admin.customers.export_columns.phone'),
                __('admin.customers.export_columns.account'),
                __('admin.customers.export_columns.orders'),
                __('admin.customers.export_columns.completed_orders'),
                __('admin.customers.export_columns.total_spent'),
                __('admin.customers.export_columns.last_order'),
            ]);

            // chunk() needs a deterministic order and the aggregate already sorts by
            // last order date, so paginate manually rather than fight the group by.
            $page = 1;
            do {
                $rows = (clone $query)->forPage($page, self::EXPORT_CHUNK)->get();
                $this->markRegisteredAccounts($rows);

                foreach ($rows as $customer) {
                    Csv::writeRow($handle, [
                        $customer->customer_name,
                        $customer->customer_email,
                        $customer->customer_phone,
                        $customer->registered_user_id
                            ? __('admin.customers.registered')
                            : __('admin.customers.guest'),
                        (int) $customer->total_orders,
                        (int) $customer->completed_orders,
                        (int) $customer->total_spent,
                        $customer->last_order_at
                            ? \Carbon\Carbon::parse($customer->last_order_at)->format('d/m/Y H:i')
                            : '',
                    ]);
                }

                $page++;
            } while ($rows->count() === self::EXPORT_CHUNK);

            fclose($handle);
        }, 200, Csv::downloadHeaders('customers_export_'.date('Y-m-d').'.csv'));
    }

    public function show(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = mb_strtolower($validated['email']);
        $ordersQuery = Order::query()
            ->whereRaw('LOWER(customer_email) = ?', [$email]);

        $latestOrder = (clone $ordersQuery)->latest()->firstOrFail();
        $metrics = (clone $ordersQuery)
            ->selectRaw('COUNT(*) as total_orders')
            ->selectRaw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_orders")
            ->selectRaw("SUM(CASE WHEN status = 'completed' THEN grand_total ELSE 0 END) as total_spent")
            ->selectRaw('MIN(created_at) as first_order_at')
            ->selectRaw('MAX(created_at) as last_order_at')
            ->firstOrFail();
        $orders = $ordersQuery->latest()->paginate(15)->withQueryString();
        $registeredCustomer = User::query()
            ->whereNull('role_id')
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        return view('admin.customers.show', [
            'customerName' => $registeredCustomer?->name ?? $latestOrder->customer_name,
            'customerEmail' => $latestOrder->customer_email,
            'customerPhone' => $latestOrder->customer_phone,
            'registeredCustomer' => $registeredCustomer,
            'metrics' => $metrics,
            'orders' => $orders,
        ]);
    }

    /**
     * One definition of the customer list, shared by the screen and the export, so a
     * filter added to one cannot silently miss the other.
     */
    private function customerQuery(Request $request): Builder
    {
        return Order::query()
            ->select([
                DB::raw('MAX(user_id) as user_id'),
                DB::raw('MAX(customer_name) as customer_name'),
                DB::raw('MAX(customer_email) as customer_email'),
                DB::raw('MAX(customer_phone) as customer_phone'),
                DB::raw('COUNT(*) as total_orders'),
                DB::raw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_orders"),
                DB::raw("SUM(CASE WHEN status = 'completed' THEN grand_total ELSE 0 END) as total_spent"),
                DB::raw('MAX(created_at) as last_order_at'),
            ])
            ->when($request->filled('q'), function (Builder $query) use ($request): void {
                $keyword = $request->string('q')->trim()->value();

                $query->where(function (Builder $query) use ($keyword): void {
                    $query->where('customer_name', 'like', "%{$keyword}%")
                        ->orWhere('customer_email', 'like', "%{$keyword}%")
                        ->orWhere('customer_phone', 'like', "%{$keyword}%");
                });
            })
            ->groupBy(DB::raw('LOWER(customer_email)'))
            ->orderByDesc('last_order_at');
    }

    /**
     * Flag which rows belong to someone who registered an account, in one query
     * rather than one per row.
     */
    private function markRegisteredAccounts(Collection $customers): void
    {
        $emails = $customers
            ->pluck('customer_email')
            ->filter()
            ->map(fn (string $email): string => mb_strtolower($email))
            ->unique()
            ->values();

        $registered = User::query()
            ->whereNull('role_id')
            ->whereIn(DB::raw('LOWER(email)'), $emails)
            ->pluck('id', 'email')
            ->mapWithKeys(fn (int $id, string $email): array => [mb_strtolower($email) => $id]);

        $customers->each(function ($customer) use ($registered): void {
            $customer->registered_user_id = $registered->get(mb_strtolower((string) $customer->customer_email));
        });
    }
}
