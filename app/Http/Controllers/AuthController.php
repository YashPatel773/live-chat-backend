<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    /**
     * Handle User Registration
     */
    public function register(Request $request)
    {
        // 1. Validate the incoming data from React
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required||min:6|confirmed',
        ]);

        // If validation fails, return the error messages back to React immediately
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 400);
        }

        // 2. Save the user into the MySQL Database
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password), // Encrypts the password securely
        ]);

        // 3. Log the user in right away by generating a JWT token
        $token = auth()->login($user);

        return $this->respondWithToken($token, $user);
    }

    /**
     * Handle User Login
     */
    public function login(Request $request)
    {
        // 1. Validate incoming login inputs
        $request->validate([
            'email' => 'required|email',    
            'password' => 'required',
        ]);

        $credentials = $request->only(['email', 'password']);

        // 2. Attempt to verify credentials and generate a token
        if (!$token = auth()->attempt($credentials)) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid email or password'
            ], 401);
        }

        // 3. If successful, return the token and logged-in user details
        return $this->respondWithToken($token, auth()->user());
    }

    /**
     * Handle User Logout
     */
    public function logout()
    {
        auth()->logout(); // Invalidates the JWT token so it can never be used again

        return response()->json([
            'success' => true,
            'message' => 'Successfully logged out'
        ]);
    }

    /**
     * Helper function to structure our uniform token response
     */
    protected function respondWithToken($token, $user)
    {
        return response()->json([
            'success' => true,
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL() * 60, // Token lifetime in seconds
            'user' => $user
        ]);
    }
}