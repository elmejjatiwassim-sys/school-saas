<?php

namespace App\Filament\Pages;

use App\Models\School;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class SchoolSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'Settings & Users';

    protected static ?int $navigationSort = 10;

    protected static ?string $title = 'School Settings (إعدادات المؤسسة)';

    protected static string $view = 'filament.pages.school-settings';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        /** @var School|null $school */
        $school = Filament::getTenant();
        if ($school) {
            $this->form->fill($school->attributesToArray());
        }
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('General Information (المعلومات العامة)')
                    ->schema([
                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\TextInput::make('name')
                                ->label('School Name / اسم المؤسسة')
                                ->required()
                                ->maxLength(255),
                            Forms\Components\TextInput::make('phone')
                                ->label('Contact Phone / رقم الهاتف')
                                ->tel()
                                ->nullable()
                                ->maxLength(255),
                            Forms\Components\TextInput::make('email')
                                ->label('Contact Email / البريد الإلكتروني')
                                ->email()
                                ->nullable()
                                ->maxLength(255),
                            Forms\Components\TextInput::make('city')
                                ->label('City / المدينة')
                                ->nullable()
                                ->maxLength(255),
                        ]),
                    ]),

                Forms\Components\Section::make('Branding & Stamp (الهوية البصرية وخاتم المؤسسة)')
                    ->schema([
                        Forms\Components\Grid::make(3)->schema([
                            Forms\Components\FileUpload::make('logo_path')
                                ->label('School Brand Logo / شعار المؤسسة')
                                ->image()
                                ->disk('public')
                                ->directory('schools/logos')
                                ->visibility('public')
                                ->maxSize(2048),
                            Forms\Components\FileUpload::make('favicon_path')
                                ->label('Browser Favicon / أيقونة المتصفح')
                                ->image()
                                ->disk('public')
                                ->directory('schools/favicons')
                                ->visibility('public')
                                ->maxSize(1024),
                            Forms\Components\FileUpload::make('stamp_signature_path')
                                ->label('School Stamp & Signature / خاتم وتوقيع المؤسسة')
                                ->image()
                                ->disk('public')
                                ->directory('schools/stamps')
                                ->visibility('public')
                                ->maxSize(2048)
                                ->helperText('صورة الخاتم والتوقيع الرقمي للمؤسسة، تظهر في أسفل وصولات الأداء المطبوعة.'),
                        ]),
                    ]),
            ])
            ->statePath('data')
            ->model(Filament::getTenant());
    }

    public function save(): void
    {
        /** @var School|null $school */
        $school = Filament::getTenant();
        if (! $school) {
            return;
        }

        $data = $this->form->getState();
        $school->update($data);

        Notification::make()
            ->title('Settings Saved (تم حفظ الإعدادات)')
            ->success()
            ->send();
    }
}
