<?php
declare(strict_types=1);

/**
 * index.php — Course cover page
 * ==============================
 *
 * Served by Herd if you point a site at this folder. Nothing in the course
 * itself is delivered over HTTP — every lesson is a CLI script — but Herd shows
 * a site preview for the folder, and without an index file that preview looks
 * like a broken site.
 *
 * THE COMPANION TO THE OOP MASTERY COVER PAGE, AND DELIBERATELY ITS OPPOSITE
 * --------------------------------------------------------------------------
 * That page is dark, sans-serif and laid out as a grid of cards: a terminal,
 * which is what a course about wiring components should look like. This one is
 * paper — warm, serif-titled, ruled, and laid out as a single vertical spine —
 * because this course is about modelling a business, and the artefact it
 * produces is closer to a document than to a service graph.
 *
 * The two share their bones on purpose. Same eyebrow, lede, live status pills
 * and progress bar; same numbered getting-started steps; and a "six rules"
 * section in the same position, because these six are the sequel to those six.
 * Open them side by side and they should read as one pair.
 *
 * WHAT IS DIFFERENT BEYOND THE PAINT
 * -----------------------------------
 *   - The one question the whole course answers, given the space it deserves
 *   - Module 0's warning, before the syllabus rather than after it
 *   - The import-scan result, which is the only claim here that is a build
 *     failure rather than a promise
 *
 * IT READS THE FILESYSTEM rather than a hard-coded module list, so it cannot
 * drift out of date. The structure comes from disk; only the prose is written
 * down here.
 *
 * Self-contained: no Composer autoload, no CDN, no build step. It must render
 * correctly BEFORE `composer install` has ever been run, because that is
 * exactly when a confused reader first opens it.
 */

// ─────────────────────────────────────────────────────────────────────────────
// Live environment
// ─────────────────────────────────────────────────────────────────────────────

$root       = __DIR__;
$phpVersion = PHP_VERSION;
$phpOk      = PHP_VERSION_ID >= 80500;
$php84      = PHP_VERSION_ID >= 80400;
$vendorOk   = is_file($root . '/vendor/autoload.php');
$sqliteOk   = extension_loaded('pdo_sqlite');

// ─────────────────────────────────────────────────────────────────────────────
// Progress, from the reader's own copy of the tracker
// ─────────────────────────────────────────────────────────────────────────────

$done = $total = 0;
$hasProgress = is_file($root . '/PROGRESS.md');

if ($hasProgress) {
    preg_match_all('/\[([ xX])\]/', (string) file_get_contents($root . '/PROGRESS.md'), $boxes);
    $total = count($boxes[1]);
    $done  = count(array_filter($boxes[1], static fn(string $c): bool => strtolower($c) === 'x'));
}

$pct = $total > 0 ? (int) round($done / $total * 100) : 0;

// ─────────────────────────────────────────────────────────────────────────────
// The syllabus, read from disk
// ─────────────────────────────────────────────────────────────────────────────

/** @return list<array{id:string,title:string,dir:string,hasChallenge:bool,hasQuiz:bool}> */
function lessonsIn(string $moduleDir): array
{
    $lessons = [];

    foreach (glob($moduleDir . '/lesson-*', GLOB_ONLYDIR) ?: [] as $dir) {
        if (!preg_match('/^lesson-(\d+\.\d+)-(.*)$/', basename($dir), $m)) {
            continue;
        }

        $lessons[] = [
            'id'           => $m[1],
            'title'        => ucfirst(str_replace('-', ' ', $m[2])),
            'dir'          => basename($moduleDir) . '/' . basename($dir),
            'hasChallenge' => is_dir($dir . '/challenge'),
            'hasQuiz'      => is_dir($dir . '/quiz'),
        ];
    }

    usort($lessons, static fn(array $a, array $b): int => version_compare($a['id'], $b['id']));

    return $lessons;
}

$modules = [];

