<?php

// tests/Feature/SearchApiTest.php

use App\Models\BlogPost;
use App\Models\Product;
use App\Models\SearchLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

// This trait resets the database after each test, so tests don't interfere with each other.
uses(RefreshDatabase::class);

// A helper function to get the admin header
function getAdminHeader(): array
{
    return ['X-Admin-Token' => config('app.admin_secret_token', 'a-very-secret-admin-token')];
}

//--- GROUP: Main Search Endpoint ---

test('the main search endpoint returns a successful response for a valid query', function () {
    // Create a product that we can search for
    Product::factory()->create(['name' => 'Testable Super Widget']);

    // Import it into Scout
    Artisan::call('scout:import', ['model' => Product::class]);

    // Give MeiliSearch a moment to index
    sleep(1);

    $response = $this->getJson('/api/search?q=Testable');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => ['type', 'title', 'snippet', 'link']
            ],
            'links',
            'meta'
        ])
        ->assertJsonFragment(['title' => 'Testable Super Widget']);
});

test('the main search endpoint returns a helpful message for no results', function () {
    $response = $this->getJson('/api/search?q=nonexistentquery');

    $response->assertStatus(200)
        ->assertJson([
            'message' => 'No results found for your query: "nonexistentquery"',
            'data' => [],
        ]);
});

test('the main search endpoint logs the search query', function () {
    // Ensure the logs table is empty before the test
    $this->assertDatabaseCount('search_logs', 0);

    $this->getJson('/api/search?q=loggingtest');

    // Assert that a log entry was created
    $this->assertDatabaseHas('search_logs', [
        'query' => 'loggingtest'
    ]);
    $this->assertDatabaseCount('search_logs', 1);
});


//--- GROUP: Validation Tests ---

// Using a dataset to test multiple invalid inputs for the search endpoint
test('the main search endpoint fails validation for invalid queries', function (string $invalidQuery) {
    $response = $this->getJson("/api/search?q={$invalidQuery}");
    $response->assertStatus(422); // 422 Unprocessable Entity
})->with([
    'empty query' => '',
    'short query' => 'a',
]);


//--- GROUP: Suggestions Endpoint ---

test('the suggestions endpoint returns a successful response', function () {
    Product::factory()->create(['name' => 'Suggested Product']);
    BlogPost::factory()->create(['title' => 'Suggested Post']);
    Artisan::call('scout:import', ['model' => Product::class]);
    Artisan::call('scout:import', ['model' => BlogPost::class]);
    sleep(1);

    $response = $this->getJson('/api/search/suggestions?q=Suggested');

    $response->assertStatus(200);

    $suggestions = $response->json();
    $this->assertContains('Suggested Product', $suggestions);
    $this->assertContains('Suggested Post', $suggestions);
});


//--- GROUP: Admin Endpoints ---

test('admin endpoints are protected from unauthorized access', function (string $method, string $endpoint) {
    $response = $this->json($method, $endpoint);
    $response->assertStatus(403); // Forbidden
})->with([
    ['GET', '/api/search/logs'],
    ['POST', '/api/search/rebuild-index'],
]);

test('the search logs endpoint returns top search terms for an admin', function () {
    // Create some log data
    SearchLog::factory()->create(['query' => 'popular']);
    SearchLog::factory()->create(['query' => 'popular']);
    SearchLog::factory()->create(['query' => 'unpopular']);

    $response = $this->getJson('/api/search/logs', getAdminHeader());

    $response->assertStatus(200)
        ->assertJsonFragment([
            'query' => 'popular',
            'search_count' => 2
        ]);
});

test('the rebuild index endpoint queues the command for an admin', function () {
    // Mock the Artisan facade to ensure the command is queued
    Artisan::shouldReceive('queue')
        ->once()
        ->with('app:rebuild-search-index');

    $response = $this->postJson('/api/search/rebuild-index', [], getAdminHeader());

    $response->assertStatus(200)
        ->assertJson([
            'message' => 'The search index rebuild process has been started in the background.'
        ]);
});
