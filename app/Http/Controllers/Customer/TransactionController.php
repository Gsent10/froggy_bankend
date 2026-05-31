<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\TopUpRequest;
use App\Http\Resources\TransactionResource;
use App\Http\Resources\WalletResource;
use App\Models\Transaction;
use App\Models\WalletActivity;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TransactionController extends Controller
{
    /**
     * Return paginated transaction history for the customer's primary wallet.
     *
     * GET /api/wallet/transactions
     */
    public function index(Request $request, $code): JsonResponse
    {
        try {
            $customer = $request->user()->load('country');

            $wallet = $customer->wallets()
                ->where('currency_code', $code)
                ->first();

            if (! $wallet) {
                return response()->json(['message' => 'Wallet not found.'], 404);
            }

            $transactions = $wallet->transactions()
                ->latest()
                ->paginate($request->integer('per_page', 10));

            return response()->json([
                'data'       => TransactionResource::collection($transactions),
                'pagination' => [
                    'current_page' => $transactions->currentPage(),
                    'last_page' => $transactions->lastPage(),
                    'per_page' => $transactions->perPage(),
                    'total' => $transactions->total(),
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Transaction list fetch failed: ' . $e->getMessage());

            return response()->json([
                'message' => 'Failed to load transactions. Please try again later.',
            ], 500);
        }
    }

    /**
     * Process a wallet top-up with idempotency protection.
     *
     * POST /api/wallet/topup
     */
    public function topUp(TopUpRequest $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            $customer = $request->user()->load('country');

            $wallet = $customer->wallets()
                ->where('currency_code', $request->currency_code)
                ->first();

            if (! $wallet) {
                return response()->json(['message' => 'Wallet not found.'], 404);
            }

            // Idempotency check — replays return the original result
            [$transaction, $isNew] = Transaction::findOrCreateByIdempotencyKey(
                $request->idempotency_key,
                [
                    'wallet_id' => $wallet->id,
                    'reference' => Transaction::generateReference(),
                    'type' => 'topup',
                    'amount' => $request->amount,
                    'currency_code' => $wallet->currency_code,
                    'payment_method' => $request->payment_method,
                ]
            );

            if (! $isNew) {
                DB::rollBack();

                return response()->json([
                    'message' => 'Duplicate request. Returning original result.',
                    'transaction' => new TransactionResource($transaction),
                    'wallet' => new WalletResource($wallet->fresh()),
                ]);
            }

            // Mock payment gateway — swap this for a real provider call
            $paymentSucceeded = $this->mockPaymentGateway($request->payment_method, $request->amount);

            if ($paymentSucceeded) {
                $wallet->credit((float) $request->amount);
                $transaction->markSuccess(['gateway' => 'mock', 'payment_method' => $request->payment_method]);
                WalletActivity::recordFromTransaction($transaction, 'Wallet Top-Up');
            } else {
                $transaction->markFailed(['reason' => 'Payment declined by gateway.']);
                WalletActivity::recordFromTransaction($transaction, 'Top-Up Failed');
            }

            DB::commit();

            $status = $paymentSucceeded ? 200 : 422;

            return response()->json([
                'message' => $paymentSucceeded
                    ? 'Top-up successful.'
                    : 'Top-up failed. Payment was declined.',
                'transaction' => new TransactionResource($transaction->fresh()),
                'wallet' => new WalletResource($wallet->fresh()),
            ], $status);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Top-up failed: ' . $e->getMessage());

            return response()->json([
                'message' => 'Failed to process top-up. Please try again later.',
            ], 500);
        }
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Simulates a payment gateway response.
     * Replace with a real provider (Stripe, Paystack, Flutterwave, etc.).
     */
    private function mockPaymentGateway(string $paymentMethod, float $amount): bool
    {
        // Simulate a 10% failure rate for realism
        return rand(1, 10) !== 1;
    }
}
