<?php

namespace Climactic\Credits\Tests\TestModels;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Climactic\Credits\Traits\HasCredits;

class User extends Authenticatable
{
    use HasCredits;

    protected $guarded = [];

    protected $table = 'users';
}
