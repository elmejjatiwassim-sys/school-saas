<?php

namespace App\Filament\Pages\Auth;

use App\Models\User;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Auth\Login as BaseLogin;

class Login extends BaseLogin
{
    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')
            ->label('اسم المستخدم أو البريد الإلكتروني / Username or Email')
            ->required()
            ->autocomplete()
            ->autofocus()
            ->extraInputAttributes(['tabindex' => 1]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function getCredentialsFromFormData(array $data): array
    {
        $login = trim((string) $data['email']);
        $isEmail = filter_var($login, FILTER_VALIDATE_EMAIL) !== false;

        if (! $isEmail) {
            $matchedUsername = User::whereRaw('LOWER(username) = ?', [strtolower($login)])->value('username');
            if ($matchedUsername) {
                $login = $matchedUsername;
            }

            return [
                'username' => $login,
                'password' => $data['password'],
            ];
        }

        return [
            'email' => $login,
            'password' => $data['password'],
        ];
    }
}
