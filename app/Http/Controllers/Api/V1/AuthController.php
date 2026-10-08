<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AuthController
{
    public function login(Request $request)
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $user = User::where('email', $credentials['email'])->first();
        $mfaProtectedRoles = ['super_admin', 'admin', 'directeur_general', 'gestionnaire', 'agent_dossier', 'agent_finance', 'agent_recherche', 'agent_communication'];
        $requiresMfa = $user && DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_id', $user->id)
            ->where('model_has_roles.model_type', User::class)
            ->whereIn('roles.name', $mfaProtectedRoles)
            ->exists();

        if (! $user || ! Hash::check($credentials['password'], $user->password) || $user->status !== 'active' || $requiresMfa) {
            throw ValidationException::withMessages(['email' => ['Les identifiants sont invalides.']]);
        }
        $token = $user->createToken('api-v1')->plainTextToken;
        return response()->json(['data' => ['token' => $token, 'user' => new UserResource($user)]], 201);
    }

    public function me(Request $request)
    {
        return response()->json(['data' => new UserResource($request->user())]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();
        return response()->json(['data' => null], 204);
    }
}
