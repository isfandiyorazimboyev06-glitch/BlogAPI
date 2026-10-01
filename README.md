# Blog API - Clean Architecture & RabbitMQ Event-Driven System

A high-performance, enterprise-grade Blog REST API built with **Laravel 12**, **Clean Architecture**, **Docker**, **PostgreSQL**, and **RabbitMQ** event-driven queue processing with automated Excel export generation.

---

## 🌟 Key Architecture & Highlights

- **Clean Architecture & Repository Pattern**:
  - `Route` ➔ `PostController` ➔ `FormRequest` ➔ `PostRepositoryInterface` ➔ `PostRepository` ➔ `PostResource`.
  - Decoupled business logic from controllers for testability and maintainability.
- **RabbitMQ Event-Driven Message Broker**:
  - AMQP connection configured with topic exchange (`blog.events`), routing key (`post.published`), and queue (`blog_posts_queue`).
  - **Dead Letter Queue (DLQ)** (`blog.dlq` via `blog.dlx`) for handling poisoned messages with auto-retry backoff (`[5, 15]` seconds).
- **High-Performance Background Jobs**:
  - `ProcessPostPublishingJob` processes reading times and metadata asynchronously.
  - Slices batches in PHP memory (`array_chunk`) to eliminate SQL `LIMIT/OFFSET` performance degradation.
  - Isolated try/catch per post so one corrupt row never crashes the entire batch.
- **Master Excel Spreadsheet Reporting**:
  - Centralized export (`storage/app/private/exports/published_posts.xlsx`) automatically updated using `maatwebsite/excel`.
  - Preserves all previously published posts while adding newly published ones.
- **Automated Scheduling Engine**:
  - Custom Artisan command `php artisan blog:publish-scheduled` scheduled with `->everyMinute()->withoutOverlapping()`.
  - Performs atomic status updates and queue dispatches in a single database transaction.
- **Database Optimization**:
  - Composite B-tree indexing on `(status, published_at)` ensuring instant queries at scale.
- **Comprehensive Automated Test Suite**:
  - 14 Unit and Feature tests with 84 assertions running against in-memory SQLite in under 200ms.

---

## 🚀 Tech Stack

- **Framework:** Laravel 12 (PHP 8.5)
- **Database:** PostgreSQL 15 (Docker)
- **Message Broker:** RabbitMQ 3 Management (Docker)
- **Queue Driver:** `vladimir-yuldashev/laravel-queue-rabbitmq`
- **Spreadsheet Engine:** `maatwebsite/excel` (PhpSpreadsheet)
- **Testing:** PHPUnit with `RefreshDatabase`, `Queue::fake()`, and `Excel::fake()`

---

## 🛠️ Getting Started

### 1. Prerequisites
- Docker & Docker Compose
- PHP 8.2+ and Composer

### 2. Clone & Setup
```bash
git clone https://github.com/isfandiyorazimboyev06-glitch/BlogAPI.git
cd BlogAPI

cp .env.example .env
composer install
php artisan key:generate
```

### 3. Start Docker Containers (PostgreSQL & RabbitMQ)
```bash
docker compose up -d
```
- **PostgreSQL:** `localhost:5432`
- **RabbitMQ AMQP:** `localhost:5672`
- **RabbitMQ Management Dashboard:** [http://localhost:15672](http://localhost:15672) (User: `guest` / Pass: `guest`)

### 4. Run Migrations
```bash
php artisan migrate
```

---

## 📡 API Endpoints

| Method | Endpoint | Description |
| :--- | :--- | :--- |
| `GET` | `/api/posts` | Paginated list of blog posts |
| `GET` | `/api/posts/{id}` | Single post details |
| `POST` | `/api/posts` | Create post (`draft`, `scheduled`, `published`) |
| `PUT` | `/api/posts/{id}` | Update existing post |
| `DELETE` | `/api/posts/{id}` | Delete post by ID |

---

## ⚙️ Running Background Workers & Schedulers

### Consume RabbitMQ Queue
```bash
php artisan queue:work
# Or using the RabbitMQ consumer:
php artisan rabbitmq:consume
```

### Publish Scheduled Posts Manually
```bash
php artisan blog:publish-scheduled
```

### Run the Automated Scheduler Daemon
```bash
php artisan schedule:work
```

---

## 🧪 Running Tests

Execute the complete automated test suite:
```bash
php artisan test
```

Or view descriptive test output:
```bash
./vendor/bin/phpunit --testdox
```
