<?php

declare(strict_types=1);

$pageTitle = 'Updates';

$pageDescription =
    'Track when fair information was reviewed, verified, or changed.';

$currentPage = 'updates';

require_once __DIR__ . '/../app/includes/header.php';

?>

<main>

<section class="page-hero">
  <div class="container narrow">
    <p class="eyebrow">Change tracking</p>
    <h1>Updates and verification</h1>
    <p>For now, this is a manual log. Later, the app can monitor official pages and PDFs for possible changes and send them to an admin review queue.</p>
  </div>
</section>
<section class="section">
  <div class="container narrow">
    <article class="update-entry">
      <div>
        <p class="eyebrow">Site foundation</p>
        <h2>Starter website created</h2>
      </div>
      <time datetime="2026-07-02">July 2, 2026</time>
      <p>Created the initial public information structure, source-tracking files, project guide placeholders, and update log.</p>
    </article>
    <div class="callout">
      <h2>Future monitored-update workflow</h2>
      <p>Detect a changed page or PDF → preserve the prior version → summarize possible changes → require human review → update guidance → notify affected families only after approval.</p>
    </div>
  </div>
</section>

</main>

<footer class="site-footer">
  <div class="container footer-grid">
    <div>
      <strong>Fair</strong>
      <p>An independent planning and education resource. Always confirm requirements with your official county, club, extension office, and fair publications.</p>
    </div>
    <div>
      <p><a href="resources.php">Official sources</a></p>
      <p><a href="updates.php">Last verified information</a></p>
      <p>&copy; <span data-current-year></span> Fair</p>
    </div>
  </div>
</footer>
<script src="assets/js/site.js"></script>

</body>
</html>
