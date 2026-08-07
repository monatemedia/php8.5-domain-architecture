<?php
declare(strict_types=1);

/**
 * index.php — cover page
 * =======================
 *
 * Purely a signpost. Herd will serve it if you point a site at this folder, but
 * nothing in the course is delivered over HTTP: every lesson runs from the CLI.
 * It reads the filesystem rather than a hard-coded list, so it cannot drift out
 * of date as modules are written.
 */

$root = __DIR__;

/** @return list<array{id:string,title:string,dir:string}> */
function lessonsIn(string $moduleDir): array {
    $out = [];
    foreach (glob($moduleDir . '/lesson-*', GLOB_ONLYDIR) ?: [] as $dir) {
        if (!preg_match('/^lesson-(\d+\.\d+)-(.*)$/', basename($dir), $m)) { continue; }
        $out[] = [
            'id'    => $m[1],
            'title' => ucfirst(str_replace('-', ' ', $m[2])),
            'dir'   => basename($moduleDir) . '/' . basename($dir),
        ];
    }
    usort($out, static fn($a, $b) => version_compare($a['id'], $b['id']));
    return $out;
}

$modules = [];
foreach (glob($root . '/module-*', GLOB_ONLYDIR) ?: [] as $dir) {
    if (!preg_match('/^module-(\d+)-(.*)$/', basename($dir), $m)) { continue; }
    $modules[] = [
        'num'     => (int) $m[1],
        'title'   => ucfirst(str_replace('-', ' ', $m[2])),
        'lessons' => lessonsIn($dir),
    ];
}
usort($modules, static fn($a, $b) => $a['num'] <=> $b['num']);

$written = array_sum(array_map(static fn($m) => count($m['lessons']), $modules));

$e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Advanced Domain Architecture &amp; Tactical DDD — PHP 8.5</title>
<style>
  :root { --ink:#1a1d21; --dim:#6b7280; --line:#e5e7eb; --accent:#7c3aed; --bg:#fbfbfd; }
  * { box-sizing: border-box; }
  body { margin:0; background:var(--bg); color:var(--ink);
         font:16px/1.65 ui-sans-serif,-apple-system,"Segoe UI",Roboto,sans-serif; }
  .wrap { max-width: 900px; margin: 0 auto; padding: 3rem 1.5rem 5rem; }
  h1 { font-size: 2rem; margin: 0 0 .35rem; letter-spacing: -.02em; }
  .sub { color: var(--dim); margin: 0 0 2rem; font-size: 1.05rem; }
  .note { border-left: 3px solid var(--accent); background:#fff; padding: .9rem 1.1rem;
          margin: 0 0 2.5rem; border-radius: 0 6px 6px 0; }
  .note code { background:#f3f4f6; padding:.1rem .35rem; border-radius:3px; font-size:.9em; }
  .q { font-size: 1.15rem; font-style: italic; margin: 0 0 .6rem; }
  h2 { font-size: .8rem; text-transform: uppercase; letter-spacing: .09em;
       color: var(--dim); margin: 2.5rem 0 .75rem; font-weight: 600; }
  .mod { background:#fff; border:1px solid var(--line); border-radius:8px;
         padding: 1rem 1.25rem; margin-bottom: .75rem; }
  .mod h3 { margin:0 0 .5rem; font-size:1rem; }
  .mod h3 span { color: var(--accent); font-variant-numeric: tabular-nums; }
  ul { margin:0; padding-left: 1.1rem; }
  li { color:#374151; font-size:.94rem; }
  li code { color: var(--dim); font-size: .85em; }
  .empty { color: var(--dim); font-style: italic; font-size:.92rem; }
  footer { margin-top: 3rem; padding-top: 1.5rem; border-top:1px solid var(--line);
           color: var(--dim); font-size:.88rem; }
</style>
</head>
<body>
<div class="wrap">

  <h1>Advanced Domain Architecture &amp; Tactical DDD</h1>
  <p class="sub">Modelling business reality in PHP 8.5 — aggregates, invariants, events and exception trees.</p>

  <div class="note">
    <p class="q">&ldquo;If I deleted my framework, my database driver and my web server tomorrow
      morning, would the code in this file still make sense to someone who runs the business?&rdquo;</p>
    <p style="margin:0"><strong>Yes</strong> &rarr; domain layer. <strong>No</strong> &rarr; infrastructure layer.
      Every lesson comes back to this.</p>
  </div>

  <div class="note" style="border-left-color:#6b7280">
    <p style="margin:0">This page is a signpost, nothing more. The course runs from the command
      line. Start with <code>php check.php</code>, then open <code>README.md</code>.</p>
  </div>

  <h2><?= $written ?> lessons written</h2>

  <?php foreach ($modules as $m): ?>
    <div class="mod">
      <h3><span>Module <?= $m['num'] ?></span> — <?= $e($m['title']) ?></h3>
      <?php if ($m['lessons'] === []): ?>
        <p class="empty" style="margin:0">Not written yet.</p>
      <?php else: ?>
        <ul>
          <?php foreach ($m['lessons'] as $l): ?>
            <li><strong><?= $e($l['id']) ?></strong> — <?= $e($l['title']) ?>
              <code><?= $e($l['dir']) ?></code></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>

  <footer>
    PHP <?= PHP_VERSION ?> &middot; follows <em>PHP 8.5 OOP Mastery</em>, which is a hard prerequisite.
  </footer>

</div>
</body>
</html>
