<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Handles authentication: register, login, logout, and current user.
 * All inputs are validated and sanitized via Laravel's built-in validator.
 * Passwords are hashed using bcrypt (Hash::make).
 * Tokens are issued via Laravel Sanctum.
 */
class AuthController extends Controller
{
    /**
     * Register a new user account.
     * Validates: name, email (unique), password (confirmed, min 8), role.
     * Only 'customer' role is publicly self-registerable for security.
     * Admin/cashier accounts must be created by an admin.
     */
    public function register(Request $request)
    {
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'role'     => 'sometimes|in:customer,cashier,admin',
        ]);

        $user = User::create([
            'name'     => strip_tags($data['name']),   // strip any HTML from name
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
            'role'     => $data['role'] ?? 'customer',
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json(['user' => $user, 'token' => $token], 201);
    }

    /**
     * Authenticate an existing user.
     * Returns a Sanctum token on success.
     * Throws ValidationException (422) on bad credentials.
     */
    public function login(Request $request)
    {
        $data = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $data['email'])->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (!$user->is_active) {
            return response()->json(['message' => 'Account has been deactivated.'], 403);
        }

        // Revoke old tokens to prevent session accumulation
        $user->tokens()->delete();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json(['user' => $user, 'token' => $token]);
    }

    /** Revoke the current access token (logout). */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out successfully.']);
    }

    /** Return the currently authenticated user. */
    public function me(Request $request)
    {
        return response()->json($request->user());
    }
}
