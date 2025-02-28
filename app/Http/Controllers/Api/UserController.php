<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log; // Import the Log facade
use Illuminate\Support\Facades\Password;

class UserController extends Controller
{
    /**
     * Create User
     * @param Request $request
     * @return User 
     */
    public function createUser(Request $request)
{
    try {
        // Log the request data for debugging
        Log::info('Create User Request:', $request->all());

        // Validate the request
        $validateUser = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|confirmed|min:8', 
            'password_confirmation' => 'required',
            'device_name' => 'required'
        ]);

        if ($validateUser->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'validation error',
                'errors' => $validateUser->errors()
            ], 422);
        }

        // Create the user
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'device_name' => $request->device_name,
        ]);

        // Log the user creation for debugging
        Log::info('User Created:', $user->toArray());

        // Fire the Registered event
        event(new Registered($user));

        // Return the token
        return response()->json([
            'token' => $user->createToken($request->device_name)->plainTextToken
        ]);
    } catch (\Throwable $th) {
        // Log the error for debugging
        Log::error('Error Creating User:', ['error' => $th->getMessage()]);

        return response()->json([
            'status' => false,
            'message' => 'Internal Server Error',
            'error' => $th->getMessage()
        ], 500);
    }
}


    /**
     * Login The User (Alternative Method)
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function loginUser(Request $request)
    {
        // Validate the request
        $validatedData = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'password' => ['required'],
            'device_name' => ['required']
        ]);

        if ($validatedData->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validatedData->errors()
            ], 422);
        }

        // Find the user by email
        $user = User::where('email', $request->email)->first();

        // Check if the user exists and the password is correct
        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Return the token
        return response()->json([
            'token' => $user->createToken($request->device_name)->plainTextToken
        ]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email']
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if the user exists
        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'User not found',
                'errors' => ['email' => ['The provided email does not exist.']]
            ], 404);
        }

        // Send the password reset link
        $status = Password::sendResetLink(
            $request->only('email')
        );

        // Check the status and return the appropriate response
        if ($status != Password::RESET_LINK_SENT) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return response()->json(['status' => __($status)]);
    }
}