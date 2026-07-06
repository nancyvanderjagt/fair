<?php

declare(strict_types=1);

$pageTitle = 'Project Guides';

$pageDescription =
    'Research-based guides for 4-H animal projects, still projects, and fair preparation.';

$currentPage = 'projects';

require_once __DIR__ . '/../app/includes/header.php';

?>

<main>

<section class="page-hero">
  <div class="container">
    <p class="eyebrow">Project library</p>
    <h1>Project guides</h1>
    <p>Start collecting information now. Each guide can later become a personalized checklist inside the app.</p>
  </div>
</section>
<section class="section">
  <div class="container">
    <div class="filter-note">
      <strong>First research priority:</strong> Build one complete guide from beginning to end before trying to cover every project.
    </div>
    <div class="card-grid project-grid">
      <article class="card"><p class="eyebrow">Animal project</p><h2>Goats</h2><p>Ownership, identification, health, feeding, fitting, showing, records, fair check-in, and auction.</p><a href="project-guides/goats.php">Open draft guide →</a></article>
      <article class="card"><p class="eyebrow">Animal project</p><h2>Chickens</h2><p>Bird selection, care, health, housing, entry classes, show preparation, records, and sale requirements.</p><a href="project-guides/chickens.php">Open draft guide →</a></article>
      <article class="card"><p class="eyebrow">Planned</p><h2>Swine</h2><p>Placeholder for future research.</p><span class="status draft">Not started</span></article>
      <article class="card"><p class="eyebrow">Planned</p><h2>Rabbits</h2><p>Placeholder for future research.</p><span class="status draft">Not started</span></article>
      <article class="card"><p class="eyebrow">Planned</p><h2>Still projects</h2><p>Placeholder for crafts, foods, horticulture, photography, and other non-livestock projects.</p><span class="status draft">Not started</span></article>
      <article class="card"><p class="eyebrow">Future structure</p><h2>Suggest a project</h2><p>Later, families and leaders could flag missing guides and submit official sources for review.</p><span class="status draft">Future feature</span></article>
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
</php>
