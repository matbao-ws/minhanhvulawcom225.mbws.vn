<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $customers = Order::query()
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
            ->when($request->filled('q'), function ($query) use ($request) {
                $keyword = $request->string('q')->trim()->value();

                $query->where(function ($query) use ($keyword) {
                    $query->where('customer_name', 'like', "%{$keyword}%")
                        ->orWhere('customer_email', 'like', "%{$keyword}%")
                        ->orWhere('customer_phone', 'like', "%{$keyword}%");
                });
            })
            ->groupBy(DB::raw('LOWER(customer_email)'))
            ->orderByDesc('last_order_at')
            ->paginate(15)
            ->withQueryString();

        $customerEmails = $customers->getCollection()
            ->pluck('customer_email')
            ->filter()
            ->map(fn (string $email): string => mb_strtolower($email))
            ->unique()
            ->values();
        $registeredCustomers = User::query()
            ->whereNull('role_id')
            ->whereIn(DB::raw('LOWER(email)'), $customerEmails)
            ->pluck('id', 'email')
            ->mapWithKeys(fn (int $id, string $email): array => [mb_strtolower($email) => $id]);

        $customers->getCollection()->each(function ($customer) use ($registeredCustomers): void {
            $customer->registered_user_id = $registeredCustomers->get(mb_strtolower($customer->customer_email));
        });

        return view('admin.customers.index', compact('customers'));
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
}
