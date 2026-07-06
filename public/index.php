<?php

declare(strict_types=1);

$pageTitle = 'Home';

$pageDescription =
    'A clear path through 4-H projects, fair preparation, and fair week.';

$currentPage = 'home';

require_once __DIR__ . '/../app/includes/header.php';

?>

<main>

<section class="hero">
  <div class="container hero-grid">
    <div>
      <p class="eyebrow">A practical 4-H and fair guide</p>
      <h1>Know what comes next, with time to prepare.</h1>
      <p class="hero-copy">Fair is being built to help new and returning families understand projects, deadlines, paperwork, fair week, and auction preparation in one organized place.</p>
      <div class="button-row">
        <a class="button primary" href="getting-started.html">Start here</a>
        <a class="button secondary" href="projects.html">Browse project guides</a>
      </div>
    </div>
    <aside class="hero-card">
      <p class="eyebrow">Eventually, your dashboard will answer:</p>
      <ul class="check-list">
        <li>What should we be doing right now?</li>
        <li>What is due next?</li>
        <li>Which forms and workbooks do we need?</li>
        <li>What happens during fair week?</li>
        <li>Where did this information come from?</li>
      </ul>
    </aside>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="section-heading">
      <p class="eyebrow">Build the foundation first</p>
      <h2>What this first version will organize</h2>
    </div>
    <div class="card-grid">
      <article class="card">
        <span class="card-number">01</span>
        <h3>New member path</h3>
        <p>A plain-language explanation of clubs, projects, enrollment, meetings, records, and the fair timeline.</p>
        <a href="getting-started.html">Open getting started →</a>
      </article>
      <article class="card">
        <span class="card-number">02</span>
        <h3>Project guides</h3>
        <p>Species and project pages with official links, required documents, key dates, supplies, and questions to ask.</p>
        <a href="projects.html">Open project guides →</a>
      </article>
      <article class="card">
        <span class="card-number">03</span>
        <h3>Fair-week instructions</h3>
        <p>Check-in, weigh-in, stall setup, barn duty, showing, auction, release, teardown, and what to bring.</p>
        <a href="fair-week.html">Open fair week →</a>
      </article>
    </div>
  </div>
</section>

<section class="section muted">
  <div class="container split">
    <div>
      <p class="eyebrow">Trust matters</p>
      <h2>Every important instruction should point back to a source.</h2>
      <p>This site will separate official requirements from helpful advice. Each page can show where the information came from, when it was checked, and whether it still needs local confirmation.</p>
    </div>
    <div class="status-panel">
      <div><span class="status official">Official</span><p>Directly supported by a current official publication or page.</p></div>
      <div><span class="status confirm">Confirm locally</span><p>May vary by county, club, year, species, or superintendent.</p></div>
      <div><span class="status draft">Draft</span><p>Research collected but not ready to rely on yet.</p></div>
    </div>
  </div>
</section>

</main>

<?php

require_once __DIR__ . '/../app/includes/footer.php';
