## Laravel Credits

A ledger-based credit system for Laravel applications. Use for virtual currencies, reward points, or any credit-based features requiring transaction tracking with full audit trails.

### Setup

Add the `HasCredits` trait to any Eloquent model:

@verbatim
<code-snippet name="Add HasCredits trait to a model" lang="php">
use Climactic\Credits\Traits\HasCredits;

class User extends Model
{
    use HasCredits;
}
</code-snippet>
@endverbatim

### Core Operations

- **Add credits**: `creditAdd(float $amount, ?string $description = null, array $metadata = []): Credit`
- **Deduct credits**: `creditDeduct(float $amount, ?string $description = null, array $metadata = []): Credit`
- **Check balance**: `creditBalance(): float`
- **Check sufficient**: `hasCredits(float $amount): bool`

@verbatim
<code-snippet name="Add and deduct credits with metadata" lang="php">
// Add credits with metadata
$transaction = $user->creditAdd(100.00, 'Welcome bonus', [
    'campaign' => 'onboarding',
    'tier' => 'premium',
    'tags' => ['bonus', 'welcome']
]);

// Check before deducting
if ($user->hasCredits(50.00)) {
    $user->creditDeduct(50.00, 'Feature unlock', ['feature' => 'premium_export']);
}

// Get current balance
$balance = $user->creditBalance();
</code-snippet>
@endverbatim

### Transfer Credits

Transfer credits between models atomically with database locking:

@verbatim
<code-snippet name="Transfer credits between users" lang="php">
$result = $sender->creditTransfer($recipient, 100.00, 'Payment', [
    'order_id' => 123,
    'type' => 'purchase'
]);
// Returns: ['sender_balance' => 900.00, 'recipient_balance' => 100.00]
</code-snippet>
@endverbatim

### Transaction History

@verbatim
<code-snippet name="Get transaction history" lang="php">
// Last 10 transactions (descending by default)
$history = $user->creditHistory();

// Last 20 transactions in ascending order
$history = $user->creditHistory(20, 'asc');

// Access the credits relationship directly
$allCredits = $user->credits()->get();
</code-snippet>
@endverbatim

### Historical Balance

Query balance at a specific point in time:

@verbatim
<code-snippet name="Get historical balance" lang="php">
// Balance 7 days ago
$pastBalance = $user->creditBalanceAt(now()->subDays(7));

// Balance at specific datetime
$balance = $user->creditBalanceAt(Carbon::parse('2024-01-15 10:00:00'));

// Balance at Unix timestamp
$balance = $user->creditBalanceAt(1705312800);
</code-snippet>
@endverbatim

### Metadata Querying

Query transactions by metadata using powerful scopes:

@verbatim
<code-snippet name="Basic metadata queries" lang="php">
// Filter by exact metadata value
$purchases = $user->credits()
    ->whereMetadata('source', 'purchase')
    ->get();

// With comparison operators (=, !=, >, <, >=, <=, like)
$highValue = $user->credits()
    ->whereMetadata('amount', '>', 100)
    ->get();

// Combine with OR conditions
$filtered = $user->credits()
    ->whereMetadata('source', 'purchase')
    ->orWhereMetadata('source', 'refund')
    ->get();
</code-snippet>
@endverbatim

@verbatim
<code-snippet name="Advanced metadata scopes" lang="php">
// Check if array contains value
$premium = $user->credits()
    ->whereMetadataContains('tags', 'premium')
    ->get();

// Check if key exists
$withOrderId = $user->credits()
    ->whereMetadataHas('order_id')
    ->get();

// Check if key is null or missing
$noOrder = $user->credits()
    ->whereMetadataNull('order_id')
    ->get();

// Check array length
$multipleTags = $user->credits()
    ->whereMetadataLength('tags', '>=', 2)
    ->get();
</code-snippet>
@endverbatim

@verbatim
<code-snippet name="Chained metadata queries" lang="php">
// Complex query with multiple conditions
$filtered = $user->credits()
    ->whereMetadata('source', 'purchase')
    ->whereMetadata('category', 'electronics')
    ->whereMetadataContains('tags', 'featured')
    ->whereMetadataHas('promo_code')
    ->where('amount', '>', 50)
    ->orderBy('created_at', 'desc')
    ->get();
</code-snippet>
@endverbatim

@verbatim
<code-snippet name="Convenience metadata methods" lang="php">
// Simple metadata query with limit
$purchases = $user->creditsByMetadata('source', '=', 'purchase', limit: 20);

