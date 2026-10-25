<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use App\Services\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function registerForm(): View
    {
        return view('auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        $data = $request->validate(['name' => 'required|string|max:120', 'email' => 'required|email|max:255|unique:users', 'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()]]);
        $user = DB::transaction(fn () => User::create($data));
        $user->sendEmailVerificationNotification();
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('verification.notice')->with('status', 'Account created. Verification email queued. One account can hold several workspace roles.');
    }

    public function profile(Request $request): View
    {
        $membership = $request->user()->memberships()->whereHas('workspace', fn ($q) => $q->where('kind', 'personal'))->with('roles')->sole();

        return view('workspaces.profile', compact('membership'));
    }

    public function saveProfile(Request $request, Tenancy $tenancy): RedirectResponse
    {
        $data = $request->validate(['name' => 'required|string|max:120', 'roles' => 'required|array|min:1', 'roles.*' => ['required', Rule::in(config('workspaces.personal_roles'))], 'profession' => ['nullable', Rule::in(array_keys(config('workspaces.professions')))]]);
        abort_if(! empty($data['profession']) && ! in_array('service_provider', $data['roles'], true), 422, 'A profession requires the service provider role.');
        DB::transaction(function () use ($request, $data, $tenancy): void {
            $user = $request->user();
            $user->update(['name' => $data['name']]);
            $membership = $user->memberships()->whereHas('workspace', fn ($q) => $q->where('kind', 'personal'))->sole();
            $membership->roles()->sync(Role::whereIn('name', $data['roles'])->pluck('id'));
            $membership->update(['profession' => $data['profession'] ?? null]);
            $tenancy->audit($user, 'account.profile_updated', (string) $user->id, ['personal_roles' => $data['roles']]);
        });

        return back()->with('status', 'Profile and personal roles updated. Professional credentials are not verified by selecting a role.');
    }

    public function security(Request $request): View
    {
        $sessions = config('session.driver') === 'database' ? DB::table('sessions')->where('user_id', $request->user()->id)->orderByDesc('last_activity')->get() : collect();

        return view('workspaces.security', compact('sessions'));
    }

    public function password(Request $request, Tenancy $tenancy): RedirectResponse
    {
        $data = $request->validate(['current_password' => 'required|current_password', 'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()]]);
        DB::transaction(function () use ($request, $data, $tenancy): void {
            $request->user()->forceFill(['password' => $data['password'], 'remember_token' => Str::random(60)])->save();
            if (config('session.driver') === 'database') {
                DB::table('sessions')->where('user_id', $request->user()->id)->where('id', '!=', $request->session()->getId())->delete();
            }
            $tenancy->audit($request->user(), 'account.password_changed', (string) $request->user()->id);
        });

        return back()->with('status', 'Password changed. Other database sessions were ended.');
    }

    public function endSessions(Request $request, Tenancy $tenancy): RedirectResponse
    {
        $request->validate(['current_password' => 'required|current_password']);
        if (config('session.driver') === 'database') {
            DB::table('sessions')->where('user_id', $request->user()->id)->where('id', '!=', $request->session()->getId())->delete();
        }
        $request->user()->forceFill(['remember_token' => Str::random(60)])->save();
        $tenancy->audit($request->user(), 'account.other_sessions_ended', (string) $request->user()->id);

        return back()->with('status', 'Other sessions ended.');
    }

    public function preferences(Request $request): View
    {
        return view('workspaces.preferences', ['preferences' => $request->user()->notification_preferences ?? ['database' => true, 'mail' => true]]);
    }

    public function savePreferences(Request $request, Tenancy $tenancy): RedirectResponse
    {
        $request->validate(['database' => 'sometimes|boolean', 'mail' => 'sometimes|boolean']);
        $request->user()->forceFill(['notification_preferences' => ['database' => $request->boolean('database'), 'mail' => $request->boolean('mail')]])->save();
        $tenancy->audit($request->user(), 'account.preferences_updated', (string) $request->user()->id);

        return back()->with('status', 'Notification preferences updated. Verification and recovery emails remain available.');
    }
}
