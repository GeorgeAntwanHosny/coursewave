<?php

namespace App\Filament\Admin\Pages;

use App\Actions\Learning\GenerateCertificatePdfAction;
use App\Jobs\GenerateCertificatePdfJob;
use App\Models\Certificate;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use UnitEnum;

class Certificates extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';
    protected static string|UnitEnum|null $navigationGroup = 'Learning';
    protected static ?string $navigationLabel = 'Certificates';
    protected string $view = 'filament.admin.pages.certificates';

    public function table(Table $table): Table
    {
        return $table
            ->query(Certificate::query()->with('course'))
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('#')->sortable(),
                Tables\Columns\TextColumn::make('student_name')->label('Student')->searchable(),
                Tables\Columns\TextColumn::make('course_title')->label('Course')->searchable(),
                Tables\Columns\TextColumn::make('instructor_name')->label('Instructor')->searchable(),
                Tables\Columns\TextColumn::make('certificate_number')->label('Number')->copyable()->searchable(),
                Tables\Columns\IconColumn::make('pdf_ready')->label('PDF')->boolean()
                    ->state(fn (Certificate $record) => $record->pdf_path !== null),
                Tables\Columns\TextColumn::make('issued_at')->label('Issued')->date()->sortable(),
            ])
            ->recordActions([
                Action::make('download')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn (Certificate $record) => $record->pdf_path !== null && Gate::allows('view', $record))
                    ->action(fn (Certificate $record) => Storage::disk(GenerateCertificatePdfAction::DISK)->download(
                        $record->pdf_path,
                        "CourseWave-Certificate-{$record->certificate_number}.pdf"
                    )),

                Action::make('regenerate')
                    ->label('Regenerate PDF')
                    ->icon('heroicon-o-arrow-path')
                    ->requiresConfirmation()
                    ->modalDescription('Re-renders the PDF from the stored snapshot (same number, same details).')
                    ->action(function (Certificate $record) {
                        GenerateCertificatePdfJob::dispatch($record->id, force: true);
                        Notification::make()->title('PDF regeneration queued')->success()->send();
                    }),
            ])
            ->defaultSort('id', 'desc');
    }
}
