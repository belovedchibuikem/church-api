<?php

namespace App\Http\Controllers\Api\V1\User\Concerns;

use App\Models\Person;
use App\Models\User;
use Illuminate\Http\Request;

trait ResolvesAuthenticatedPerson
{
    private function person(Request $request): Person
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $person = $user->person;
        abort_unless($person instanceof Person, 409, 'The account is not linked to a person profile.');

        return $person;
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
