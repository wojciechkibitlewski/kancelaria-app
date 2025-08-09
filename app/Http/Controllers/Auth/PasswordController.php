<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class PasswordController extends Controller
{
    // 1) Tworzy link do ustawienia hasła i wysyła go do n8n
    public function createLink(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->firstOrFail();

        // a) wygeneruj token resetu (Laravel zrobi wpis w tabeli z tokenem)
        $token = Password::getRepository()->create($user);

        // b) złóż link do strony WeWeb (to będzie Wasz ekran "Ustaw hasło")
        $frontend = config('services.frontend_url'); // np. https://app.twojadomena.pl
        $link = $frontend . '/reset-password?token=' . urlencode($token) . '&email=' . urlencode($user->email);

        // c) wyślij do n8n
        $payload = [
            'email' => $user->email,
            'name'  => $user->name ?? '',
            'link'  => $link,
        ];

        $requestToN8n = Http::withHeaders($this->signatureHeaders($payload))
            ->post(config('services.n8n.reset_webhook'), $payload);

        if (!$requestToN8n->successful()) {
            return response()->json(['message' => 'Nie udało się zainicjować wysyłki maila'], 502);
        }

        return response()->json(['message' => 'Link wygenerowany i przekazany do n8n']);
    }

    // 2) Ustawienie nowego hasła po kliknięciu w link z maila
    public function reset(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'token' => 'required',
            'password' => 'required|confirmed|min:8',
        ]);

        $status = Password::reset(
            $request->only('email', 'token', 'password', 'password_confirmation'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => bcrypt($password),
                    'remember_token' => Str::random(60),
                ])->save();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json(['message' => 'Token nieprawidłowy lub wygasł'], 422);
        }

        return response()->json(['message' => 'Hasło ustawione. Możesz się zalogować.']);
    }

    // (Opcjonalnie) Podpisz żądanie HMAC, żeby n8n mógł zweryfikować, że to my
    protected function signatureHeaders(array $payload): array
    {
        $secret = config('services.n8n.secret');
        if (!$secret) return [];

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $sig  = hash_hmac('sha256', $body, $secret);

        return ['x-signature' => $sig];
    }
}