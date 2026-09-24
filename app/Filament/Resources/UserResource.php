<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use App\Services\CredentialService;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Settings & Users';

    protected static ?string $navigationLabel = 'Staff & Users (الحسابات والموظفون)';

    protected static ?string $modelLabel = 'User Account (حساب)';

    protected static ?string $pluralModelLabel = 'User Accounts (الحسابات)';

    protected static ?int $navigationSort = 9;

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (Filament::hasTenancy() && Filament::getTenant()) {
            $query->where('school_id', Filament::getTenant()->getKey())
                ->where('is_super_admin', false);
        }

        return $query;
    }

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
                                ->default(fn () => Filament::getTenant() ? app(CredentialService::class)->generateUniqueUsername(Filament::getTenant()) : null)
                                ->helperText('Unique username for school portal login.'),

                            Forms\Components\TextInput::make('email')
                                ->label('Email Address / البريد الإلكتروني')
                                ->email()
                                ->nullable()
                                ->unique(ignoreRecord: true)
                                ->maxLength(255),

                            Forms\Components\Select::make('role')
                                ->label('Role / الدور')
                                ->options([
                                    'admin' => 'School Admin (مدير المؤسسة)',
                                    'supervisor' => 'General Supervisor (حارس عام / Surveillant Général)',
                                    'teacher' => 'Teacher (أستاذ)',
                                    'student' => 'Student (تلميذ)',
                                    'staff' => 'Staff / Assistant (موظف إداري)',
                                ])
                                ->default('teacher')
                                ->required(),

                            Forms\Components\TextInput::make('password')
                                ->label('Password / كلمة المرور')
                                ->password()
                                ->revealable()
                                ->required(fn (string $operation): bool => $operation === 'create')
                                ->dehydrated(fn (?string $state): bool => filled($state))
                                ->dehydrateStateUsing(fn (string $state): string => Hash::make($state))
                                ->helperText('Leave empty to preserve existing password.'),

                            Forms\Components\Toggle::make('must_change_password')
                                ->label('Must Change Password / إجبار تغيير كلمة المرور عند الدخول')
                                ->default(true),

                            Forms\Components\Toggle::make('is_active')
                                ->label('Account Active (حساب مفعل)')
                                ->default(true),
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

                Tables\Columns\TextColumn::make('role')
                    ->label('Role')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'admin' => 'primary',
                        'supervisor', 'general_supervisor' => 'warning',
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
                Tables\Filters\SelectFilter::make('role')
                    ->options([
                        'admin' => 'School Admin',
                        'supervisor' => 'General Supervisor',
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
                        $currentTenant = Filament::getTenant();
                        abort_unless($currentTenant && $record->school_id === $currentTenant->id, 403);

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
