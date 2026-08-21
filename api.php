<?php
require_once __DIR__ . '/functions.php';
startSession();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
<title>API - <?= e(SITE_NAME) ?></title>
<link rel="stylesheet" href="/assets/style.css?v=18">
</head>
<body <?php include __DIR__ . '/includes/theme-body.php'; ?>>
<script>if(document.body.hasAttribute('data-theme-auto')&&window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches){document.body.classList.add('dark');}</script>
<?php include __DIR__ . '/includes/header.php'; ?>
<main>
    <h2>Welcome to the ScratchNews API!</h2>
    <p>A small, read-only API for building things on top of ScratchNews: Discord bots, dashboards, whatever you want.
        Access is API-key based and allows 30 requests a minute by default. If you want more, reach out on <a href="https://discord.gg/Z6GBswx5Q">Discord</a> and tell me why do you want an API key.</p>

    <h3 style="margin-top:2rem;">GET /api/articles.php</h3>
    <p>Returns published articles, paginated. Optional query params: <code>page</code>, <code>per_page</code> (max 50), <code>category</code> (a category slug).</p>
    <pre class="api-code-block">{
  "data": [ { "id": 15, "title": "...", "summary": "...", "content": "...",
              "image_url": "...", "author": "...", "created_at": "...",
              "updated_at": "...", "views": 36,
              "categories": [ { "id": 2, "name": "Editorials", "slug": "editorials" } ] } ],
  "page": 1,
  "per_page": 20,
  "total": 14
}</pre>

    <h3 style="margin-top:2rem;">GET /api/articles.php?id=15</h3>
    <p>Returns a single published article by ID, or a 404 if it doesn't exist or isn't published.</p>

    <h3 style="margin-top:2rem;">GET /api/categories.php</h3>
    <p>Returns the full list of categories.</p>
    <pre class="api-code-block">[
  { "id": 2, "name": "Editorials", "slug": "editorials" },
  { "id": 3, "name": "Community", "slug": "community" }
]</pre>

    <h3 style="margin-top:2rem;">GET /api/explore.php</h3>
    <p>Same response shape as <code>/api/articles.php</code>, but with Explore-page-style filtering and sorting. Optional query params:</p>
    <ul>
        <li><code>category</code> : a category slug, or <code>all</code> (default)</li>
        <li><code>sort</code> : one of <code>metrics</code> (default, trending), <code>recent</code>, <code>popular</code>, <code>most_liked</code>, <code>most_disliked</code>, <code>oldest</code></li>
        <li><code>author</code> : substring match against author name</li>
        <li><code>from</code> / <code>to</code> : date range, <code>YYYY-MM-DD</code></li>
        <li><code>page</code>, <code>per_page</code> (max 50)</li>
    </ul>

    <h3 style="margin-top:2rem;">Authentication</h3>
    <p>Send <code>Authorization: Bearer &lt;your key&gt;</code> for a higher rate limit. Without a key you get 30 requests/minute by default, bucketed by IP. Want a key with a higher limit? Reach out on <a href="https://discord.gg/Z6GBswx5Q">Discord</a> and tell me why.</p>

    <h3 style="margin-top:2rem;">Errors</h3>
    <p>An invalid API key returns <code>401</code> with <code>{"error": "Invalid API key"}</code>. Exceeding your rate limit returns <code>429</code> with <code>{"error": "Rate limit exceeded, slow down"}</code>.</p>

    <h3 style="margin-top:2rem;">Static mirror (for high-volume or blocked consumers)</h3>
    <p>InfinityFree's free-tier hosting blocks automated/bot traffic at the network level, which breaks server-to-server API access for some consumers regardless of a valid key. For that case, a periodically-updated static snapshot is available with no auth and no rate limit:</p>
    <ul>
        <li><a href="https://raw.githubusercontent.com/xTODB/scratchnews-data/main/data/articles.json">articles.json</a> : all published articles, including <code>views</code>, <code>likes</code>, <code>dislikes</code>, <code>comments</code> per article, so you can replicate any Explore sort yourself</li>
        <li><a href="https://raw.githubusercontent.com/xTODB/scratchnews-data/main/data/categories.json">categories.json</a></li>
    </ul>
    <p>This fallback API updates automatically whenever an article is published, edited, or removed.</p>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>