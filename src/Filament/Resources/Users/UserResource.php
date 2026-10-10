<?php

namespace Melasistema\MelaCms\Filament\Resources\Users;

use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Melasistema\MelaCms\Enums\Role;
use Melasistema\MelaCms\Filament\Resources\Users\Pages\CreateUser;
use Melasistema\MelaCms\Filament\Resources\Users\Pages\EditUser;
use Melasistema\MelaCms\Filament\Resources\Users\Pages\ListUsers;

/**
 * The users of the admin panel, each with one core role.
 *
 * A site that needs more fields extends this class, overrides
 * formComponents() or tableColumns(), and sets its class as
 * `resources.users` in config/mela-cms.php.
 */
class UserResource extends Resource
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $slug = 'users';

    public static function getModel(): string
    {
        return config('auth.providers.users.model');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components(static::formComponents());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns(static::tableColumns())
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    /**
     * @return array<Component>
     */
    public static function formComponents(): array
    {
        return [
            TextInput::make('name')
                ->required()
                ->maxLength(255),
            TextInput::make('email')
                ->email()
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true),
            TextInput::make('password')
                ->password()
                ->revealable()
                ->rule(Password::default())
                // The stored hash never reaches the browser, even if the model does not hide it.
                ->afterStateHydrated(fn (TextInput $component) => $component->state(null))
                ->required(fn (string $operation): bool => $operation === 'create')
                ->helperText(fn (string $operation): ?string => $operation === 'edit' ? 'Leave empty to keep the current password.' : null)
                ->dehydrated(fn (?string $state): bool => filled($state))
                ->dehydrateStateUsing(fn (string $state): string => Hash::make($state)),
            Select::make('role')
                ->options(static::assignableRoles())
                ->required()
                ->native(false)
                // You cannot change your own role, so the last super-admin cannot lock everyone out.
                ->disabled(fn (?Model $record): bool => $record !== null && $record->is(Filament::auth()->user()))
                ->dehydrated(false)
                ->loadStateFromRelationshipsUsing(fn (Select $component, Model $record) => $component->state($record->getRoleNames()->first()))
                ->saveRelationshipsUsing(fn (Model $record, string $state) => $record->syncRoles([$state])),
        ];
    }

    /**
     * @return array<Column>
     */
    public static function tableColumns(): array
    {
        return [
            TextColumn::make('name')
                ->searchable()
                ->sortable(),
            TextColumn::make('email')
                ->searchable()
                ->sortable(),
            TextColumn::make('roles.name')
                ->label('Role')
                ->badge()
                ->formatStateUsing(fn (string $state): string => Role::tryFrom($state)?->label() ?? $state),
        ];
    }

    /**
     * The roles the current user may give to someone: only a super-admin can
     * create another super-admin.
     *
     * @return array<string, string>
     */
    public static function assignableRoles(): array
    {
        $isSuperAdmin = (bool) Filament::auth()->user()?->hasRole(Role::SuperAdmin->value);

        return collect(Role::cases())
            ->reject(fn (Role $role): bool => $role === Role::SuperAdmin && ! $isSuperAdmin)
            ->mapWithKeys(fn (Role $role): array => [$role->value => $role->label()])
            ->all();
    }

    /**
     * Nobody deletes their own account, a super-admin included.
     */
    public static function canDelete(Model $record): bool
    {
        return $record->isNot(Filament::auth()->user()) && parent::canDelete($record);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
