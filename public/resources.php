<?php

declare(strict_types=1);

$pageTitle = 'Resources';

$pageDescription =
    'Official sources, forms, record books, and research used to verify 4-H and fair information.';

$currentPage = 'resources';

require_once __DIR__ . '/../app/includes/header.php';

?>


<main>

<section class="page-hero">
  <div class="container narrow">
    <p class="eyebrow">Source library</p>
    <h1>Official resources and research notes</h1>
    <p>This is where we will collect links without pretending every link applies to every family.</p>
  </div>
</section>
<section class="section">
  <div class="container narrow">
    <div class="callout">
      <h2>Source rule</h2>
      <p>Whenever possible, save the page title, organization, URL, publication year or version, date checked, applicable county or state, project tags, and a short note about what the source proves.</p>
    </div>
    <div class="resource-table-wrap">
      <table class="resource-table">
        <thead><tr><th>Source</th><th>Applies to</th><th>Status</th><th>Checked</th></tr></thead>
        <tbody>
          <tr><td>Add official state 4-H source</td><td>Michigan</td><td><span class="status draft">To research</span></td><td>—</td></tr>
          <tr><td>Add county fair handbook</td><td>County-specific</td><td><span class="status draft">To research</span></td><td>—</td></tr>
          <tr><td>Add animal workbook library</td><td>Project-specific</td><td><span class="status draft">To research</span></td><td>—</td></tr>
        </tbody>
      </table>
    </div>
    <p class="small-note">The editable research log is in <code>research/SOURCE_LOG.md</code> inside this project.</p>
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
