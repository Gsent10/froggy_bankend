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
use App\Enums\ExchangeRate;
use App\Models\Wallet;
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


    public function allTransactions(Request $request): JsonResponse
    {
        try {
            $customer = $request->user()->load('country');

            $transactions = $customer->transactions()
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
     * POST /api/wallets/topup
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

    /**
     * Process a transfer between wallets with idempotency protection.
     *
     * POST /api/wallets/transfer
     */
    public function transfer(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from_wallet_id' => ['required', 'integer'],
            'to_wallet_id' => ['required', 'integer', 'different:from_wallet_id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'idempotency_key' => ['required', 'string', 'max:64'],
        ]);

        DB::beginTransaction();

        try {
            $customer = $request->user();
            $amount = (float) $validated['amount'];

            $fromWallet = Wallet::where('id', $validated['from_wallet_id'])
                ->where('customer_id', $customer->id)
                ->first();

            $toWallet = Wallet::where('id', $validated['to_wallet_id'])
                ->where('customer_id', $customer->id)
                ->first();

            if (! $fromWallet || ! $toWallet) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'One or both wallets not found or do not belong to you.',
                ], 404);
            }

            if ($fromWallet->balance < $amount) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient balance in source wallet.',
                ], 422);
            }

            $rate = ExchangeRate::getRate($fromWallet->currency_code, $toWallet->currency_code);
            $convertedAmount = round($amount * $rate, 2);
            $reference = Transaction::generateReference();

            [$debitTxn, $isNew] = Transaction::findOrCreateByIdempotencyKey(
                $validated['idempotency_key'] . '-DEBIT',
                [
                    'wallet_id' => $fromWallet->id,
                    'reference' => $reference . '-DEBIT',
                    'type' => 'transfer',
                    'amount' => $amount,
                    'currency_code' => $fromWallet->currency_code,
                    'payment_method' => 'wallet_transfer',
                ]
            );

            if (! $isNew) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Duplicate request. Transfer already processed.',
                ]);
            }

            [$creditTxn] = Transaction::findOrCreateByIdempotencyKey(
                $validated['idempotency_key'] . '-CREDIT',
                [
                    'wallet_id' => $toWallet->id,
                    'reference' => $reference . '-CREDIT',
                    'type' => 'topup',
                    'amount' => $convertedAmount,
                    'currency_code'  => $toWallet->currency_code,
                    'payment_method' => 'wallet_transfer',
                ]
            );

            $fromWallet->debit($amount);
            $toWallet->credit($convertedAmount);

            $debitTxn->markSuccess(['exchange_rate' => $rate, 'to_wallet_id' => $toWallet->id]);
            WalletActivity::recordFromTransaction($debitTxn, 'Wallet Transfer Out');

            $creditTxn->markSuccess(['exchange_rate' => $rate, 'from_wallet_id' => $fromWallet->id]);
            WalletActivity::recordFromTransaction($creditTxn, 'Wallet Transfer In');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Transfer successful.',
                'data'    => [
                    'reference' => $reference,
                    'amount_sent' => $amount,
                    'amount_received' => $convertedAmount,
                    'exchange_rate' => $rate,
                    'from_currency' => $fromWallet->currency_code,
                    'to_currency' => $toWallet->currency_code,
                    'from_wallet' => [
                        'id' => $fromWallet->id,
                        'currency_code' => $fromWallet->currency_code,
                        'balance' => $fromWallet->fresh()->balance,
                    ],
                    'to_wallet' => [
                        'id' => $toWallet->id,
                        'currency_code' => $toWallet->currency_code,
                        'balance' => $toWallet->fresh()->balance,
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Transfer failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Transfer could not be completed. Please try again.',
            ], 500);
        }
    }

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
