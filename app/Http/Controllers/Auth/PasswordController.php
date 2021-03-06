<?php

namespace Coyote\Http\Controllers\Auth;

use Coyote\Http\Controllers\Controller;
use Illuminate\Mail\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Auth;

class PasswordController extends Controller
{
    /**
     * Formularz generuje link umozliwiajacy reset hasla
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function getIndex()
    {
        $this->breadcrumb->push('Odzyskiwanie hasła', url('Password'));

        return $this->view('auth.password');
    }

    /**
     * Formularz powoduje wyslanie linku umozliwiajacego zmiane hasla
     *
     * @param Request $request
     * @return $this|\Illuminate\Http\RedirectResponse
     */
    public function postIndex(Request $request)
    {
        $this->validate($request, [
            'email'                     => 'required|email|exists:users'
        ]);

        // aby wygenerowac link konto musi byc aktywne oraz e-mail musi byc wczesniej potwierdzony
        $credentials = $request->only('email') + ['is_active' => 1, 'is_confirm' => 1];

        $response = Password::sendResetLink($credentials, function (Message $message) {
            $message->subject('Ustaw nowe hasło w serwisie 4programmers.net');
        });

        switch ($response) {
            case Password::RESET_LINK_SENT:
                return redirect()->back()->with('success', 'Na podany adres e-mail wysłane zostały dalsze instrukcje');

            case Password::INVALID_USER:
                return redirect()->back()->withErrors([
                    'email' => sprintf(
                        'Ten adres e-mail nie został zweryfikowany. %s aby go potwierdzić', link_to('Confirm', 'Kliknij')
                    )
                ]);
        }
    }

    /**
     * Widok generowania nowego hasla
     *
     * @param  string $token
     * @return \Illuminate\View\View
     */
    public function getReset($token = null)
    {
        if (is_null($token)) {
            abort(404);
        }

        $this->breadcrumb->push('Odzyskiwanie hasła', url('Password/reset'));

        return $this->view('auth.reset')->with('token', $token);
    }

    /**
     * Ustawienie nowego hasla i ponowne logowanie uzytkownika
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function postReset(Request $request)
    {
        $this->validate($request, [
            'token'    => 'required',
            'email'    => 'required|email',
            'password' => 'required|confirmed|min:3',
        ]);

        $credentials = $request->only(
            'email', 'password', 'password_confirmation', 'token'
        ) + ['is_confirm' => 1]; // <-- its important! only record with confirmed email address!!

        // walidacja poprawnosci hasla. jedyne wymaganie to posiadanie hasla max 3 znakowego
        Password::validator(function () {
            return true;
        });

        $response = Password::reset($credentials, function ($user, $password) {
            $user->password = bcrypt($password);
            $user->save();

            Auth::login($user);
        });

        switch ($response) {
            case Password::PASSWORD_RESET:
                return redirect(route('home'))->with('success', 'Hasło zostało ustawione. Zostałeś prawidłowo zalogowany.');

            default:
                $errors = [
                    Password::INVALID_USER => 'Podany adres e-mail nie jest przypisany do żadnego konta',
                    Password::INVALID_TOKEN => 'URL jest nieprawidłowy. Być może ten link nie jest już aktywny?'
                ];

                return redirect()->back()
                    ->withInput($request->only('email'))
                    ->withErrors(['email' => $errors[$response]]);
        }
    }
}
