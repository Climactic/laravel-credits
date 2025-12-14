<?php

use Climactic\Credits\Tests\TestModels\User;

beforeEach(function () {
    /** @var \Climactic\Credits\Tests\TestCase $this */
    $this->user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
    ]);

    // Create test transactions with various metadata
    $this->user->creditAdd(100.00, 'Purchase 1', [
        'source' => 'purchase',
        'category' => 'electronics',
        'user_id' => 123,
        'tags' => ['premium', 'featured'],
        'order_value' => 100,
    ]);

    $this->user->creditAdd(50.00, 'Refund 1', [
        'source' => 'refund',
        'category' => 'electronics',
        'user_id' => 123,
        'order_value' => 50,
    ]);

    $this->user->creditAdd(75.00, 'Purchase 2', [
        'source' => 'purchase',
        'category' => 'books',
        'user_id' => 456,
        'tags' => ['premium'],
        'order_value' => 75,
    ]);

    $this->user->creditAdd(200.00, 'Purchase 3', [
        'source' => 'purchase',
        'category' => 'electronics',
        'user_id' => 123,
        'tags' => ['featured', 'sale'],
        'order_value' => 200,
    ]);

    $this->user->creditAdd(25.00, 'Bonus', [
        'source' => 'bonus',
        'category' => 'reward',
        'order_value' => 25,
    ]);
});

it('can query credits by simple metadata value', function () {
    $results = $this->user->credits()->whereMetadata('source', 'purchase')->get();

    expect($results)->toHaveCount(3)
        ->and($results->every(fn ($credit) => $credit->metadata['source'] === 'purchase'))->toBeTrue();
});

it('can query credits by metadata with operator', function () {
    $results = $this->user->credits()->whereMetadata('order_value', '>', 50)->get();

    expect($results)->toHaveCount(3);
});

it('can query credits by nested metadata', function () {
    $results = $this->user->credits()->whereMetadata('user_id', 123)->get();

    expect($results)->toHaveCount(3);
});

it('can query credits where metadata contains value', function () {
    $results = $this->user->credits()->whereMetadataContains('tags', 'premium')->get();

    expect($results)->toHaveCount(2);
});

it('can query credits where metadata key exists', function () {
    $results = $this->user->credits()->whereMetadataHas('tags')->get();

    expect($results)->toHaveCount(3);
});

it('can query credits where metadata key is null', function () {
    $results = $this->user->credits()->whereMetadataNull('tags')->get();

    expect($results)->toHaveCount(2);
});

it('can query credits by metadata length', function () {
    $results = $this->user->credits()->whereMetadataLength('tags', '>', 1)->get();

    expect($results)->toHaveCount(2);
});

it('can chain multiple metadata conditions', function () {
    $results = $this->user->credits()
        ->whereMetadata('source', 'purchase')
        ->whereMetadata('category', 'electronics')
        ->get();

    expect($results)->toHaveCount(2);
});

it('can get credits by metadata using convenience method', function () {
    $results = $this->user->creditsByMetadata('source', 'purchase', limit: 10);

    expect($results)->toHaveCount(3)
        ->and($results->first()->description)->toBe('Purchase 3'); // Most recent first
});

it('can get credits by metadata with operator using convenience method', function () {
    $results = $this->user->creditsByMetadata('order_value', '>=', 100, limit: 10);

    expect($results)->toHaveCount(2);
});

it('can get credits with multiple metadata filters', function () {
    $results = $this->user->creditHistoryWithMetadata([
        ['key' => 'source', 'value' => 'purchase'],
        ['key' => 'user_id', 'value' => 123],
    ], limit: 10);

    expect($results)->toHaveCount(2);
});

it('can get credits with complex metadata filters', function () {
    $results = $this->user->creditHistoryWithMetadata([
        ['key' => 'source', 'value' => 'purchase'],
        ['key' => 'tags', 'value' => 'premium', 'method' => 'contains'],
        ['key' => 'order_value', 'operator' => '>', 'value' => 50],
    ], limit: 10);

    expect($results)->toHaveCount(2);
});

