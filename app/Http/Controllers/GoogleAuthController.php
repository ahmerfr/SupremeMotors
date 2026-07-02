<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Throwable;
use Illuminate\Support\Facades\Mail;
use App\Mail\WelcomeEmail;
use App\Mail\NewUserRegisteredEmail;

class GoogleAuthController extends Controller
{
    /**
     * Redirect the user to Google's OAuth page.
     */
    public function redirect()
    {
        return Socialite::driver('google')->redirect();
    }

    /**
     * Handle the callback from Google.
     */
    public function callback()
    {
        try {
            // Get user information from Google
            $user = Socialite::driver('google')->user();
        } catch (Throwable $e) {
            return redirect('/')->with('error', 'Google authentication failed.');
        }

        $existing_user = User::where('email', $user->email)->first();

        if ($existing_user) {
            Auth::login($existing_user);
        } else {
            $new_user = User::updateOrCreate([
                'email' => $user->email
            ], [
                'name' => $user->name,
                'profile_picture' => $user->avatar,
                'password' => bcrypt(Str::random(16)),
                'role' => 'user',
                'email_verified_at' => now()
            ]);

            Mail::to($new_user->email)->send(new WelcomeEmail($new_user));
            Mail::to("info@suprememotors.ltd")->send(new NewUserRegisteredEmail($new_user));

            Auth::login($new_user);
        }

        return redirect(route("admin.dashboard"));
    }
}
