<?php

namespace App\Filament\Pages\Auth;

use Filament\Forms\Components\Component;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Pages\Auth\EditProfile as BaseEditProfile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class EditProfile extends BaseEditProfile
{
    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')
            ->label(__('filament-panels::pages/auth/edit-profile.form.email.label'))
            ->email()
            ->nullable()
            ->maxLength(255)
            ->unique(ignoreRecord: true);
    }

    protected function getPasswordFormComponent(): Component
    {
        return TextInput::make('password')
            ->label(__('filament-panels::pages/auth/edit-profile.form.password.label'))
            ->password()
            ->revealable(filament()->arePasswordsRevealable())
            ->rule(Password::default())
            ->autocomplete('new-password')
            ->dehydrated(fn ($state): bool => filled($state))
            ->dehydrateStateUsing(fn ($state): string => Hash::make($state))
            ->live(debounce: 500)
            ->same('passwordConfirmation');
    }

    protected function getForms(): array
    {
        return [
            'form' => $this->form(
                $this->makeForm()
                    ->schema([
                        $this->getNameFormComponent(),
                        $this->getEmailFormComponent(),
                        TextInput::make('current_password')
                            ->label('Current Password / كلمة المرور الحالية')
                            ->password()
                            ->revealable()
                            ->currentPassword()
                            ->dehydrated(false)
                            ->visible(fn (Get $get): bool => filled($get('password')))
                            ->required(fn (Get $get): bool => filled($get('password'))),
                        $this->getPasswordFormComponent(),
                        $this->getPasswordConfirmationFormComponent(),
                    ])
                    ->operation('edit')
                    ->model($this->getUser())
                    ->statePath('data')
                    ->inlineLabel(! static::isSimple()),
            ),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (filled($data['password'] ?? null)) {
            $data['must_change_password'] = false;
        }

        unset($data['current_password']);

        $record->update($data);

        return $record;
    }
}