// Multiple filter conditions
$filtered = $user->creditHistoryWithMetadata([
    ['key' => 'source', 'value' => 'purchase'],
    ['key' => 'amount', 'operator' => '>', 'value' => 100],
    ['key' => 'tags', 'value' => 'premium', 'method' => 'contains'],
    ['key' => 'order_id', 'method' => 'has'],
], limit: 25, order: 'desc');
</code-snippet>
@endverbatim

### Nested Metadata Keys

Use dot notation for nested JSON keys:

@verbatim
<code-snippet name="Query nested metadata" lang="php">
// Metadata: {'user': {'tier': 'gold', 'id': 123}}
$goldUsers = $user->credits()
    ->whereMetadata('user.tier', 'gold')
    ->get();
</code-snippet>
@endverbatim

### Events

All events dispatch after database commit for reliability:

- `CreditsAdded` - Properties: `creditable`, `credit`, `amount`, `newBalance`, `description`, `metadata`
- `CreditsDeducted` - Properties: `creditable`, `credit`, `amount`, `newBalance`, `description`, `metadata`
- `CreditsTransferred` - Properties: `sender`, `recipient`, `amount`, `senderCredit`, `recipientCredit`, `senderNewBalance`, `recipientNewBalance`, `description`, `metadata`

@verbatim
<code-snippet name="Listen to credit events" lang="php">
use Climactic\Credits\Events\CreditsAdded;
use Climactic\Credits\Events\CreditsDeducted;
use Climactic\Credits\Events\CreditsTransferred;

Event::listen(CreditsAdded::class, function (CreditsAdded $event) {
    Log::info("Added {$event->amount} credits", [
        'user_id' => $event->creditable->id,
        'new_balance' => $event->newBalance,
        'metadata' => $event->metadata,
    ]);
});

Event::listen(CreditsTransferred::class, function (CreditsTransferred $event) {
    Notification::send($event->recipient, new CreditsReceivedNotification($event->amount));
});
</code-snippet>
@endverbatim

### Exception Handling

@verbatim
<code-snippet name="Handle insufficient credits" lang="php">
use Climactic\Credits\Exceptions\InsufficientCreditsException;

try {
    $user->creditDeduct(1000.00, 'Large purchase');
} catch (InsufficientCreditsException $e) {
    // Handle insufficient balance
    return back()->with('error', 'Insufficient credits for this purchase.');
}
</code-snippet>
@endverbatim

### Configuration

Publish config: `php artisan vendor:publish --tag="credits-config"`

- `allow_negative_balance` (default: false) - Allow balances to go negative
- `table_name` (default: 'credits') - Ledger table name
- `accounts_table_name` (default: 'credit_accounts') - Per-creditable account table name

### Best Practices

1. Always use metadata for transaction context (order IDs, sources, categories)
2. Use `hasCredits()` before `creditDeduct()` for better UX
3. Leverage events for async operations (notifications, analytics)
4. Use `creditBalanceAt()` for historical reporting
5. Index frequently-queried metadata keys for performance (see Database Indexing section below)

### Database Indexing for Metadata

For high-volume metadata queries, add database indexes:

**MySQL/MariaDB** - Use virtual generated columns:

@verbatim
<code-snippet name="MySQL metadata indexing migration" lang="php">
Schema::table('credits', function (Blueprint $table) {
    // Add virtual column extracting JSON value
    $table->string('metadata_source')
        ->virtualAs("JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.source'))");

    // Index the virtual column
    $table->index('metadata_source');
});
</code-snippet>
@endverbatim

**PostgreSQL** - Use GIN indexes on JSONB:

@verbatim
<code-snippet name="PostgreSQL metadata indexing migration" lang="php">
Schema::table('credits', function (Blueprint $table) {
    // GIN index for general JSONB queries
    DB::statement('CREATE INDEX credits_metadata_gin ON credits USING GIN (metadata)');

    // Or expression index for specific key
    DB::statement("CREATE INDEX credits_metadata_source ON credits ((metadata->>'source'))");
});
</code-snippet>
@endverbatim

**SQLite** - Limited JSON indexing support. Consider MySQL/PostgreSQL for high-volume metadata queries.

### Concurrency

Balances live in the `credit_accounts` table, one row per creditable. Every mutation locks that row with `SELECT ... FOR UPDATE`, appends the ledger entry, and writes the balance back inside a single transaction, so concurrent writers on the same creditable take turns. `creditBalance()` reads the account row; `creditAccount()` exposes it as a relationship.

Do not write to the `credits` table directly — bypassing `creditAdd()`/`creditDeduct()` skips the lock and desynchronizes the account balance.

### Database Support

- **PostgreSQL**: full support with row-level locking
- **MySQL/MariaDB (InnoDB)**: full support with row-level locking
- **SQLite**: ignores row-level locking (not recommended for concurrent production use)
