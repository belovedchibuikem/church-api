<?php

namespace App\Console\Commands;

use App\Identity\UserAccountStatus;
use App\Models\User;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Requests\Api\V1\Auth\BrowserLoginRequest;
use App\Support\Identity\AuthenticateBrowserUserAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

#[Signature('auth:diagnose {email : User email} {--password=DemoPass!2026 : Password to test} {--fix : Re-hash password if verification fails} {--http : Also exercise POST /api/v1/auth/login}')]
#[Description('Diagnose browser login failures for a user (local only)')]
class DiagnoseLoginCommand extends Command
{
    public function handle(AuthenticateBrowserUserAction $authenticate): int
    {
        if (! app()->environment('local', 'testing')) {
            $this->components->error('This command is only available in local and testing environments.');

            return self::FAILURE;
        }

        $email = strtolower(trim((string) $this->argument('email')));
        $password = (string) $this->option('password');
        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

        if ($user === null) {
            $this->components->error("No user found for {$email}.");

            return self::FAILURE;
        }

        $hash = $user->getAuthPassword();
        $hashOk = is_string($hash) && $hash !== '' && Hash::check($password, $hash);

        $this->line("Email: {$user->email}");
        $this->line('Account status: '.($user->account_status?->value ?? 'null'));
        $this->line('Suspended at: '.($user->suspended_at?->toIso8601String() ?? 'null'));
        $this->line('Email verified: '.($user->email_verified_at ? 'yes' : 'no'));
        $this->line('Password hash present: '.(is_string($hash) && $hash !== '' ? 'yes' : 'no'));
        $this->line('Password verify: '.($hashOk ? 'PASS' : 'FAIL'));

        if (! $hashOk && $this->option('fix')) {
            $user->forceFill(['password' => $password])->save();
            $user->refresh();
            $hashOk = Hash::check($password, (string) $user->getAuthPassword());
            $this->components->info('Password re-saved. Verify: '.($hashOk ? 'PASS' : 'FAIL'));
        }

        try {
            $authenticate->handle($email, $password, false);
            $this->components->info('AuthenticateBrowserUserAction: PASS');
        } catch (ValidationException $exception) {
            $this->components->error('AuthenticateBrowserUserAction: FAIL');
            foreach ($exception->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $this->line("  {$field}: {$message}");
                }
            }

            return self::FAILURE;
        }

        if ($this->option('http')) {
            $request = Request::create(
                '/api/v1/auth/login',
                'POST',
                server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
                content: json_encode(['email' => $email, 'password' => $password], JSON_THROW_ON_ERROR),
            );

            $formRequest = BrowserLoginRequest::createFrom($request);
            $formRequest->setContainer(app())->setRedirector(app('redirect'));
            $formRequest->validateResolved();

            $validatedEmail = (string) $formRequest->validated('email');
            $validatedPassword = (string) $formRequest->validated('password');
            $this->line('Validated email: '.$validatedEmail);
            $this->line('Validated password length: '.strlen($validatedPassword));

            try {
                app(AuthenticateBrowserUserAction::class)->handle($validatedEmail, $validatedPassword, false);
                $this->components->info('Validated payload via AuthenticateBrowserUserAction: PASS');
            } catch (ValidationException $exception) {
                $this->components->error('Validated payload via AuthenticateBrowserUserAction: FAIL');
                foreach ($exception->errors() as $field => $messages) {
                    foreach ($messages as $message) {
                        $this->line("  {$field}: {$message}");
                    }
                }
            }

            try {
                $response = app(LoginController::class)($formRequest, app(AuthenticateBrowserUserAction::class), app(\App\Support\Identity\StartBrowserSessionAction::class));
                $this->line('LoginController status: '.$response->getStatusCode());
            } catch (ValidationException $exception) {
                $this->components->error('LoginController validation FAIL');
                foreach ($exception->errors() as $field => $messages) {
                    foreach ($messages as $message) {
                        $this->line("  {$field}: {$message}");
                    }
                }
            } catch (\Throwable $exception) {
                $this->components->error('LoginController exception: '.$exception->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
