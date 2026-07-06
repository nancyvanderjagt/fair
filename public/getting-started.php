<?php

declare(strict_types=1);

$pageTitle = 'Getting Started';

$pageDescription =
    'A step-by-step starting path for new 4-H members and fair families.';

$currentPage = 'getting-started';

require_once __DIR__ . '/../app/includes/header.php';

?>

<main>

<section class="page-hero">
  <div class="container narrow">
    <p class="eyebrow">New member path</p>
    <h1>Getting started without guessing</h1>
    <p>This page will become the step-by-step path from “we are interested in 4-H” through choosing projects and preparing for fair.</p>
  </div>
</section>
<section class="section">
  <div class="container narrow">
    <ol class="timeline">
      <li>
        <h2>Understand the organizations</h2>
        <p>Explain the relationship between 4-H, the extension office, the local club, the fair board, project leaders, superintendents, and the auction.</p>
        <span class="status draft">Research needed</span>
      </li>
      <li>
        <h2>Join and enroll</h2>
        <p>Document where families enroll, typical information requested, local deadlines, fees, and whom to contact when something is unclear.</p>
        <span class="status confirm">Varies locally</span>
      </li>
      <li>
        <h2>Choose projects</h2>
        <p>Help families understand the time, space, cost, ownership rules, recordkeeping, and fair requirements for each project.</p>
        <span class="status draft">Guide coming</span>
      </li>
      <li>
        <h2>Build the calendar backward</h2>
        <p>Turn fair week into earlier milestones for acquiring animals, tagging, vaccinations, registrations, workbooks, buyers, and practice.</p>
        <span class="status draft">Guide coming</span>
      </li>
    </ol>
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
