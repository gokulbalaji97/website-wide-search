# Website-Wide Search Application

This is a backend-focused application built with Laravel that provides a powerful, unified search system across multiple content types. It leverages Laravel Scout with MeiliSearch for fast, relevant, and typo-tolerant search results, with background processing handled by Redis queues.

---

### ## Key Features

* **Unified Search API:** A single `/api/search` endpoint to search across Blog Posts, Products, Pages, and FAQs.
* **High-Performance Indexing:** Uses **Laravel Scout** with the **MeiliSearch** engine for sub-second search performance.
* **Asynchronous Processing:** Leverages **Laravel Queues** (with Redis) to handle search indexing in the background, ensuring fast API response times for write operations.
* **Robust Admin Functionality:** Includes secure, admin-only endpoints for viewing search analytics and manually rebuilding the search index.(Just a simple Prototype, Not a fully blown Auth)
* **Containerized Environment:** The entire application stack is containerized using **Laravel Sail** (Docker), ensuring consistent and easy setup.
* **Clean API Design:** Follows RESTful principles with API Resources for consistent responses and middleware for route protection.

---

### ## Tech Stack

* **Backend:** PHP 8.4, Laravel 12
* **Database:** MySQL
* **Search Engine:** MeiliSearch
* **Queue & Cache:** Redis
* **Development Environment:** Docker (Laravel Sail)

---

### ## Setup and Installation

This project is fully containerized. You only need Docker installed on your machine.

1.  **Clone the Repository**
    ```bash
    git clone https://github.com/gokulbalaji97/website-wide-search.git
    cd website-wide-search
    ```

2.  **Create Environment File**
    Copy the example environment file.
    ```bash
    cp .env.example .env
    ```

3.  **Start the Docker Containers**
    This command will build and start the PHP, Nginx, MySQL, Redis, and MeiliSearch containers.
    ```bash
    ./vendor/bin/sail up -d
    ```

4.  **Install Dependencies**
    Install the PHP composer dependencies.
    ```bash
    sail composer install
    ```

5.  **Generate Application Key**
    ```bash
    sail artisan key:generate
    ```

6.  **Run Database Migrations and Seeders**
    This will create the database schema and populate it with sample data.
    ```bash
    sail artisan migrate --seed
    ```

7.  **Build the Search Index**
    This command will run the initial import of all seeded data into MeiliSearch.
    ```bash
    sail artisan search:rebuild-index
    ```

The application is now running and accessible at `http://localhost`.

---

### ## Running Tests

The project includes a full feature test suite written with **Pest**. The tests cover the API endpoints for success cases, validation, and admin-only security.

To run the entire test suite, execute the following command:

```bash
sail artisan test
```

---

### ## Indexing & Search Strategy

The architecture was designed for performance, accuracy, and long-term maintainability.

#### 1. Core Technology Choices
* **Laravel Scout:** Chosen as the core component to abstract the search implementation. This allows the application to be agnostic about the search engine, meaning we could swap MeiliSearch for another engine like Algolia in the future with minimal code changes.
* **MeiliSearch:** Selected as the search engine for its out-of-the-box speed and, most importantly, its built-in support for **typo-tolerant and prefix search** ("fuzzy matching"). This directly fulfills a key project requirement without needing complex manual configuration. Its seamless integration with Laravel Sail makes for a simple setup.

#### 2. The Asynchronous Indexing Workflow
The primary strategic decision was to make all indexing operations **asynchronous**.
* **Problem:** When a user creates or updates a record (e.g., a blog post), synchronously sending that data to MeiliSearch can add hundreds of milliseconds to the API response time, leading to a poor user experience.
* **Solution:** By setting `SCOUT_QUEUE=true` in the `.env` file, all indexing jobs are pushed to a **Redis queue**. The application immediately returns a success response to the user, and a separate queue worker process handles the communication with MeiliSearch in the background. This ensures the application remains highly responsive and scalable.

#### 3. Data Control and Relevance
* **Selective Indexing:** We don't just dump entire database records into the search index. The `toSearchableArray()` method in each model is used to precisely define **what** data should be searchable. This prevents irrelevant data (like internal flags or timestamps) from polluting search results and keeps the index lean and fast.
* **Unified Search Logic:** The `/api/search` endpoint queries each searchable model individually via Scout. The results, already ranked by relevance by MeiliSearch, are then merged. This logic is encapsulated in the `SearchController`, and the final output is standardized using a `SearchResultResource` to ensure a consistent API response regardless of the data source.

#### 4. Maintenance and Reliability
* **Manual Rebuild Command:** The `search:rebuild-index` Artisan command was created as a powerful administrative tool. It's essential for the initial data import and serves as a recovery mechanism to completely resynchronize the search index with the database if needed.
* **Scheduled Sync:** The daily scheduled task that runs this command acts as a self-healing safety net, ensuring long-term data consistency and correcting any potential discrepancies that might have occurred.

---

### ## Background Processes

#### Queue Worker

To process indexing jobs in the background, run the queue worker:
```bash
sail artisan queue:work
```

#### Task Scheduling

A nightly task is scheduled to rebuild the search index. In Laravel 12, this is defined in `routes/console.php`. On a production server, the following cron entry would be added to execute it:
```cron
* * * * * cd /var/www/html/ && php artisan schedule:run >> /dev/null 2>&1
```

---

### ## API Endpoints

The sample `ADMIN_SECRET_TOKEN` is set in the `.env.example` file. For admin routes, send this token in the `X-Admin-Token` header.

| Method | Endpoint                    | Description                                                  | Notes                                         |
| :----- | :---------------------------- | :----------------------------------------------------------- | :-------------------------------------------- |
| `GET`  | `/api/search`                 | Performs a unified search across all content.                | Query param: `q=...`                          |
| `GET`  | `/api/search/suggestions`     | Gets a lightweight list of search suggestions.               | Query param: `q=...`                          |
| `GET`  | `/api/search/logs`            | **Admin-only.** Returns the top 10 most frequent search terms. | Requires `X-Admin-Token` header.              |
| `POST` | `/api/search/rebuild-index`   | **Admin-only.** Triggers a full rebuild of the search index. | Requires `X-Admin-Token` header. Action is queued. |
