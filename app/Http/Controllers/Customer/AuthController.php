<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\ForgotPasswordRequest;
use App\Http\Requests\Customer\LoginRequest;
use App\Http\Requests\Customer\RegisterRequest;
use App\Http\Requests\Customer\ResetPasswordRequest;
use App\Http\Requests\Customer\VerifyOtpRequest;
use App\Http\Requests\Customer\ResendOtpRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Country;
use App\Models\Customer;
use App\Models\Wallet;
use App\Notifications\Customer\SendOtpNotification;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    /**
     * Register a new customer, generate OTP, and send email verification.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            $otp = $this->generateOtp();

            $customer = Customer::create([
                'full_name' => $request->full_name,
                'email' => $request->email,
                'phone_number' => $request->phone_number,
                'country_code' => $request->country_code,
                'password' => $request->password,
                'verification_code' => $otp,
                'verification_code_expires_at' => Carbon::now()->addMinutes(20),
            ]);

            $country = Country::find($request->country_code);

            Wallet::create([
                'customer_id' => $customer->id,
                'currency_code' => $country->currency_code,
                'balance' => 0.00,
            ]);

            try {
                $customer->notify(new SendOtpNotification($otp, 'email_verification'));
            } catch (Exception $e) {
                Log::error('OTP notification failed: ' . $e->getMessage());
            }

            DB::commit();

            return response()->json([
                'message' => 'Registration successful. Please check your email for your OTP.',
            ], 201);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Registration failed: ' . $e->getMessage());

            return response()->json([
                'message' => 'Registration failed. Please try again later.',
            ], 500);
        }
    }

    /**
     * Verify OTP for email verification and issue auth token upon success.
     */
    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            $customer = Customer::where('email', $request->email)->first();

            if (! $customer) {
                return response()->json(['message' => 'Account not found.'], 404);
            }

            if ($customer->email_verified_at) {
                return response()->json(['message' => 'Account is already verified.'], 422);
            }

            if (Carbon::now()->isAfter($customer->verification_code_expires_at)) {
                return response()->json(['message' => 'OTP has expired. Please request a new one.'], 422);
            }

            if ($customer->verification_code !== $request->otp) {
                return response()->json(['message' => 'Invalid OTP.'], 422);
            }

            $customer->update([
                'email_verified_at' => Carbon::now(),
                'verification_code' => null,
                'verification_code_expires_at' => null,
            ]);

            // Issue token only after verified
            $token = $customer->createToken('customer')->plainTextToken;

            DB::commit();

            return response()->json([
                'message'  => 'Account verified successfully.',
                'token'    => $token,
                'customer' => new CustomerResource($customer->load('country')),
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('OTP verification failed: ' . $e->getMessage());

            return response()->json([
                'message' => 'Verification failed. Please try again later.',
            ], 500);
        }
    }

    /**
     * Resend OTP for email verification.
     */
    public function resendOtp(ResendOtpRequest $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            $customer = Customer::where('email', $request->email)->first();

            if (! $customer) {
                return response()->json(['message' => 'Account not found.'], 404);
            }

            if ($customer->email_verified_at) {
                return response()->json(['message' => 'Account is already verified.'], 422);
            }

            // Throttle: prevent spamming resend
            if ($customer->updated_at->isAfter(Carbon::now()->subSeconds(60))) {
                return response()->json([
                    'message' => 'Please wait 60 seconds before requesting a new OTP.',
                ], 429);
            }

            $otp = $this->generateOtp();

            $customer->update([
                'verification_code' => $otp,
                'verification_code_expires_at' => Carbon::now()->addMinutes(20),
            ]);

            try {
                $customer->notify(new SendOtpNotification($otp, 'email_verification'));
            } catch (Exception $e) {
                Log::error('Resend OTP notification failed: ' . $e->getMessage());
            }

            DB::commit();

            return response()->json([
                'message' => 'A new OTP has been sent to your email.',
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Resend OTP failed: ' . $e->getMessage());

            return response()->json([
                'message' => 'Failed to resend OTP. Please try again later.',
            ], 500);
        }
    }

    /**
     * Login with email and password, only if email is verified.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $customer = Customer::where('email', $request->email)->first();

        if (! $customer || ! Hash::check($request->password, $customer->password)) {
            return response()->json(['message' => 'Invalid email or password.'], 401);
        }

        if (! $customer->email_verified_at) {
            return response()->json([
                'message' => 'Please verify your email before logging in.',
                'email'   => $customer->email,
            ], 403);
        }

        // Revoke all previous tokens — single session per customer
        $customer->tokens()->delete();

        $token = $customer->createToken('customer')->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'token' => $token,
            'customer' => new CustomerResource($customer->load('country')),
        ]);
    }

    /**
     * Logout by revoking the current access token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    /**
     * Forgot password, verify email, generates OTP, and sends notification.
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            $customer = Customer::where('email', $request->email)->first();

            // Always return success to prevent user enumeration
            if (! $customer) {
                return response()->json([
                    'message' => 'If this email is registered, an OTP has been sent.',
                ]);
            }

            $otp = $this->generateOtp();

            $customer->update([
                'verification_code' => $otp,
                'verification_code_expires_at' => Carbon::now()->addMinutes(20),
            ]);

            try {
                $customer->notify(new SendOtpNotification($otp, 'password_reset'));
            } catch (Exception $e) {
                Log::error('Forgot password OTP failed: ' . $e->getMessage());
            }

            DB::commit();

            return response()->json([
                'message' => 'If this email is registered, an OTP has been sent.',
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Forgot password failed: ' . $e->getMessage());

            return response()->json([
                'message' => 'Failed to process request. Please try again later.',
            ], 500);
        }
    }

    /**
     *  Reset password after verifying OTP, only for authenticated customers.
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            $customer_id = Auth::user()->id;
            $customer = Customer::where('id', $customer_id)->first();

            if (! $customer) {
                return response()->json(['message' => 'Account not found.'], 404);
            }

            $customer->update([
                'password' => $request->password,
            ]);


            DB::commit();

            return response()->json([
                'message' => 'Password reset successfully.',
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Reset password failed: ' . $e->getMessage());

            return response()->json([
                'message' => 'Failed to reset password. Please try again later.',
            ], 500);
        }
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function generateOtp(): string
    {
        return (string) rand(100000, 999999);
    }
}
