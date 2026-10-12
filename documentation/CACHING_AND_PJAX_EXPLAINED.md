# System Caching & PJAX Navigation Explained

This document outlines how the caching architecture of the DTS Prototype interacts with the PJAX (Navigation Override) system to deliver instant page transitions and minimize server load.

The system relies on three independent but complementary caching layers:

## Layer 1: Server-Side Full-Page HTML Cache (`CacheMiddleware.php`)

* **Cache Miss (First Visit):** When a user visits a heavy page (like `/admin-dashboard`), PHP executes all controller logic, queries the database, renders the complete HTML, and saves it to a file (e.g., `cache/responses/cache_user_3_<md5hash>.html`) with a 55-second Time-To-Live (TTL). This process typically takes a few seconds.
* **Cache Hit (Subsequent Visits):** If the user navigates back to the page within 55 seconds, `CacheMiddleware` intercepts the request and serves the saved HTML file directly. The PHP controller is never instantiated, and database queries are bypassed. (To ensure security, CSRF tokens are dynamically regex-replaced before serving, preventing cached forms from breaking).
* **PJAX Interaction:** When the PJAX router fetches a new URL (e.g., `fetch('/admin-dashboard')`), the server still returns this fully cached HTML. The PJAX script then rapidly parses the response and surgically extracts only the `#pjax-content`, `#pjax-header`, and `#pjax-nav-links` containers to swap into the current DOM. This combines the backend speed of the HTML cache with the frontend fluidity of a single-page application.

## Layer 2: Browser HTTP Cache (Implicit)

* **Static Assets:** Static files such as `tailwind.css`, `chart.min.js`, and `pjax-router.js` are served by the web server (Apache/Nginx) with standard `Cache-Control` headers, instructing the browser to keep them in memory.
* **PJAX Interaction:** Without PJAX, the browser would re-parse the `<head>` on every navigation and quickly verify the cache for each asset. With PJAX, the `<head>` of the document is completely ignored during navigation. Static assets are loaded exactly once during the initial hard page load and remain persistently active in memory for the duration of the session, completely eliminating redundant network verification requests.

## Layer 3: Server-Side Query Cache (`Cache::remember`)

* **Targeted Query Caching:** For dynamic pages that cannot be fully HTML-cached (like paginated tables in `/statistics`, `/intake`, or `/tasks/completed`), the system uses `App\Core\Cache::remember()` to store expensive query results, such as the *Total Items Count* needed for cursor pagination. This is typically cached for 300 seconds (5 minutes).
* **Cache Miss:** The first visit executes the heavy `COUNT(*)` query (scanning potentially hundreds of thousands of documents), which can take 1–2 seconds. The result is then stored in the cache.
* **Cache Hit:** Subsequent visits within the 5-minute window instantly fetch the count from memory (taking < 5ms).
* **PJAX Interaction:** While the pagination count is cached, the *data query* (fetching the actual 15 rows) is intentionally NOT cached to ensure users always see the latest documents. Because these data queries have been hyper-optimized (e.g., replacing slow `ROW_NUMBER()` window functions with lightning-fast `MAX() ... GROUP BY` covering index queries), the data query executes in under 1 millisecond. When combined with PJAX, the result is instantaneous navigation even on dynamic data tables.

## The Combined Effect

1. **Initial Navigation to a Heavy Page:** PHP cache miss (or query cache miss) → 1–3 seconds to load.
2. **Subsequent Navigations:** PHP HTML Cache Hit (55s) OR Query Cache Hit (300s) + Optimized Data Query (< 1ms) + No `<head>` re-parse + In-flight requests aborted on navigation = **Effectively instant transitions**.
3. **Non-Blocking Sessions:** The addition of `session_write_close()` in the `AuthMiddleware` early-releases the PHP session lock. This ensures that even if a background process (like an uncached data load) is running, subsequent PJAX requests from the user are not blocked and can complete immediately.
