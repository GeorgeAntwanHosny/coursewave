<?php

namespace App\Filament\Instructor\Pages;

use App\Models\Certificate;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class CourseCertificates extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';
    static string | UnitEnum | null $navigationGroup  = 'Community';
    protected static ?string $navigationLabel = 'Certificates';
    protected string $view = 'filament.instructor.pages.course-certificates';

    public function table(Table $table): Table
    {
        return $table
            // Only certificates for THIS instructor's courses (query-scope layer). Read-only.
            ->query(Certificate::query()->whereHas('course', fn (Builder $q) => $q->where('instructor_id', Auth::id())))
            ->columns([
                Tables\Columns\TextColumn::make('student_name')->label('Student')->searchable(),
                Tables\Columns\TextColumn::make('course_title')->label('Course')->searchable(),
                Tables\Columns\TextColumn::make('certificate_number')->label('Number')->copyable()->searchable(),
                Tables\Columns\TextColumn::make('issued_at')->label('Issued')->date()->sortable(),
            ])
            ->defaultSort('issued_at', 'desc')
            ->emptyStateHeading('No certificates issued for your courses yet');
    }
}
