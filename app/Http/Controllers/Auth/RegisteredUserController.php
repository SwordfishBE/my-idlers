<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Settings;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector|\Illuminate\View\View
     */
    public function create()
    {
        $maxUsers = (int) config('auth.max_users', 1);
        if ($maxUsers > 0 && User::count() >= $maxUsers) {
            return redirect('/login');
        }
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\RedirectResponse
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $createUser = function () use ($request) {
            $maxUsers = (int) config('auth.max_users', 1);
            if ($maxUsers > 0 && User::count() >= $maxUsers) {
                return null;
            }
            return User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'api_token' => Str::random(60),
            ]);
        };

        try {
            // The file lock serializes registrations on this installation, even
            // when another cache driver is used for ordinary application data.
            $user = Cache::store('file')->lock('myidlers-registration', 120)->block(5, $createUser);
        } catch (LockTimeoutException $exception) {
            return redirect()->route('login')->with('error', 'Registration is temporarily unavailable. Please try again.');
        }
        if (! $user) {
            return redirect()->route('login')->with('error', 'Registration is currently closed.');
        }

        event(new Registered($user));

        Auth::login($user);

        return redirect('/');
    }
}
