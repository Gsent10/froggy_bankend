<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\WalletActivityResource;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ActivityController extends Controller
{
    /**
     * Return paginated wallet activity for the customer's primary wallet.
     *
     * GET /api/wallet/activity
     */
    public function index(Request $request, $code): JsonResponse
    {
        try {
            $customer = $request->user()->load('country');

            $wallet = $customer->wallets()
                ->where('currency_code', $code)
                ->first();

            if (! $wallet) {
                return response()->json([
                    'message' => 'Wallet not found.',
                ], 404);
            }

            $activities = $wallet->activities()
                ->latest('created_at')
                ->paginate($request->integer('per_page', 10));

            return response()->json([
                'data'       => WalletActivityResource::collection($activities),
                'pagination' => [
                    'current_page' => $activities->currentPage(),
                    'last_page'    => $activities->lastPage(),
                    'per_page'     => $activities->perPage(),
                    'total'        => $activities->total(),
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Activity fetch failed: ' . $e->getMessage());

            return response()->json([
                'message' => 'Failed to load activity. Please try again later.',
            ], 500);
        }
    }

    public function allActivity(Request $request): JsonResponse
    {
        try {
            $customer = $request->user()->load('country');



            $activities = $customer->activities()
                ->latest('created_at')
                ->paginate($request->integer('per_page', 10));

            return response()->json([
                'data'       => WalletActivityResource::collection($activities),
                'pagination' => [
                    'current_page' => $activities->currentPage(),
                    'last_page'    => $activities->lastPage(),
                    'per_page'     => $activities->perPage(),
                    'total'        => $activities->total(),
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Activity fetch failed: ' . $e->getMessage());

            return response()->json([
                'message' => 'Failed to load activity. Please try again later.',
            ], 500);
        }
    }
}
