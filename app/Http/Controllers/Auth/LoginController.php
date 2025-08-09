<?php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    /**
     * Logowanie użytkownika — zwraca token Sanctum i dane użytkownika z rolami.
     */
    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required','email'],
            'password' => ['required'],
        ]);

        $user = User::where('email', $data['email'])->first();
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            abort(401, 'Złe dane logowania.');
        }

        if (! $user->is_active) {
            abort(403, 'Konto jest nieaktywne. Skontaktuj się z przełożonym.');
        }

        $token = $user->createToken('weweb')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => $user->load('roles:id,name','manager:id,name'),
        ]);
    }

    /**
     * Wylogowanie użytkownika — usuwa wszystkie jego tokeny.
     */
    public function logout(Request $request)
    {
        // Usuwamy wszystkie tokeny zalogowanego użytkownika
        $request->user()->tokens()->delete();

        return response()->json([
            'message' => 'Wylogowano ze wszystkich urządzeń.'
        ]);
    }

    public function me(Request $request)
    {
        return $request->user()->load('roles:id,name','manager:id,name');
    }
}