it('can get credits using has filter method', function () {
    $results = $this->user->creditHistoryWithMetadata([
        ['key' => 'tags', 'method' => 'has'],
    ], limit: 10);

    expect($results)->toHaveCount(3);
});

it('can get credits using null filter method', function () {
    $results = $this->user->creditHistoryWithMetadata([
        ['key' => 'tags', 'method' => 'null'],
    ], limit: 10);

    expect($results)->toHaveCount(2);
});

it('can get credits using length filter method', function () {
    $results = $this->user->creditHistoryWithMetadata([
        ['key' => 'tags', 'operator' => '=', 'value' => 2, 'method' => 'length'],
    ], limit: 10);

    expect($results)->toHaveCount(2);
});

it('respects limit parameter in metadata queries', function () {
    $results = $this->user->creditsByMetadata('source', 'purchase', limit: 2);

    expect($results)->toHaveCount(2);
});

it('respects order parameter in metadata queries', function () {
    $results = $this->user->creditsByMetadata('source', 'purchase', limit: 10, order: 'asc');

    expect($results->first()->description)->toBe('Purchase 1');
});

it('handles metadata queries with no results', function () {
    $results = $this->user->credits()->whereMetadata('source', 'nonexistent')->get();

    expect($results)->toHaveCount(0);
});

it('handles metadata queries with deeply nested keys using dot notation', function () {
    // Add a transaction with deeply nested metadata
    $this->user->creditAdd(10.00, 'Deep nested', [
        'data' => [
            'level1' => [
                'level2' => [
                    'value' => 'found',
                ],
            ],
        ],
    ]);

    // Use dot notation for nested JSON paths
    $results = $this->user->credits()
        ->where('metadata->data->level1->level2->value', 'found')
        ->get();

    expect($results)->toHaveCount(1);
});

it('handles metadata queries with simple nested keys', function () {
    // For simpler nesting, we can still use the whereMetadata scope
    $this->user->creditAdd(10.00, 'Simple nested', [
        'user' => [
            'id' => 123,
        ],
    ]);

    // This works because Laravel converts 'user.id' to 'user->id' in JSON paths
    $results = $this->user->credits()->whereMetadata('user.id', 123)->get();

    expect($results)->toHaveCount(1);
});

it('handles metadata queries combined with regular queries', function () {
    $results = $this->user->credits()
        ->where('type', 'credit')
        ->whereMetadata('source', 'purchase')
        ->where('amount', '>', 50)
        ->get();

    expect($results)->toHaveCount(3);
});

it('rejects empty metadata keys', function () {
    expect(fn () => $this->user->credits()->whereMetadata('', 'value')->get())
        ->toThrow(\InvalidArgumentException::class, 'Metadata key cannot be empty');
});

it('rejects metadata keys with arrow operator', function () {
    expect(fn () => $this->user->credits()->whereMetadata('data->key', 'value')->get())
        ->toThrow(\InvalidArgumentException::class, 'cannot contain "->"');
});

it('rejects metadata keys with quotes', function () {
    expect(fn () => $this->user->credits()->whereMetadata('data"key', 'value')->get())
        ->toThrow(\InvalidArgumentException::class, 'cannot contain quotes');

    expect(fn () => $this->user->credits()->whereMetadata("data'key", 'value')->get())
        ->toThrow(\InvalidArgumentException::class, 'cannot contain quotes');
});

it('trims whitespace from metadata keys', function () {
    $this->user->creditAdd(10.00, 'Test', ['source' => 'test']);

    // Should work even with whitespace
    $results = $this->user->credits()->whereMetadata('  source  ', 'test')->get();

    expect($results)->toHaveCount(1);
});

it('can chain whereMetadata with orWhereMetadata', function () {
    // From beforeEach: 3 purchases, 1 refund, 1 bonus
    $results = $this->user->credits()
        ->whereMetadata('source', 'purchase')
        ->orWhereMetadata('source', 'refund')
        ->get();

    expect($results)->toHaveCount(4); // 3 purchases + 1 refund
});

