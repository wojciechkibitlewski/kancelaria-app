<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    // === LISTA: wg uprawnień ===
    public function index(Request $request)
    {
        $query = User::query()->with('roles:id,name', 'manager:id,name');

        // Zastosuj scope wg roli zalogowanego
        $this->applyVisibilityScope($query, $request->user());

        // Proste filtrowanie po roli (opcjonalnie ?role=Handlowiec)
        if ($role = $request->query('role')) {
            $query->whereHas('roles', fn($q) => $q->where('name', $role));
        }

        return $query->orderBy('name')->paginate(20);
    }

    // === PODGLĄD KONKRETNEGO ===
    public function show(Request $request, User $user)
    {
        $this->authorizeUserVisibility($request->user(), $user);
        return $user->load('roles:id,name', 'manager:id,name');
    }

    // === DODANIE NOWEGO (admin/kierownik) ===
    public function store(Request $request)
    {
        $actor = $request->user();
        $this->ensureCanManageUsers($actor);

        $data = $request->validate([
            'name'       => ['required', 'string', 'max:255'],
            'email'      => ['required', 'email', 'max:255', 'unique:users,email'],
            'role'       => ['required', Rule::in(['Zarzad','Kierownik','Handlowiec'])],
            'manager_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        // Jeśli kierownik tworzy Handlowca i nie poda manager_id -> sam jest managerem
        if ($actor->hasRole('Kierownik') && ($data['role'] === 'Handlowiec') && empty($data['manager_id'])) {
            $data['manager_id'] = $actor->id;
        }

        // Nie pozwól Kierownikowi tworzyć Zarządu ani Kierownika
        if ($actor->hasRole('Kierownik') && in_array($data['role'], ['Zarzad','Kierownik'])) {
            abort(403, 'Brak uprawnień do tworzenia tej roli.');
        }

        $user = User::create([
            'name'       => $data['name'],
            'email'      => $data['email'],
            'password'   => null,      // ustawi później przez link
            'manager_id' => $data['manager_id'] ?? null,
            'is_active'  => true,
        ]);

        $user->syncRoles([$data['role']]);

        // Wyślij link do ustawienia hasła (n8n) – używamy istniejącego kontrolera
        request()->merge(['email' => $user->email]);
        app(\App\Http\Controllers\Auth\PasswordController::class)->createLink(request());

        return response()->json([
            'message' => 'Użytkownik utworzony. Wysłano e-mail z linkiem do ustawienia hasła.',
            'user'    => $user->load('roles:id,name','manager:id,name')
        ], 201);
    }

    // === EDYCJA SIEBIE (każdy) ===
    public function updateSelf(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name'  => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users','email')->ignore($user->id)],
            // jeśli chcesz pozwolić na zmianę hasła z poziomu profilu:
            'password' => ['sometimes', 'confirmed', 'min:8'],
        ]);

        if (isset($data['password'])) {
            $user->password = Hash::make($data['password']);
            unset($data['password']);
            unset($data['password_confirmation']);
        }

        $user->fill($data)->save();

        return $user->fresh()->load('roles:id,name','manager:id,name');
    }

    // === EDYCJA INNEGO UŻYTKOWNIKA (kierownik/zarząd) ===
    public function update(Request $request, User $user)
    {
        $actor = $request->user();
        $this->ensureCanManageTarget($actor, $user);

        $data = $request->validate([
            'name'       => ['sometimes', 'string', 'max:255'],
            'email'      => ['sometimes', 'email', 'max:255', Rule::unique('users','email')->ignore($user->id)],
            'role'       => ['sometimes', Rule::in(['Zarzad','Kierownik','Handlowiec'])],
            'manager_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        // Kierownik nie może podnosić nikogo do Kierownika/Zarządu
        if ($actor->hasRole('Kierownik') && isset($data['role']) && in_array($data['role'], ['Zarzad','Kierownik'])) {
            abort(403, 'Brak uprawnień do nadawania tej roli.');
        }

        // Kierownik może edytować tylko swoich Handlowców (i nie może grzebać przy Zarządzie/Kierownikach)
        if ($actor->hasRole('Kierownik')) {
            if (!$user->hasRole('Handlowiec') || $user->manager_id !== $actor->id) {
                abort(403, 'Możesz edytować tylko swoich Handlowców.');
            }
        }

        $user->fill([
            'name'       => $data['name']       ?? $user->name,
            'email'      => $data['email']      ?? $user->email,
            'manager_id' => $data['manager_id'] ?? $user->manager_id,
        ])->save();

        if (isset($data['role'])) {
            $user->syncRoles([$data['role']]);
        }

        return $user->fresh()->load('roles:id,name','manager:id,name');
    }

    // === DEAKTYWACJA / REAKTYWACJA (kierownik/zarząd) ===
    public function setActive(Request $request, User $user)
    {
        $actor = $request->user();
        $this->ensureCanManageTarget($actor, $user);

        $data = $request->validate([
            'is_active' => ['required','boolean'],
        ]);

        // nie pozwól wyłączyć siebie
        if ($user->id === $actor->id) {
            abort(422, 'Nie możesz zmienić aktywności własnego konta.');
        }

        $user->is_active = $data['is_active'];
        $user->save();

        if (!$user->is_active) {
            // wyloguj ze wszystkich urządzeń
            $user->tokens()->delete();
        }

        return response()->json([
            'message' => $user->is_active ? 'Użytkownik aktywny.' : 'Użytkownik zdeaktywowany.',
            'user'    => $user->fresh()->load('roles:id,name','manager:id,name')
        ]);
    }

    // === PONOWNA WYSYŁKA ZAPROSZENIA (link do ustawienia hasła) ===
    public function resendInvite(Request $request, User $user)
    {
        $actor = $request->user();
        $this->ensureCanManageTarget($actor, $user);

        request()->merge(['email' => $user->email]);
        app(\App\Http\Controllers\Auth\PasswordController::class)->createLink(request());

        return response()->json(['message' => 'Wysłano ponownie link do ustawienia hasła.']);
    }


    // Kto co widzi w liście
    protected function applyVisibilityScope($query, User $actor): void
    {
        if ($actor->hasRole('Zarzad')) {
            // widzi wszystkich
            return;
        }

        if ($actor->hasRole('Kierownik')) {
            // widzi siebie i swoich handlowców
            $query->where(function ($q) use ($actor) {
                $q->where('id', $actor->id)
                  ->orWhere('manager_id', $actor->id);
            });
            return;
        }

        // Handlowiec widzi tylko siebie
        $query->where('id', $actor->id);
    }

    // Czy zalogowany może widzieć konkretnego użytkownika
    protected function authorizeUserVisibility(User $actor, User $target): void
    {
        if ($actor->hasRole('Zarzad')) return;

        if ($actor->hasRole('Kierownik')) {
            if ($target->id === $actor->id || $target->manager_id === $actor->id) return;
            abort(403, 'Brak uprawnień.');
        }

        // Handlowiec: tylko sam siebie
        if ($actor->id !== $target->id) abort(403, 'Brak uprawnień.');
    }

    // Czy zalogowany w ogóle może zarządzać użytkownikami
    protected function ensureCanManageUsers(User $actor): void
    {
        if (! $actor->hasAnyRole(['Zarzad','Kierownik'])) {
            abort(403, 'Brak uprawnień.');
        }
    }

    // Czy może zarządzać tym KONKRETNYM użytkownikiem
    protected function ensureCanManageTarget(User $actor, User $target): void
    {
        if ($actor->hasRole('Zarzad')) return;

        if ($actor->hasRole('Kierownik')) {
            // może tylko swoich Handlowców
            if ($target->hasRole('Handlowiec') && $target->manager_id === $actor->id) return;
        }

        abort(403, 'Brak uprawnień do tego użytkownika.');
    }
}