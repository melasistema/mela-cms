<?php

use Melasistema\MelaCms\Install\UserModelPatcher;

// app/Models/User.php as shipped by `laravel new` (Laravel 13).
const STOCK_USER_MODEL = <<<'PHP'
<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}

PHP;

function pintAccepts(string $source): bool
{
    $directory = sys_get_temp_dir().'/mela-cms-pint-'.bin2hex(random_bytes(6));
    mkdir($directory);
    file_put_contents($directory.'/User.php', $source);

    exec(escapeshellarg(__DIR__.'/../../vendor/bin/pint').' --test --preset laravel '.escapeshellarg($directory).' 2>&1', $output, $exitCode);

    unlink($directory.'/User.php');
    rmdir($directory);

    return $exitCode === 0;
}

it('patches the stock Laravel user model', function () {
    $patched = (new UserModelPatcher)->patch(STOCK_USER_MODEL);

    expect($patched)
        ->toContain("use Database\\Factories\\UserFactory;\nuse Filament\\Models\\Contracts\\FilamentUser;\nuse Illuminate\\")
        ->toContain("use Illuminate\\Notifications\\Notifiable;\nuse Melasistema\\MelaCms\\Concerns\\HasCmsAccess;\n")
        ->toContain("class User extends Authenticatable implements FilamentUser\n{")
        ->toContain("{\n    use HasCmsAccess;\n\n    /** @use HasFactory<UserFactory> */\n    use HasFactory, Notifiable;\n");
});

it('leaves the stock model in a state Pint accepts', function () {
    expect(pintAccepts(STOCK_USER_MODEL))->toBeTrue()
        ->and(pintAccepts((new UserModelPatcher)->patch(STOCK_USER_MODEL)))->toBeTrue();
});

it('does nothing to a model that is already patched', function () {
    $patcher = new UserModelPatcher;
    $patched = $patcher->patch(STOCK_USER_MODEL);

    expect($patcher->patch($patched))->toBe($patched);
});

it('appends to an existing implements list', function () {
    $source = str_replace('extends Authenticatable', 'extends Authenticatable implements MustVerifyEmail', STOCK_USER_MODEL);

    expect((new UserModelPatcher)->patch($source))
        ->toContain('class User extends Authenticatable implements MustVerifyEmail, FilamentUser');
});

it('patches a model with no imports and no traits', function () {
    $source = "<?php\n\nnamespace App\\Models;\n\nclass User extends \\Illuminate\\Foundation\\Auth\\User\n{\n}\n";
    $patched = (new UserModelPatcher)->patch($source);

    expect($patched)->toBe(<<<'PHP'
        <?php

        namespace App\Models;

        use Filament\Models\Contracts\FilamentUser;
        use Melasistema\MelaCms\Concerns\HasCmsAccess;

        class User extends \Illuminate\Foundation\Auth\User implements FilamentUser
        {
            use HasCmsAccess;
        }

        PHP);
});

it('gives up on source without a class', function () {
    expect((new UserModelPatcher)->patch("<?php\n\nreturn [];\n"))->toBeNull();
});
