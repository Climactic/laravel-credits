<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Allow Negative Balance
    |--------------------------------------------------------------------------
    |
    | When set to true, accounts can have negative balances. When false,
    | attempting to deduct more credits than available will throw an
    | InsufficientCreditsException.
    |
    */
    'allow_negative_balance' => false,

    /*
    |--------------------------------------------------------------------------
    | Database Table Name
    |--------------------------------------------------------------------------
    |
    | The name of the table used to store credit transactions.
    | Default is 'credits'.
    |
    */
    'table_name' => 'credits',
];
