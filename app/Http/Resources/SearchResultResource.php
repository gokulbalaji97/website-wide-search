<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class SearchResultResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $type = strtolower(class_basename($this->resource));
        $title = $this->getTitle();

        return [
            'type' => $type,
            'title' => $title,
            'snippet' => $this->getSnippet($title),
            'link' => $this->getLink($type),
        ];
    }

    /**
     * Get the title for the search result.
     */
    protected function getTitle(): string
    {
        // Return 'name' for Product, 'question' for Faq, and 'title' for others
        if ($this->resource instanceof \App\Models\Product) {
            return $this->name;
        }
        if ($this->resource instanceof \App\Models\Faq) {
            return $this->question;
        }
        return $this->title;
    }

    /**
     * Get a snippet of the content.
     */
    protected function getSnippet(string $title): string
    {
        $content = '';
        if ($this->resource instanceof \App\Models\BlogPost) {
            $content = $this->body;
        } elseif ($this->resource instanceof \App\Models\Product) {
            $content = $this->description;
        } elseif ($this->resource instanceof \App\Models\Page) {
            $content = $this->content;
        } elseif ($this->resource instanceof \App\Models\Faq) {
            $content = $this->answer;
        }
        // Remove the title from content to avoid repetition and create a snippet
        return Str::limit(str_replace($title, '', $content), 150);
    }

    /**
     * Get the URL for the resource.
     */
    protected function getLink(string $type): string
    {
        return url("/api/{$type}s/{$this->id}");
    }
}
