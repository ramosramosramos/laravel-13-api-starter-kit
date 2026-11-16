<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use InvalidArgumentException;

final class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {

        //
        $this->configureResetPassword();
        $this->configureVerifyEmail();
        $this->configureDefaults();
        $this->tuneModelBehavior();
        $this->configureSortingToFilipino();
        $this->configureScramble();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(
            fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );

        if (! $this->app->environment('local')) {
            URL::forceScheme('https');
        }
    }

    protected function configureResetPassword(): void
    {
        ResetPassword::createUrlUsing(function (mixed $notifiable, string $token): string {

            if (! $notifiable instanceof User) {
                throw new InvalidArgumentException('Notifiable must be an instance of User.');
            }

            $email = $notifiable->getEmailForPasswordReset();

            return mb_rtrim((string) config()->string('app.frontend_url'), '/')
                .'/reset-password?'
                .http_build_query([
                    'token' => $token,
                    'email' => $email,
                ]);
        });
    }

    protected function configureVerifyEmail(): void
    {
        VerifyEmail::createUrlUsing(function (mixed $notifiable): string {
            if (! $notifiable instanceof User) {
                throw new InvalidArgumentException('Notifiable must be an instance of User.');
            }

            $key = $notifiable->getKey();
            if (! is_int($key) && ! is_string($key)) {
                throw new InvalidArgumentException('User key must be an integer or string.');
            }

            $email = $notifiable->getEmailForVerification();

            $hash = sha1($email);
            $backendUrl = URL::temporarySignedRoute(
                'verification.verify',
                now()->addMinutes(60),
                [
                    'id' => $key,
                    'hash' => $hash,
                ]
            );

            $query = parse_url($backendUrl, PHP_URL_QUERY);

            return mb_rtrim(config()->string('app.frontend_url'), '/')
                .'/verify-email/'
                .$key
                .'/'
                .$hash
                .'?'
                .$query;
        });
    }

    protected function configureSortingToFilipino(): void
    {
        Collection::macro('sortByFilipino', function (string|callable $key, bool $descending = false) {
            $normalize = function ($value): string {
                // @phpstan-ignore-next-line
                $str = mb_strtolower((string) $value, 'UTF-8');

                // Replace 'ng' and 'ñ' using placeholders so ASCII sorting places them in order:
                // n -> n\x01 (ñ) -> n\x02 (ng) -> o
                return str_replace(
                    ['ng', 'ñ'],
                    ["n\x02", "n\x01"],
                    $str
                );
            };

            $sorted = $this->sortBy(function ($item) use ($key, $normalize) {
                $value = is_callable($key) ? $key($item) : data_get($item, $key);

                return $normalize($value);
            }, SORT_STRING, $descending);

            return $sorted->values();
        });

    }

    protected function configureScramble(): void
    {
        // Scramble::configure()
        //     ->routes(function (Route $route) {
        //         return Str::startsWith($route->uri, 'api/');
        //     });

        // Gate::define('viewApiDocs', function (User $user) {
        //     return in_array($user->email, ['admin@app.com']);
        // });
    }

    /**
     * Fine-tune Eloquent model behavior.
     */
    private function tuneModelBehavior(): void
    {
        Model::shouldBeStrict();
        Model::unguard();
        Model::automaticallyEagerLoadRelationships();
    }
}
