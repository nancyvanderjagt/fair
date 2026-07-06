<?php

declare(strict_types=1);

$pageTitle = 'Fair Week';

$pageDescription =
    'Understand fair check-in, barn duty, showing, auction, release, and teardown.';

$currentPage = 'fair-week';

require_once __DIR__ . '/../app/includes/header.php';

?>

<main>

<section class="page-hero">
  <div class="container narrow">
    <p class="eyebrow">The week itself</p>
    <h1>Fair week, explained before you arrive</h1>
    <p>This section should reduce panic by showing the order of events, what to bring, who is responsible, and what families often learn too late.</p>
  </div>
</section>
<section class="section">
  <div class="container narrow">
    <div class="accordion-list">
      <details open>
        <summary>Before arrival</summary>
        <p>Entry confirmation, health paperwork, project books, supplies, feed, bedding, show clothes, sale materials, and transportation.</p>
      </details>
      <details>
        <summary>Check-in and weigh-in</summary>
        <p>Where to go, when to arrive, who handles the animal, required paperwork, identification checks, and what happens if there is a problem.</p>
      </details>
      <details>
        <summary>Barn duty and daily care</summary>
        <p>Feeding, watering, cleaning, appearance standards, duty schedules, communication, and parent-versus-member expectations.</p>
      </details>
      <details>
        <summary>Show day</summary>
        <p>Arrival time, grooming, class order, ring etiquette, equipment, clothing, and what judging may include.</p>
      </details>
      <details>
        <summary>Auction and buyer outreach</summary>
        <p>Buyer letters, approaching businesses, sale order, thanking supporters, payment expectations, and permitted language.</p>
      </details>
      <details>
        <summary>Release and teardown</summary>
        <p>Animal release, tack removal, stall cleaning, club responsibilities, hauling, and final paperwork.</p>
      </details>
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
