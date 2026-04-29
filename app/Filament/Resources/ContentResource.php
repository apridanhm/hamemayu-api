<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContentResource\Pages;
use App\Filament\Resources\ContentResource\RelationManagers;
use App\Models\Content;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ContentResource extends Resource
{
    protected static ?string $model = Content::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Basic Info
                Section::make('Informasi Dasar')
                    ->schema([
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        
                        TextInput::make('slug')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->columnSpanFull(),
                        
                        Select::make('category_id')
                            ->relationship('category', 'name')
                            ->required()
                            ->searchable(),
                        
                        Textarea::make('excerpt')
                            ->maxLength(65535)
                            ->columnSpanFull(),
                    ])->columns(2),
    
                // Content
                Section::make('Konten Lengkap')
                    ->schema([
                        RichEditor::make('content')
                            ->columnSpanFull()
                            ->toolbarButtons([
                                'attachFiles',
                                'blockquote',
                                'bold',
                                'bulletList',
                                'codeBlock',
                                'h2',
                                'h3',
                                'italic',
                                'link',
                                'orderedList',
                                'redo',
                                'strike',
                                'underline',
                                'undo',
                            ]),
                    ]),
    
                // Location & Info
                Section::make('Lokasi & Informasi Praktis')
                    ->schema([
                        TextInput::make('lat')
                            ->numeric()
                            ->label('Latitude')
                            ->helperText('Koordinat peta (kosongkan jika tidak ada)'),
                        
                        TextInput::make('lng')
                            ->numeric()
                            ->label('Longitude')
                            ->helperText('Koordinat peta (kosongkan jika tidak ada)'),
                        
                        TextInput::make('opening_hours')
                            ->maxLength(255)
                            ->label('Jam Buka'),
                        
                        TextInput::make('ticket_price')
                            ->maxLength(255)
                            ->label('Harga Tiket'),
                        
                        TextInput::make('cover_image')
                            ->url()
                            ->label('URL Gambar Utama')
                            ->helperText('Masukkan URL gambar dari Unsplash/Google'),
                    ])->columns(2),
    
                // Status
                Section::make('Status Publikasi')
                    ->schema([
                        Toggle::make('is_featured')
                            ->label('Featured (Tampil di Homepage)'),
                        
                        Select::make('status')
                            ->options([
                                'draft' => 'Draft',
                                'published' => 'Published',
                            ])
                            ->default('draft')
                            ->required(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                
                TextColumn::make('category.name')
                    ->sortable(),
                
                BadgeColumn::make('status')
                    ->colors([
                        'success' => 'published',
                        'warning' => 'draft',
                    ]),
                
                IconColumn::make('is_featured')
                    ->boolean()
                    ->label('Featured'),
                
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->relationship('category', 'name'),
                
                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'published' => 'Published',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }


    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContents::route('/'),
            'create' => Pages\CreateContent::route('/create'),
            'edit' => Pages\EditContent::route('/{record}/edit'),
        ];
    }
}
