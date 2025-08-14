<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    /**
     * POST /api/auth/register
     */
    public function register(Request $request)
    {
        //validacija ulaza
        $data = $request->validate([
            'name'     => ['required','string','max:255'],
            'email'    => ['required','string','email','max:255', Rule::unique('users','email')],
            'password' => ['required','confirmed','min:8'], // expects password_confirmation
        ]);

        // kreiranje korisnika-password se hashira automatski
        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        //izdaje personal access token preko Sanctum
        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'user'  => new UserResource($user),
            'token' => $token,
            'token_type' => 'Bearer',
        ], Response::HTTP_CREATED);
    }
     /**
     * POST /api/auth/login
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => ['required','email'],
            'password' => ['required','string'],
        ]);

        //provera kredencijala
        if (!Auth::attempt($credentials)) {
            return response()->json([
                'message' => 'Invalid credentials.'
            ], Response::HTTP_UNAUTHORIZED);
        }

        // uzimanje korisnika na osnovu email-a
        $user = User::where('email', $credentials['email'])->firstOrFail();

        // obrisati stare tokene za single-session
        // $user->tokens()->delete();

        // izdavanje novog tokena
        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'user'  => new UserResource($user),
            'token' => $token,
            'token_type' => 'Bearer',
        ]);
    }
    /**
     * POST /api/auth/logout
     * Requires: Authorization: Bearer <token>
     */
    public function logout(Request $request)
    {
        $user = $request->user();

        if ($user && $user->currentAccessToken()) {
            // samo trenutni pristupni token brise
            $user->currentAccessToken()->delete();
        }

        return response()->json([
            'message' => 'Logged out.'
        ]);
    }

}
