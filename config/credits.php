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

    /*
    |--------------------------------------------------------------------------
    | Accounts Table Name
    |--------------------------------------------------------------------------
    |
    | The name of the table holding one account row per creditable. This row
    | stores the current balance and is the row that gets locked during
    | concurrent credit operations. Default is 'credit_accounts'.
    |
    */
    'accounts_table_name' => 'credit_accounts',
];
