<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\WalletResource;
use App\Http\Resources\WalletActivityResource;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WalletController extends Controller
{
    /**
     * Return paginated list of wallets for the authenticated customer.
     *
     * GET /api/wallets
     */
    public function index(Request $request): JsonResponse
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

    /**
     * Create a new wallet for the authenticated customer.
     *
     * POST /api/wallets
     */
    public function create(Request $request): JsonResponse
    {
        $request->validate([
            'currency_code' => ['required', 'string', 'size:3'],
        ]);

        try {
            $customer = $request->user();

            // For simplicity, we create a wallet with the customer's country currency
            $wallet = $customer->wallets()->create([
                'currency_code' => $request->input('currency_code'),
                'balance' => 0,
            ]);

            return response()->json([
                'message' => 'Wallet created successfully.',
                'wallet' => new WalletResource($wallet),
            ], 201);
        } catch (Exception $e) {
            Log::error('Wallet creation failed: ' . $e->getMessage());

            return response()->json([
                'message' => 'Failed to create wallet. Please try again later.',
            ], 500);
        }
    }

    /**
     * Return details of a specific wallet by ID.
     *
     * GET /api/wallets/{id}
     */
    public function show(Request $request, $id): JsonResponse
    {
        try {
            $wallet = $request->user()->wallets()->find($id);

            if (! $wallet) {
                return response()->json([
                    'message' => 'Wallet not found.',
                ], 404);
            }

            $activities = $wallet->activities()->latest()->get();

            return response()->json([
                'wallet' => new WalletResource($wallet),
                'activities' => WalletActivityResource::collection($activities),
            ]);
        } catch (Exception $e) {
            Log::error('Wallet details fetch failed: ' . $e->getMessage());

            return response()->json([
                'message' => 'Failed to load wallet details. Please try again later.',
            ], 500);
        }
    }
}
