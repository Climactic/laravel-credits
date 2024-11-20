<?php

namespace Climactic\Credits\Tests\TestModels;

use Climactic\Credits\Traits\HasCredits;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasCredits;

    protected $guarded = [];

    protected $table = 'users';
}
