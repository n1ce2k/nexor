<?php

namespace Nexor\Cms\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Nexor\Cms\Support\Nexor;

class SetPasswordCommand extends Command
{
    protected $signature = 'nexor:password {email : E-mail учётной записи}';

    protected $description = 'Задать пароль пользователя админки';

    /**
     * The password is only ever read from a hidden prompt, so it never lands in
     * shell history, process listings or logs.
     */
    public function handle(): int
    {
        $email = $this->argument('email');
        $user = Nexor::newUser()->newQuery()->where('email', $email)->first();

        if (! $user) {
            $this->components->error("Пользователь {$email} не найден.");

            return self::FAILURE;
        }

        $password = $this->secret('Новый пароль');
        $confirmation = $this->secret('Повторите пароль');

        if ($password !== $confirmation) {
            $this->components->error('Пароли не совпадают.');

            return self::FAILURE;
        }

        $validator = Validator::make(['password' => $password], [
            'password' => ['required', Password::default()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->components->error($message);
            }

            return self::FAILURE;
        }

        $user->forceFill(['password' => Hash::make($password)])->save();

        $this->components->info("Пароль для {$email} изменён.");

        return self::SUCCESS;
    }
}
