<?php

namespace App\Filament\SuperAdmin\Resources;

use App\Enums\SchoolSubscriptionStatus;
use App\Enums\StudentStatus;
use App\Filament\SuperAdmin\Resources\SchoolResource\Pages;
use App\Models\School;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class SchoolResource extends Resource
{
    protected static ?string $model = School::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-library';

    protected static ?string $navigationLabel = 'Schools (المؤسسات التعليمية)';

    protected static ?string $modelLabel = 'School (مؤسسة)';

    protected static ?string $pluralModelLabel = 'Schools (المؤسسات التعليمية)';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('School Settings')
                    ->tabs([
                        // Tab 1: General Info
                        Forms\Components\Tabs\Tab::make('General Info (المعلومات العامة)')
                            ->icon('heroicon-o-information-circle')
                            ->schema([
                                Forms\Components\Grid::make(2)->schema([
                                    Forms\Components\TextInput::make('name')
                                        ->label('School Name / اسم المؤسسة')
                                        ->required()
                                        ->maxLength(255)
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(function (Forms\Set $set, ?string $state, ?string $operation) {
                                            if ($operation === 'create' && $state) {
                                                $set('slug', Str::slug($state));
                                                $set('code', School::generateUniqueCode($state));
                                            }
                                        }),
                                    Forms\Components\TextInput::make('code')
                                        ->label('Unique Code / الرمز التعريفي (3-4 أحرف)')
                                        ->required()
                                        ->maxLength(4)
                                        ->unique(ignoreRecord: true)
                                        ->dehydrateStateUsing(fn ($state) => strtoupper((string) $state))
                                        ->helperText('Uppercase 3-4 letters unique identifier across the platform.'),
                                    Forms\Components\TextInput::make('slug')
                                        ->label('Portal Slug (URL identifier)')
                                        ->required()
                                        ->unique(ignoreRecord: true)
                                        ->helperText('Tenant URL: /admin/{slug}'),
                                    Forms\Components\TextInput::make('city')
                                        ->label('City / المدينة')
                                        ->maxLength(255),
                                    Forms\Components\TextInput::make('email')
                                        ->label('Contact Email')
                                        ->email()
                                        ->nullable()
                                        ->maxLength(255),
                                    Forms\Components\TextInput::make('phone')
                                        ->label('Contact Phone')
                                        ->tel()
                                        ->nullable()
                                        ->maxLength(255),
                                    Forms\Components\Toggle::make('is_active')
                                        ->label('Active School (مؤسسة مفعلة)')
                                        ->default(true),
                                ]),
                            ]),

                        // Tab 2: Branding (White-Labeling)
                        Forms\Components\Tabs\Tab::make('Branding (الهوية البصرية)')
                            ->icon('heroicon-o-paint-brush')
                            ->schema([
                                Forms\Components\Grid::make(2)->schema([
                                    Forms\Components\FileUpload::make('logo_path')
                                        ->label('School Brand Logo / شعار المؤسسة')
                                        ->image()
                                        ->disk('public')
                                        ->directory('schools/logos')
                                        ->visibility('public')
                                        ->maxSize(2048)
                                        ->helperText('Displayed on the top-left of the tenant admin panel and printable documents.'),
                                    Forms\Components\FileUpload::make('favicon_path')
                                        ->label('Browser Favicon / أيقونة المتصفح')
                                        ->image()
                                        ->disk('public')
                                        ->directory('schools/favicons')
                                        ->visibility('public')
                                        ->maxSize(1024)
                                        ->helperText('Displayed in the browser tab when users visit this school portal.'),
                                    Forms\Components\FileUpload::make('stamp_signature_path')
                                        ->label('School Stamp & Signature / خاتم وتوقيع المؤسسة')
                                        ->image()
                                        ->disk('public')
                                        ->directory('schools/stamps')
                                        ->visibility('public')
                                        ->maxSize(2048)
                                        ->helperText('Displayed on printable payment receipts under La Direction / إدارة المؤسسة.'),
                                ]),
                            ]),

                        // Tab 3: Subscription & Stripe
                        Forms\Components\Tabs\Tab::make('Subscription & Stripe (الاشتراك والفوترة)')
                            ->icon('heroicon-o-credit-card')
                            ->schema([
                                Forms\Components\Grid::make(2)->schema([
                                    Forms\Components\TextInput::make('price_per_student')
                                        ->label('Price per Active Student / سعر التلميذ')
                                        ->numeric()
                                        ->prefix('MAD')
                                        ->default(1.50)
                                        ->required()
                                        ->helperText('Monthly metered charge for each enrolled active student.'),
                                    Forms\Components\TextInput::make('monthly_minimum_charge')
                                        ->label('Monthly Minimum Charge / الحد الأدنى الشهري')
                                        ->numeric()
                                        ->prefix('MAD')
                                        ->default(300.00)
                                        ->required()
                                        ->helperText('Base charge applied if metered usage is below this threshold.'),
                                    Forms\Components\Select::make('subscription_status')
                                        ->label('Subscription Status / حالة الاشتراك')
                                        ->options(SchoolSubscriptionStatus::class)
                                        ->default(SchoolSubscriptionStatus::Active->value)
                                        ->required(),
                                    Forms\Components\DatePicker::make('billing_start_date')
                                        ->label('Billing Start Date / تاريخ بدء الفوترة')
                                        ->default(now()->toDateString())
                                        ->required()
                                        ->helperText('Tuition invoices will NOT be generated for academic months prior to this date.'),
                                    Forms\Components\TextInput::make('stripe_customer_id')
                                        ->label('Stripe Customer ID')
                                        ->placeholder('cus_...')
                                        ->hintAction(
                                            Forms\Components\Actions\Action::make('open_stripe')
                                                ->label('View in Stripe')
                                                ->icon('heroicon-o-arrow-top-right-on-square')
                                                ->url(fn ($state) => $state ? "https://dashboard.stripe.com/customers/{$state}" : null, true)
                                        ),
                                    Forms\Components\TextInput::make('stripe_payment_method_id')
                                        ->label('Stripe Default Payment Method')
                                        ->placeholder('pm_...')
                                        ->maxLength(255),
                                    Forms\Components\TextInput::make('ai_monthly_messages_quota')
                                        ->label('كوطة رسائل AI الشهرية / Monthly AI Messages Limit')
                                        ->numeric()
                                        ->minValue(0)
                                        ->default(50)
                                        ->required()
                                        ->helperText('Pre-filled with default 50. Super Admin can increase or decrease it per school at any time.'),
                                    Forms\Components\TextInput::make('ai_messages_used_this_month')
                                        ->label('الرسائل المستهلكة هذا الشهر / AI Messages Used This Month')
                                        ->numeric()
                                        ->default(0)
                                        ->disabled()
                                        ->dehydrated(false)
                                        ->helperText('Resets automatically to 0 on the 1st of each active academic month.'),
                                ]),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount([
                'students as active_students_count' => fn ($q) => $q->where('status', StudentStatus::Active),
            ]))
            ->columns([
                Tables\Columns\ImageColumn::make('logo_path')
                    ->label('Logo')
                    ->disk('public')
                    ->circular()
                    ->defaultImageUrl(fn () => 'https://ui-avatars.com/api/?background=e2e8f0&color=64748b&name=School'),
                Tables\Columns\TextColumn::make('name')
                    ->label('School Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->badge()
                    ->color('info')
                    ->copyable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('city')
                    ->label('City')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('active_students_count')
                    ->label('Active Students')
                    ->badge()
                    ->color('success')
                    ->sortable(),
                Tables\Columns\TextColumn::make('price_per_student')
                    ->label('Rate / Student')
                    ->money('MAD')
                    ->sortable(),
                Tables\Columns\TextColumn::make('estimated_monthly_bill')
                    ->label('Estimated Bill')
                    ->getStateUsing(fn (School $record): string => number_format($record->estimated_monthly_bill, 2).' MAD')
                    ->badge()
                    ->color('warning'),
                Tables\Columns\TextColumn::make('subscription_status')
                    ->label('Subscription')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('billing_start_date')
                    ->label('Billing Start')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('subscription_status')
                    ->options(SchoolSubscriptionStatus::class),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status'),
            ])
            ->actions([
                Tables\Actions\Action::make('open_tenant_portal')
                    ->label('School Portal')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn (School $record) => "/admin/{$record->slug}", shouldOpenInNewTab: true),
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
            'index' => Pages\ListSchools::route('/'),
            'create' => Pages\CreateSchool::route('/create'),
            'edit' => Pages\EditSchool::route('/{record}/edit'),
        ];
    }
}