it('can chain whereMetadataContains with orWhereMetadataContains', function () {
    // From beforeEach: Purchase 1 has ['premium', 'featured'], Purchase 2 has ['premium'], Purchase 3 has ['featured', 'sale']
    $results = $this->user->credits()
        ->whereMetadataContains('tags', 'premium')
        ->orWhereMetadataContains('tags', 'sale')
        ->get();

    expect($results)->toHaveCount(3); // Purchase 1 (premium), Purchase 2 (premium), Purchase 3 (sale)
});

it('can chain whereMetadataHas with orWhereMetadataHas', function () {
    // Add a credit with only 'special' key (no tags, no user_id)
    $this->user->creditAdd(5.00, 'Special', ['special' => true]);

    // From beforeEach: 3 have tags, 4 have user_id
    $results = $this->user->credits()
        ->whereMetadataHas('tags')
        ->orWhereMetadataHas('special')
        ->get();

    expect($results)->toHaveCount(4); // 3 with tags + 1 with special
});

it('can chain whereMetadataNull with orWhereMetadataNull', function () {
    // From beforeEach: Refund 1 and Bonus don't have tags, Bonus doesn't have user_id
    $results = $this->user->credits()
        ->whereMetadataNull('tags')
        ->orWhereMetadataNull('user_id')
        ->get();

    // Refund 1 (no tags), Bonus (no tags, no user_id) = 2 unique records
    expect($results)->toHaveCount(2);
});

it('can chain whereMetadataLength with orWhereMetadataLength', function () {
    // From beforeEach: Purchase 1 has 2 tags, Purchase 2 has 1 tag, Purchase 3 has 2 tags
    $results = $this->user->credits()
        ->whereMetadataLength('tags', 2)
        ->orWhereMetadataLength('tags', 1)
        ->get();

    expect($results)->toHaveCount(3); // All 3 with tags
});

it('can chain mixed where and orWhere metadata conditions', function () {
    // Complex query: (source = 'bonus') OR (source = 'purchase' AND category = 'books')
    $results = $this->user->credits()
        ->where(function ($query) {
            $query->whereMetadata('source', 'bonus');
        })
        ->orWhere(function ($query) {
            $query->whereMetadata('source', 'purchase')
                ->whereMetadata('category', 'books');
        })
        ->get();

    expect($results)->toHaveCount(2); // 1 bonus + 1 purchase in books category
});

it('can chain multiple orWhereMetadata conditions', function () {
    // From beforeEach: 3 purchases (source=purchase), 1 refund (source=refund), 1 bonus (source=bonus)
    $results = $this->user->credits()
        ->whereMetadata('source', 'purchase')
        ->orWhereMetadata('source', 'refund')
        ->orWhereMetadata('source', 'bonus')
        ->get();

    expect($results)->toHaveCount(5); // All 5 credits
});

it('can chain multiple orWhereMetadataContains conditions', function () {
    // From beforeEach: Purchase 1 ['premium', 'featured'], Purchase 2 ['premium'], Purchase 3 ['featured', 'sale']
    $results = $this->user->credits()
        ->whereMetadataContains('tags', 'premium')
        ->orWhereMetadataContains('tags', 'featured')
        ->orWhereMetadataContains('tags', 'sale')
        ->get();

    expect($results)->toHaveCount(3); // All 3 with tags (each matches at least one condition)
});

it('can chain multiple different orWhere metadata methods', function () {
    // Mix different orWhere methods: source='bonus' OR has tags OR order_value > 150
    $results = $this->user->credits()
        ->whereMetadata('source', 'bonus')
        ->orWhereMetadataHas('tags')
        ->orWhereMetadata('order_value', '>', 150)
        ->get();

    // bonus (1) + has tags (3: Purchase 1, 2, 3) + order_value > 150 (1: Purchase 3 with 200)
    // Purchase 3 matches both tags and order_value, so unique count = 4
    expect($results)->toHaveCount(4);
});
