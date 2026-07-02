<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\Mail; // Add this import
use App\Mail\WelcomeEmail; // Add this import
use App\Mail\NewUserRegisteredEmail; // Add this import


class RegisteredUserController extends Controller
{
    /**
     * Show the registration page.
     */
    public function create(): Response
    {
        return Inertia::render('auth/Register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:' . User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'phone' => ['required', 'regex:/^\+?[0-9]{10,15}$/'],
        ]);
    
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'profile_picture' => null,
            'password' => Hash::make($request->password),
            'email_verified_at' => null,
            'role' => 'user',
        ]);
        Mail::to($user->email)->send(new WelcomeEmail($user));
        Mail::to("info@suprememotors.ltd")->send(new NewUserRegisteredEmail($user));
        
        event(new Registered($user));
        Auth::login($user);
        return redirect(route("admin.dashboard"));
    }
    
}