foreach (glob($root . '/module-*', GLOB_ONLYDIR) ?: [] as $dir) {
    if (!preg_match('/^module-(\d+)-(.*)$/', basename($dir), $m)) {
        continue;
    }

    $modules[] = [
        'n'       => (int) $m[1],
        'slug'    => basename($dir),
        'title'   => ucwords(str_replace('-', ' ', $m[2])),
        'lessons' => lessonsIn($dir),
    ];
}

usort($modules, static fn(array $a, array $b): int => $a['n'] <=> $b['n']);

/**
 * The argument each module makes. Titles come from disk; the reason a module
 * exists does not, and should not.
 */
$blurbs = [
    0 => ['lede' => 'The gate, and the half of the subject almost every course omits: how to tell when this is the wrong tool.',
          'edge' => 'Two lessons. No code. The most important thing here.'],
    1 => ['lede' => 'Move behaviour to the data that owns it, make invalid states unrepresentable, and stop passing floats around as money.',
          'edge' => 'readonly classes · clone with · asymmetric visibility'],
    2 => ['lede' => 'What must be consistent together, right now — and why the answer is smaller than you think.',
          'edge' => 'Aggregate roots · transactions · domain events'],
    3 => ['lede' => 'The domain declares what it needs; infrastructure supplies it. Then the mapping problem, met head on rather than designed around.',
          'edge' => 'Repository interfaces · reconstitution · contract tests · SQLite'],
    4 => ['lede' => 'Why APIs return 500 for user error, and the tree of typed failures that fixes it — ending at an RFC 9457 boundary.',
          'edge' => 'Marker interfaces · structured context · result types · Problem Details'],
    5 => ['lede' => 'The situation almost everyone is actually in: the ORM owns your model and rewriting is not on the table.',
          'edge' => 'The ladder · characterisation tests · strangling one aggregate'],
    6 => ['lede' => 'Assemble all of it behind a Slim API, and prove the domain layer does not depend on any of it.',
          'edge' => 'Slim 4 · PHP-DI 7 · an import scan that fails the build'],
];

// ─────────────────────────────────────────────────────────────────────────────
// Live counts — the same things verify.php reports
// ─────────────────────────────────────────────────────────────────────────────

$lessonCount    = array_sum(array_map(static fn(array $m): int => count($m['lessons']), $modules));
$challengeCount = array_sum(array_map(
    static fn(array $m): int => count(array_filter($m['lessons'], static fn(array $l): bool => $l['hasChallenge'])),
    $modules
));
$quizCount = array_sum(array_map(
    static fn(array $m): int => count(array_filter($m['lessons'], static fn(array $l): bool => $l['hasQuiz'])),
    $modules
));

$exampleCount  = count(glob($root . '/module-*/lesson-*/examples/*.php') ?: []);
$solutionCount = count(glob($root . '/module-*/lesson-*/challenge/solution/run.php') ?: []);

$gateCount = 0;

if (is_file($root . '/GATES.md')) {
    $gateCount = preg_match_all('/^# Gate \d/m', (string) file_get_contents($root . '/GATES.md'));
}

/**
 * The number the capstone's import scan reports: every PHP file that holds
 * domain classes. Counted the same way the scan counts — skipping the
 * `infrastructure/` and `given/` directories that sit beside a solution.
 */
$domainFiles = 0;

foreach (array_merge([$root . '/domain/workshop'], glob($root . '/module-*/lesson-*/challenge/solution', GLOB_ONLYDIR) ?: []) as $dir) {
    if (!is_dir($dir)) {
        continue;
    }

    /** @var SplFileInfo $file */
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $file) {
        $path = str_replace('\\', '/', $file->getPathname());

        if ($file->getExtension() === 'php'
            && !str_contains($path, '/infrastructure/')
            && !str_contains($path, '/given/')) {
            $domainFiles++;
        }
    }
}

// The six rules, in the order COURSE_PHILOSOPHY.md argues them.
$rules = [
    'The dependency rule points inward',
    'Make illegal states unrepresentable',
    'The aggregate is a consistency boundary, and it is smaller than you think',
    "Speak the business's words, exactly",
    'A failure the business can predict is not exceptional',
    'Ceremony must be paid for',
];

