<?php

namespace App\Http\Controllers\Api;

use App\Enums\MeasurementUnit;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\DeleteAccountRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\Business;
use App\Models\User;
use App\Services\DeleteAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();
        $phone = $data['phone'];

        $user = DB::transaction(function () use ($data, $phone) {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);

            Business::query()->create([
                'user_id' => $user->id,
                'name' => $data['business_name'],
                'phone' => $phone,
                'whatsapp_phone' => $phone,
                'country' => strtoupper($data['country']),
                'measurement_unit' => MeasurementUnit::from($data['measurement_unit']),
                'currency' => strtoupper($data['currency']),
            ]);

            return $user->load('business');
        });

        $token = $user->createToken('phone')->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => UserResource::make($user),
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        $user = User::query()->where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Those login details are wrong.'],
            ]);
        }

        $user->load('business');
        $token = $user->createToken('phone')->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => UserResource::make($user),
        ]);
    }

    public function me(Request $request): UserResource
    {
        return UserResource::make($request->user()->load('business'));
    }

    public function updateProfile(UpdateProfileRequest $request): UserResource
    {
        $user = $request->user();
        $data = $request->validated();

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
        ]);

        return UserResource::make($user->fresh()->load('business'));
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->json([
            'message' => 'Signed out.',
        ]);
    }

    public function destroy(DeleteAccountRequest $request): JsonResponse
    {
        DeleteAccount::run($request->user());

        return response()->json([
            'message' => 'Account deleted.',
        ]);
    }
}
