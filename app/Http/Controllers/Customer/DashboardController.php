<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\WalletResource;
use App\Http\Resources\TransactionResource;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    /**
     * Return the authenticated customer's profile and primary wallet.
     *
     * GET /api/dashboard
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $customer = $request->user()->load('country');

            $wallets = $customer->wallets()
                ->get();

            $recentTransactions = $customer->transactions()
                ->latest()
                ->limit(5)
                ->get();

            return response()->json([
                'customer' => new CustomerResource($customer),
                'wallets'   => WalletResource::collection($wallets),
                'recentTransactions' => TransactionResource::collection($recentTransactions),
            ]);
        } catch (Exception $e) {
            Log::error('Dashboard fetch failed: ' . $e->getMessage());

            return response()->json([
                'message' => 'Failed to load dashboard. Please try again later.',
            ], 500);
        }
    }

    /**
     * Return all wallets owned by the customer.
     *
     * GET /api/dashboard/wallets
     */
    public function wallets(Request $request): JsonResponse
    {
        try {
            $wallets = $request->user()->wallets()->get();

            return response()->json([
                'wallets' => WalletResource::collection($wallets),
            ]);
        } catch (Exception $e) {
            Log::error('Wallet list fetch failed: ' . $e->getMessage());

            return response()->json([
                'message' => 'Failed to load wallets. Please try again later.',
            ], 500);
        }
    }
}