$e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Advanced Domain Architecture &amp; Tactical DDD — PHP 8.5</title>
<meta name="description" content="A local, CLI-first course in tactical domain-driven design for PHP 8.5: aggregates, invariants, repositories, exception trees — and when not to use any of it.">
<style>
  :root {
    --paper:     #f7f5f2;
    --panel:     #ffffff;
    --ink:       #1b1a17;
    --muted:     #6b6660;
    --faint:     #938c83;
    --rule:      #e4dfd7;
    --rule-soft: #efebe4;
    --accent:    #0d6d68;
    --accent-dk: #0a4f4b;
    --accent-bg: #eef5f4;
    --amber:     #a1590b;
    --amber-bg:  #fdf6ec;
    --ok:        #15803d;
    --bad:       #b3261e;
    --maxw:      920px;
    --serif:     ui-serif, Georgia, "Iowan Old Style", "Palatino Linotype", "Times New Roman", serif;
    --sans:      ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    --mono:      ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, monospace;
  }

  * { box-sizing: border-box; }
  html { -webkit-text-size-adjust: 100%; scroll-behavior: smooth; }

  body {
    margin: 0;
    background: var(--paper);
    color: var(--ink);
    font: 16px/1.7 var(--sans);
    -webkit-font-smoothing: antialiased;
  }

  .wrap { max-width: var(--maxw); margin: 0 auto; padding-inline: 1.5rem; }

  a { color: var(--accent-dk); text-decoration-thickness: 1px; text-underline-offset: 2px; }

  code, kbd { font-family: var(--mono); font-size: 0.88em; }

  h1, h2, h3, h4 { font-family: var(--serif); font-weight: 600; letter-spacing: -0.01em; }

  /* ── Hero ─────────────────────────────────────────────────────────────── */

  header.hero {
    border-bottom: 1px solid var(--rule);
    background:
      linear-gradient(180deg, #fffefc 0%, var(--paper) 100%);
    padding-block: 3.5rem 3rem;
  }

  .eyebrow {
    font-family: var(--mono);
    font-size: 0.72rem;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: var(--accent);
    display: inline-block;
    margin-bottom: 1.1rem;
  }

  h1 { font-size: clamp(2.1rem, 5.5vw, 3.2rem); line-height: 1.12; margin: 0 0 1rem; }
  h1 em { font-style: italic; color: var(--accent-dk); }

  .lede { font-size: 1.12rem; color: #3d3a35; margin: 0 0 1rem; max-width: 62ch; }
  .lede.small { font-size: 0.97rem; color: var(--muted); }

  /* ── Status + progress ────────────────────────────────────────────────── */

  .status { display: flex; flex-wrap: wrap; gap: 0.5rem; margin: 1.6rem 0 0; }

  .pill {
    display: inline-flex; align-items: center; gap: 0.45rem;
    border: 1px solid var(--rule); background: var(--panel);
    border-radius: 999px; padding: 0.32rem 0.8rem;
    font-size: 0.82rem; color: #43403b;
  }
  .pill .dot { width: 7px; height: 7px; border-radius: 50%; background: var(--faint); }
  .pill.ok  .dot { background: var(--ok); }
  .pill.bad .dot { background: var(--bad); }
  .pill.bad { border-color: #e8c9c6; }

  .progress {
    margin-top: 1.4rem; background: var(--panel);
    border: 1px solid var(--rule); border-radius: 10px; padding: 1rem 1.15rem;
    max-width: 34rem;
  }
  .progress .row {
    display: flex; justify-content: space-between; align-items: baseline;
    font-size: 0.86rem; color: var(--muted); gap: 1rem;
  }
  .progress .row strong { color: var(--ink); font-family: var(--serif); font-size: 0.98rem; }
  .bar { height: 7px; background: var(--rule-soft); border-radius: 4px; overflow: hidden; margin: 0.6rem 0 0.5rem; }
  .bar i { display: block; height: 100%; background: var(--accent); border-radius: 4px; }

  .cta { display: flex; flex-wrap: wrap; gap: 0.7rem; margin-top: 1.8rem; }
  .btn {
    display: inline-block; border: 1px solid var(--rule);
    background: var(--panel); color: var(--ink);
    padding: 0.55rem 1.1rem; border-radius: 8px;
    font-size: 0.92rem; text-decoration: none;
  }
  .btn:hover { border-color: var(--accent); }
  .btn.primary { background: var(--accent); border-color: var(--accent); color: #fff; }
  .btn.primary:hover { background: var(--accent-dk); }

  /* ── Sections ─────────────────────────────────────────────────────────── */

  section { padding-block: 3.4rem; border-bottom: 1px solid var(--rule); }
  section:last-of-type { border-bottom: 0; }

  h2 { font-size: 1.6rem; margin: 0 0 0.4rem; }
  .sub { color: var(--muted); margin: 0 0 2rem; max-width: 62ch; }

  /* ── The question ─────────────────────────────────────────────────────── */

  .question {
    background: var(--panel); border: 1px solid var(--rule);
    border-radius: 12px; padding: 2rem 1.9rem;
  }
  .question blockquote {
    margin: 0; font-family: var(--serif); font-size: clamp(1.15rem, 2.6vw, 1.45rem);
    line-height: 1.45; font-style: italic; color: var(--ink);
  }
  .branches { display: grid; gap: 0.9rem; margin-top: 1.6rem; }
  @media (min-width: 640px) { .branches { grid-template-columns: 1fr 1fr; } }
  .branch { border-left: 3px solid var(--rule); padding-left: 1rem; }
  .branch.yes { border-left-color: var(--accent); }
  .branch.no  { border-left-color: var(--faint); }
  .branch b { display: block; font-family: var(--mono); font-size: 0.75rem;
              letter-spacing: 0.1em; text-transform: uppercase; margin-bottom: 0.25rem; }
  .branch.yes b { color: var(--accent); }
  .branch.no  b { color: var(--faint); }
  .branch span { font-size: 0.93rem; color: #43403b; }

  /* ── Warning ──────────────────────────────────────────────────────────── */

  .warn {
    background: var(--amber-bg); border: 1px solid #efdcc2;
    border-left: 3px solid var(--amber);
    border-radius: 10px; padding: 1.3rem 1.5rem;
  }
  .warn h3 { margin: 0 0 0.5rem; font-size: 1.12rem; color: #7c4408; }
  .warn p { margin: 0 0 0.7rem; font-size: 0.95rem; color: #55432c; }
  .warn p:last-child { margin-bottom: 0; }

  /* ── The spine ────────────────────────────────────────────────────────── */

  ol.spine { list-style: none; margin: 0; padding: 0; position: relative; }
  ol.spine::before {
    content: ""; position: absolute; left: 15px; top: 12px; bottom: 12px;
    width: 1px; background: var(--rule);
  }
  ol.spine > li { position: relative; padding: 0 0 2.1rem 3.1rem; }
  ol.spine > li:last-child { padding-bottom: 0; }
  ol.spine > li::before {
    content: attr(data-n);
    position: absolute; left: 0; top: 0;
    width: 31px; height: 31px; border-radius: 50%;
    background: var(--panel); border: 1px solid var(--rule);
    color: var(--accent); font-family: var(--mono); font-size: 0.8rem;
    display: grid; place-items: center;
  }
  ol.spine > li.capstone::before { background: var(--accent); border-color: var(--accent); color: #fff; }

  .mod-title { font-family: var(--serif); font-size: 1.18rem; margin: 0.15rem 0 0.35rem; }
  .mod-lede { margin: 0 0 0.75rem; font-size: 0.95rem; color: #43403b; max-width: 60ch; }
  .mod-lessons { list-style: none; margin: 0 0 0.75rem; padding: 0;
                 display: flex; flex-wrap: wrap; gap: 0.35rem 0.5rem; }
  .mod-lessons li {
    font-size: 0.82rem; color: var(--muted);
    background: var(--panel); border: 1px solid var(--rule-soft);
    border-radius: 6px; padding: 0.18rem 0.55rem;
  }
  .mod-lessons li b { color: var(--accent); font-family: var(--mono);
                      font-weight: 400; font-size: 0.78rem; margin-right: 0.3rem; }
  .mod-edge { font-family: var(--mono); font-size: 0.76rem; color: var(--faint); }

  /* ── Rules ────────────────────────────────────────────────────────────── */

  ol.rules { list-style: none; margin: 0; padding: 0; counter-reset: r; }
  ol.rules li {
    counter-increment: r;
    display: grid; grid-template-columns: 2rem 1fr; gap: 0.9rem;
    padding: 0.75rem 0; border-bottom: 1px solid var(--rule-soft);
    font-family: var(--serif); font-size: 1.02rem;
  }
  ol.rules li:last-child { border-bottom: 0; }
  ol.rules li::before {
    content: counter(r); font-family: var(--mono); font-size: 0.78rem;
    color: var(--accent); padding-top: 0.28rem;
  }

  /* ── Proof ────────────────────────────────────────────────────────────── */

  .proof { background: var(--panel); border: 1px solid var(--rule); border-radius: 12px; overflow: hidden; }
  .proof-head { padding: 1.4rem 1.6rem 0.9rem; }
  .proof-head h3 { margin: 0 0 0.4rem; font-size: 1.15rem; }
  .proof-head p { margin: 0; font-size: 0.93rem; color: var(--muted); }
  .proof pre {
    margin: 0; padding: 1.2rem 1.6rem 1.5rem;
    background: #fbfaf8; border-top: 1px solid var(--rule-soft);
    font-family: var(--mono); font-size: 0.82rem; line-height: 1.75;
    color: #43403b; overflow-x: auto;
  }
  .proof pre b { color: var(--accent-dk); font-weight: 600; }

  .figures { display: grid; gap: 0.8rem; margin-top: 1.5rem;
             grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); }
  .fig { background: var(--panel); border: 1px solid var(--rule); border-radius: 10px;
         padding: 0.9rem 1rem; }
  .fig b { display: block; font-family: var(--serif); font-size: 1.5rem; color: var(--accent-dk); }
  .fig span { font-size: 0.79rem; color: var(--muted); }

  /* ── Steps ────────────────────────────────────────────────────────────── */

  ol.steps { list-style: none; margin: 0; padding: 0; counter-reset: s; }
  ol.steps li { counter-increment: s; position: relative; padding: 0 0 1.7rem 2.6rem; }
  ol.steps li::before {
    content: counter(s); position: absolute; left: 0; top: 0.1rem;
    width: 1.6rem; height: 1.6rem; border-radius: 50%;
    background: var(--accent-bg); color: var(--accent-dk);
    font-family: var(--mono); font-size: 0.75rem;
    display: grid; place-items: center;
  }
  ol.steps h4 { margin: 0 0 0.3rem; font-size: 1.02rem; }
  ol.steps p { margin: 0 0 0.55rem; font-size: 0.93rem; color: var(--muted); max-width: 60ch; }
  ol.steps pre {
    margin: 0; background: var(--panel); border: 1px solid var(--rule);
    border-radius: 8px; padding: 0.6rem 0.85rem;
    font-family: var(--mono); font-size: 0.82rem; overflow-x: auto;
  }

  .note {
    background: var(--accent-bg); border: 1px solid #d3e6e4;
    border-left: 3px solid var(--accent);
    border-radius: 8px; padding: 1rem 1.2rem;
    font-size: 0.92rem; color: #33534f; margin-top: 1.6rem;
  }
  .note strong { color: #1d3a37; }

  footer { padding-block: 2.2rem 3.2rem; color: var(--faint); font-size: 0.84rem; }
  footer a { color: var(--muted); }

  @media (prefers-reduced-motion: reduce) { html { scroll-behavior: auto; } }
</style>
</head>
<body>

<header class="hero">
  <div class="wrap">
    <span class="eyebrow">Sequel to PHP 8.5 OOP Mastery · Local · CLI-first</span>

    <h1>Modelling business reality,<br><em>and knowing when not to</em></h1>

    <p class="lede">
      The previous course was about <em>how to wire components</em>. This one is about
      <em>what the components should be</em> — moving from loggers and mailers to a job card
      that knows the rules for cancelling itself, a price that cannot be negative, and a bill
      that refuses to be issued twice.
    </p>

    <p class="lede small">
      <?= $lessonCount ?> lessons across <?= count($modules) ?> modules, <?= $challengeCount ?>
      challenges, <?= $quizCount ?> quizzes and <?= $gateCount ?> closed-book gates. One running
      domain — a vehicle workshop — that accumulates from Lesson 1.1 to the capstone, so the
      capstone assembles classes first written in Module 1 without changing one of them.
    </p>

    <div class="status">
      <?php if ($phpOk): ?>
        <span class="pill ok"><span class="dot"></span>PHP <?= $e($phpVersion) ?></span>
      <?php elseif ($php84): ?>
        <span class="pill bad"><span class="dot"></span>PHP <?= $e($phpVersion) ?> — three examples need 8.5</span>
      <?php else: ?>
        <span class="pill bad"><span class="dot"></span>PHP <?= $e($phpVersion) ?> — needs 8.4+</span>
      <?php endif; ?>

      <span class="pill <?= $vendorOk ? 'ok' : '' ?>"><span class="dot"></span><?= $vendorOk ? 'Dependencies installed' : 'composer install — needed from Module 3' ?></span>
      <span class="pill <?= $sqliteOk ? 'ok' : 'bad' ?>"><span class="dot"></span>pdo_sqlite<?= $sqliteOk ? '' : ' missing' ?></span>
    </div>

    <?php if ($total > 0): ?>
      <div class="progress">
        <div class="row">
          <strong>Your progress</strong>
          <span><?= $done ?> of <?= $total ?> · <?= $pct ?>%</span>
        </div>
        <div class="bar"><i style="width: <?= max($pct, 1) ?>%"></i></div>
        <div class="row"><span>Tracked in your own <code>PROGRESS.md</code>, which is git-ignored.</span></div>
      </div>
    <?php elseif (!$hasProgress): ?>
      <div class="progress">
        <div class="row"><strong>No tracker yet</strong></div>
        <div class="row"><span>Run <code>cp PROGRESS.template.md PROGRESS.md</code> and this fills in.</span></div>
      </div>
    <?php endif; ?>

    <div class="cta">
      <a class="btn primary" href="#start">How to start</a>
      <a class="btn" href="#syllabus">The syllabus</a>
      <a class="btn" href="#proof">What is proved</a>
    </div>
  </div>
</header>

<section>
  <div class="wrap">
    <h2>The one question this whole course answers</h2>
    <p class="sub">Every lesson comes back to it. If you remember nothing else, remember the question.</p>

    <div class="question">
      <blockquote>
        “If I deleted my framework, my database driver and my web server tomorrow morning, would
        the code in this file still make sense to someone who runs the business?”
      </blockquote>

      <div class="branches">
        <div class="branch yes">
          <b>Yes</b>
          <span>It belongs in the <strong>domain layer</strong>. Entities, value objects, aggregates,
          domain events, domain exceptions.</span>
        </div>
        <div class="branch no">
          <b>No</b>
          <span>It belongs in the <strong>infrastructure layer</strong>. Controllers, repository
          implementations, HTTP mapping, database drivers, queue adapters.</span>
        </div>
      </div>
    </div>
  </div>
</section>

<section>
  <div class="wrap">
    <div class="warn">
      <h3>Read this before you plan anything</h3>
      <p>
        <strong>Most applications should not use tactical DDD.</strong> It is a large amount of
        ceremony that pays for itself only when the business rules are genuinely complicated.
        Applied to a CRUD app — forms in, rows out, no interesting invariants — it produces four
        layers of indirection around an <code>UPDATE</code> statement and makes the codebase worse.
      </p>
      <p>
        Module 0 exists to teach you to tell the difference, and it comes first deliberately. A
        course that teaches only the patterns produces developers who apply them everywhere. The
        judgement of <em>when not to</em> is the more valuable half, and it is the half almost every
        DDD course omits.
      </p>
    </div>
  </div>
</section>

<section id="syllabus">
  <div class="wrap">
    <h2>The syllabus</h2>
    <p class="sub">
      Strictly sequential, and genuinely cumulative — the capstone assembles classes first written
      in Lesson 1.2 without changing one of them. Every module ends with a closed-book gate that
      reaches back two or three modules.
    </p>

    <ol class="spine">
      <?php foreach ($modules as $m): ?>
        <?php $b = $blurbs[$m['n']] ?? ['lede' => '', 'edge' => '']; ?>
        <li data-n="<?= $m['n'] ?>" class="<?= $m['n'] === 6 ? 'capstone' : '' ?>">
          <h3 class="mod-title"><?= $e($m['title']) ?></h3>
          <?php if ($b['lede'] !== ''): ?>
            <p class="mod-lede"><?= $e($b['lede']) ?></p>
          <?php endif; ?>

          <?php if ($m['lessons'] !== []): ?>
            <ul class="mod-lessons">
              <?php foreach ($m['lessons'] as $l): ?>
                <li><b><?= $e($l['id']) ?></b><?= $e($l['title']) ?></li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>

          <?php if ($b['edge'] !== ''): ?>
            <div class="mod-edge"><?= $e($b['edge']) ?></div>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ol>
  </div>
</section>

<section>
  <div class="wrap">
    <h2>Six rules</h2>
    <p class="sub">
      The sequel to the previous course's six. Those were about how objects relate to each other;
      these are about what the objects are for. Rule 6 is the one that keeps the other five honest.
    </p>

    <ol class="rules">
      <?php foreach ($rules as $r): ?>
        <li><?= $e($r) ?></li>
      <?php endforeach; ?>
    </ol>

    <div class="note">
      Read <code>COURSE_PHILOSOPHY.md</code> before Lesson 0.1 — twenty minutes, and everything
      after leans on it. Then keep <code>DOMAIN.md</code> open: it is the workshop's language,
      lifecycle and ten invariants, and the lessons assume you can look things up in it.
    </div>
  </div>
</section>

<section id="proof">
  <div class="wrap">
    <h2>What is proved, rather than promised</h2>
    <p class="sub">
      The capstone scans every file in the domain layer and records every type it names. This is
      the course's thesis, and it is a build failure rather than a claim.
    </p>

    <div class="proof">
      <div class="proof-head">
        <h3>The domain layer's entire external vocabulary</h3>
        <p><?= $domainFiles ?> files scanned. Every name they refer to, in full:</p>
      </div>
<pre><b>DomainException
InvalidArgumentException
NoDiscard
Throwable</b></pre>
    </div>

    <p class="sub" style="margin-top:1.5rem">
      Not “no framework imports” — <strong>no imports</strong>. Domain classes share a namespace, so
      they name each other unqualified; the only names that survive the tokeniser are the SPL types
      the exception tree extends. Delete Slim, PDO, PHP-DI and the web server, and there is nothing
      left to remove.
    </p>

    <div class="figures">
      <div class="fig"><b><?= $solutionCount ?></b><span>reference solutions, each run against its own checks on every verification</span></div>
      <div class="fig"><b><?= $exampleCount ?></b><span>runnable examples</span></div>
      <div class="fig"><b>8</b><span>bugs the harness caught during the build — none of them by reading</span></div>
      <div class="fig"><b>0</b><span>expected-output blocks. Every challenge is judged by assertions.</span></div>
    </div>

    <div class="note">
      <strong>Why that last figure matters.</strong> The previous course was written and never
      executed, and shipped reference solutions that failed their own assertions. This one runs
      every solution on every <code>verify.php</code>, and re-runs every earlier lesson's spec
      against the newest code. The record — including all eight bugs and what each one cost —
      is in <code>RUN-THIS-FIRST.md</code>.
    </div>
  </div>
</section>

<section id="start">
  <div class="wrap">
    <h2>Getting started</h2>
    <p class="sub">
      This page is the only web page in the project. Every lesson is a command-line script. Open a
      terminal in the course folder.
    </p>

    <ol class="steps">
      <li>
        <h4>Confirm your PHP</h4>
        <p>
          <?php if ($phpOk): ?>
            You are on <strong><?= $e($phpVersion) ?></strong> — everything runs.
          <?php elseif ($php84): ?>
            You are on <strong><?= $e($phpVersion) ?></strong>. Three examples demonstrate 8.5 syntax
            and will not parse; everything else works. In Herd, check the <em>global</em> PHP
            override as well as the per-site version — a global override silently wins.
          <?php else: ?>
            You are on <strong><?= $e($phpVersion) ?></strong>. Below 8.4 nothing loads.
          <?php endif; ?>
        </p>
<pre>php -v</pre>
      </li>

      <li>
        <h4>Verify the course itself</h4>
        <p>
          Six steps: syntax-check every file, probe every 8.4 and 8.5 feature the lessons use, run
          every reference solution against its own acceptance checks, and re-run every earlier
          lesson's spec against the newest code.
        </p>
<pre>php verify.php</pre>
      </li>

      <li>
        <h4>Take your copy of the tracker</h4>
        <p>
          Your ticks and scores are yours, so <code>PROGRESS.md</code> is git-ignored and the
          template is not. Everything the course ships — including every reference solution and
          every quiz answer key — is in the repository.
        </p>
<pre>cp PROGRESS.template.md PROGRESS.md</pre>
      </li>

      <li>
        <h4>Find out where you are</h4>
        <p>
          Walks the lessons in order and stops at the first unsolved one, so it always gives you
          exactly one next action. When a challenge fails, it runs the reference solution before
          blaming you — if that fails too, you are told plainly that it is a course bug.
        </p>
<pre>php check.php</pre>
      </li>

      <li>
        <h4>Start at Module 0</h4>
        <p>
          Two short lessons and no code, and it is the most important thing in the course. It is
          also the one you will be most tempted to skip.
        </p>
<pre>module-0-when-and-when-not/lesson-0.1-the-cost-of-ddd/README.md</pre>
      </li>
    </ol>

    <div class="note">
      <strong>Each lesson has the same four parts.</strong> A <code>README.md</code> to read, an
      <code>examples/</code> folder to run, a <code>challenge/</code> judged by assertions in
      <code>checks.php</code>, and a <code>quiz/</code> with the answer key at the bottom. Read
      <code>checks.php</code> <em>first</em> — it is the specification, stated precisely, and
      working out what a system must do from its assertions is the same skill you use when you
      inherit a codebase with a test suite and no documentation.
    </div>
  </div>
</section>

<footer>
  <div class="wrap">
    PHP <?= $e($phpVersion) ?> ·
    <a href="https://www.php.net/releases/8.5/en.php">PHP 8.5</a> ·
    <a href="https://www.rfc-editor.org/rfc/rfc9457.html">RFC 9457</a> ·
    <a href="https://www.slimframework.com/docs/v4/">Slim 4</a> ·
    <a href="https://php-di.org/">PHP-DI</a> ·
    <a href="https://phpunit.de/">PHPUnit</a>
    <br>
    Prerequisite: <em>PHP 8.5 OOP Mastery</em>, completed — not read.
    This page is informational only; the course runs entirely from the command line.
  </div>
</footer>

</body>
</html>
