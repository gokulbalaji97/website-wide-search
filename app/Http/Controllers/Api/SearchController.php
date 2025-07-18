<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\BlogPost;
use App\Models\Product;
use App\Models\Page;
use App\Models\Faq;
use App\Http\Resources\SearchResultResource;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;

class SearchController extends Controller
{
    public function search(Request $request)
    {
        $request->validate(['q' => 'required|string|min:2']);
        $query = $request->input('q');

        // Search each model
        $blogPosts = BlogPost::search($query)->get();
        $products = Product::search($query)->get();
        $pages = Page::search($query)->get();
        $faqs = Faq::search($query)->get();

        // Merge the results into a single collection
        $results = new Collection([...$blogPosts, ...$products, ...$pages, ...$faqs]);

        if ($results->isEmpty()) {
            return response()->json([
                'message' => 'No results found for your query: "' . e($query) . '"',
                'data' => [],
            ],  200);
        }

        // Sort by relevance (MeiliSearch does this by default)
        $results = $results->sortByDesc('created_at');

        // Manually paginate the merged collection
        $page = $request->input('page', 1);
        $perPage = 10;
        $paginatedResults = new LengthAwarePaginator(
            $results->forPage($page, $perPage)->values(),
            $results->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        // Return the paginated results through our API resource
        return SearchResultResource::collection($paginatedResults);
    }

    public function topSearches()
    {
        $topTerms = DB::table('search_logs')
            ->select('query', DB::raw('count(*) as search_count'))
            ->groupBy('query')
            ->orderByDesc('search_count')
            ->limit(10)
            ->get();

        return response()->json($topTerms);
    }

    public function suggestions(Request $request)
    {
        $request->validate(['q' => 'required|string|min:2']);
        $query = $request->input('q');

        // We search each model, but only take a few results from each
        $blogPosts = BlogPost::search($query)->take(3)->get()->pluck('title');
        $products = Product::search($query)->take(3)->get()->pluck('name');
        $faqs = Faq::search($query)->take(2)->get()->pluck('question');
        $pages = Page::search($query)->take(2)->get()->pluck('title');

        // Merge the suggestions into a single flat array
        $suggestions = $blogPosts->concat($products)->concat($faqs)->concat($pages)->unique()->values();

        return response()->json($suggestions);
    }

    public function rebuildIndex()
    {
        // We'll dispatch this to the queue so the API request returns immediately
        // while the long-running task happens in the background.
        Artisan::queue('app:rebuild-search-index');

        return response()->json([
            'message' => 'The search index rebuild process has been started in the background.'
        ]);
    }
}
