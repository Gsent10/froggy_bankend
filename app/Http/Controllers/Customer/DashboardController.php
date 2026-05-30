<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\WalletResource;
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

            $wallet = $customer->wallets()
                ->where('currency_code', $customer->country->currency_code)
                ->first();

            if (! $wallet) {
                return response()->json([
                    'message' => 'Wallet not found for this customer.',
                ], 404);
            }

            return response()->json([
                'customer' => new CustomerResource($customer),
                'wallet'   => new WalletResource($wallet),
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
