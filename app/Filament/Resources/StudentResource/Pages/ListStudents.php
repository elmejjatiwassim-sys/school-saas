<?php

namespace App\Filament\Resources\StudentResource\Pages;

use App\Filament\Resources\StudentResource;
use App\Models\Classroom;
use App\Services\AiStudentParserService;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;

class ListStudents extends ListRecords
{
    protected static string $resource = StudentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('ai_import')
                ->label('استيراد عبر الذكاء الاصطناعي / Import with AI')
                ->icon('heroicon-o-sparkles')
                ->color('primary')
                ->modalHeading('Import Students from Document / استيراد التلاميذ')
                ->modalDescription('Upload an attendance sheet, class list, PDF, image, or spreadsheet to extract student records automatically.')
                ->form([
                    Forms\Components\FileUpload::make('file')
                        ->label('Document File (PDF, XLSX, CSV, Image)')
                        ->disk('local')
                        ->directory('imports')
                        ->acceptedFileTypes([
                            'application/pdf',
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'text/csv',
                            'text/plain',
                            'image/png',
                            'image/jpeg',
                            'image/webp',
                        ])
                        ->required(),

                    Forms\Components\Select::make('classroom_id')
                        ->label('Classroom (الفصل الدراسي)')
                        ->options(fn () => Classroom::when(
                            Filament::hasTenancy() && Filament::getTenant(),
                            fn ($q) => $q->whereBelongsTo(Filament::getTenant())
                        )->pluck('name', 'id'))
                        ->searchable()
                        ->preload()
                        ->required(),
                ])
                ->action(function (array $data, AiStudentParserService $parser): void {
                    $filePath = Storage::disk('local')->path($data['file']);
                    $schoolId = (int) Filament::getTenant()->getKey();
                    $classroomId = (int) $data['classroom_id'];

                    try {
                        $parsedStudents = $parser->parseFile($filePath);

                        if (empty($parsedStudents)) {
                            Notification::make()
                                ->title('No Students Found')
                                ->body('The AI parser did not detect any student records in the uploaded file.')
                                ->warning()
                                ->send();

                            return;
                        }

                        $importedCount = $parser->importStudents($parsedStudents, $schoolId, $classroomId);

                        Notification::make()
                            ->title('AI Import Complete / اكتمل الاستيراد')
                            ->body("Successfully imported {$importedCount} students with auto-generated registration numbers.")
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Import Failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    } finally {
                        if (file_exists($filePath)) {
                            @unlink($filePath);
                        }
                    }
                }),

            Actions\CreateAction::make(),
        ];
    }
}
