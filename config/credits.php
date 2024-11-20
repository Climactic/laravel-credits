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
    | Decimal Precision
    |--------------------------------------------------------------------------
    |
    | The number of decimal places to use for credit amounts.
    | This affects database storage and calculations.
    | Note: This needs to be configured before running migrations.
    |
    */
    'decimal_precision' => 2,

    /*
    |--------------------------------------------------------------------------
    | Default Transaction Types
    |--------------------------------------------------------------------------
    |
    | The allowed transaction types. You can add custom types,
    | but 'credit' and 'debit' are required for core functionality.
    |
    */
    'transaction_types' => [
        'credit',
        'debit',
        // Add custom types here
    ],

    /*
    |--------------------------------------------------------------------------
    | Transaction Description
    |--------------------------------------------------------------------------
    |
    | Configure whether transaction descriptions are required
    | and the maximum length allowed.
    |
    */
    'description' => [
        'required' => false,
        'max_length' => 255,
    ],

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
