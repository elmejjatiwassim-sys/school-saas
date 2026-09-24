<?php

namespace App\Filament\SuperAdmin\Resources;

use App\Filament\SuperAdmin\Resources\UserResource\Pages;
use App\Models\User;
use App\Services\CredentialService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Users (المستخدمون والحسابات)';

    protected static ?string $modelLabel = 'User (مستخدم)';

    protected static ?string $pluralModelLabel = 'Users (المستخدمون)';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('User Account Information')
                    ->schema([
                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\TextInput::make('name')
                                ->label('Full Name / الاسم الكامل')
                                ->required()
                                ->maxLength(255),

                            Forms\Components\TextInput::make('username')
                                ->label('Username / اسم المستخدم')
                                ->required()
                                ->unique(ignoreRecord: true)
                                ->maxLength(255)
                                ->helperText('Unique username for portal sign in.'),

                            Forms\Components\TextInput::make('email')
                                ->label('Email Address / البريد الإلكتروني')
                                ->email()
                                ->nullable()
                                ->unique(ignoreRecord: true)
                                ->maxLength(255),

                            Forms\Components\Select::make('school_id')
                                ->label('School / المؤسسة التعليمية')
                                ->relationship('school', 'name')
                                ->searchable()
                                ->preload()
                                ->nullable()
                                ->helperText('Leave empty for global platform administrators.'),

                            Forms\Components\Select::make('role')
                                ->label('Role / الدور')
                                ->options([
                                    'admin' => 'School Admin (مدير المؤسسة)',
                                    'teacher' => 'Teacher (أستاذ)',
                                    'student' => 'Student (تلميذ)',
                                    'staff' => 'Staff / Assistant (موظف إداري)',
                                ]),

                            Forms\Components\TextInput::make('password')
                                ->label('Password / كلمة المرور')
                                ->password()
                                ->revealable()
                                ->required(fn (string $operation): bool => $operation === 'create')
                                ->dehydrated(fn (?string $state): bool => filled($state))
                                ->dehydrateStateUsing(fn (string $state): string => Hash::make($state))
                                ->helperText('Leave empty on edit to keep current password.'),

                            Forms\Components\Toggle::make('must_change_password')
                                ->label('Must Change Password / إجبار تغيير كلمة المرور عند الدخول')
                                ->default(false),

                            Forms\Components\Toggle::make('is_active')
                                ->label('Account Active (حساب مفعل)')
                                ->default(true),

                            Forms\Components\Toggle::make('is_super_admin')
                                ->label('Super Administrator (مسؤول منصة عام)')
                                ->default(false),
                        ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('username')
                    ->label('Username')
                    ->badge()
                    ->color('info')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable()
                    ->default('—'),

                Tables\Columns\TextColumn::make('school.name')
                    ->label('School Name')
                    ->badge()
                    ->color('gray')
                    ->default('Global / Platform')
                    ->sortable(),

                Tables\Columns\TextColumn::make('role')
                    ->label('Role')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'admin' => 'primary',
                        'teacher' => 'info',
                        'student' => 'success',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\IconColumn::make('must_change_password')
                    ->label('Must Change PW')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('school_id')
                    ->label('School')
                    ->relationship('school', 'name'),
                Tables\Filters\SelectFilter::make('role')
                    ->options([
                        'admin' => 'Admin',
                        'teacher' => 'Teacher',
                        'student' => 'Student',
                        'staff' => 'Staff',
                    ]),
                Tables\Filters\TernaryFilter::make('must_change_password')
                    ->label('Must Change Password'),
            ])
            ->actions([
                Tables\Actions\Action::make('reset_password')
                    ->label('إعادة تعيين كلمة المرور / Reset Password')
                    ->icon('heroicon-o-key')
                    ->color('warning')
                    ->modalHeading('Reset User Password / إعادة تعيين كلمة المرور')
                    ->modalDescription(fn (User $record): string => "Set a new password for {$record->name} ({$record->username}).")
                    ->form([
                        Forms\Components\Radio::make('mode')
                            ->label('Generation Mode / طريقة التعيين')
                            ->options([
                                'generate' => 'توليد كلمة سر عشوائية / Generate Random',
                                'manual' => 'إدخال كلمة مرور يدوية / Manual Password',
                            ])
                            ->default('generate')
                            ->live(),

                        Forms\Components\TextInput::make('password')
                            ->label('New Password / كلمة المرور الجديدة')
                            ->password()
                            ->revealable()
                            ->visible(fn (Forms\Get $get): bool => $get('mode') === 'manual')
                            ->required(fn (Forms\Get $get): bool => $get('mode') === 'manual')
                            ->minLength(6),
                    ])
                    ->action(function (User $record, array $data, CredentialService $credentialService): void {
                        $plainPassword = $data['mode'] === 'manual' && ! empty($data['password'])
                            ? $data['password']
                            : $credentialService->generateTemporaryPassword();

                        $record->update([
                            'password' => Hash::make($plainPassword),
                            'temporary_password' => $plainPassword,
                            'must_change_password' => true,
                        ]);

                        Notification::make()
                            ->title('Password Reset Successfully (تمت إعادة تعيين كلمة المرور)')
                            ->body("New password for {$record->username}: {$plainPassword}")
                            ->success()
                            ->persistent()
                            ->send();
                    }),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
