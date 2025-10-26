<?php

declare(strict_types=1);

require_once __DIR__.'/../db.php';
require_once __DIR__.'/../lib/char_profile.php';
require_once __DIR__.'/../lib/links.php';

$name = trim($_GET['name'] ?? '');
if ($name === '') {
    echo '<div class="container-page"><div class="alert alert-warning">No character specified.</div></div>';

    return;
}

$pdo = db();
if (!$pdo instanceof PDO) {
    echo '<div class="container-page"><div class="alert alert-danger">Unable to connect to the database.</div></div>';

    return;
}

$p = nx_fetch_player($pdo, $name);
if (!$p) {
    echo '<div class="container-page"><div class="alert alert-danger">Character not found.</div></div>';

    return;
}

if (isset($GLOBALS['nx_meta_title'])) {
    $GLOBALS['nx_meta_title'] = $p['name'] . ' – Character';
}

$skills   = nx_fetch_skills($pdo, (int) $p['id']);
$guild    = nx_fetch_guild($pdo, (int) $p['id']);
$house    = nx_fetch_house($pdo, (int) $p['id']);
$deaths   = nx_fetch_deaths($pdo, (int) $p['id']);
$kills    = nx_fetch_kills($pdo, (int) $p['id']);
$equip    = nx_fetch_equipment($pdo, (int) $p['id']);

function dt($ts): string
{
    return $ts ? date('Y-m-d H:i', is_numeric($ts) ? (int) $ts : strtotime((string) $ts)) : '—';
}
?>

<div class="container-page">
  <div class="card nx-glow mb-3">
    <div class="card-body">
      <div class="d-flex align-items-center justify-content-between">
        <h3 class="mb-0"><?= htmlspecialchars($p['name']) ?></h3>
        <span class="badge bg-primary"><?= (int) $p['level'] ?> <?= htmlspecialchars($p['vocation'] ?? '') ?></span>
      </div>
      <div class="text-muted small">Created: <?= dt($p['account_created'] ?? 0) ?> · Last login: <?= dt($p['lastlogin'] ?? 0) ?> ·
        Premium: <?= !empty($p['is_premium']) ? 'Active until ' . dt($p['premium_ends_at']) : 'None' ?></div>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-12 col-lg-4">
      <div class="card nx-glow h-100">
        <div class="card-header">Stats</div>
        <div class="card-body">
          <dl class="row mb-0">
            <dt class="col-6">Level</dt><dd class="col-6 text-end"><?= (int) $p['level'] ?></dd>
            <dt class="col-6">Magic Level</dt><dd class="col-6 text-end"><?= (int) ($p['maglevel'] ?? 0) ?></dd>
            <dt class="col-6">HP</dt><dd class="col-6 text-end"><?= (int) ($p['health'] ?? 0) ?>/<?= (int) ($p['healthmax'] ?? 0) ?></dd>
            <dt class="col-6">MP</dt><dd class="col-6 text-end"><?= (int) ($p['mana'] ?? 0) ?>/<?= (int) ($p['manamax'] ?? 0) ?></dd>
            <dt class="col-6">Town</dt><dd class="col-6 text-end"><?= (int) ($p['town_id'] ?? 0) ?></dd>
            <?php if ($guild): ?>
              <dt class="col-6">Guild</dt><dd class="col-6 text-end"><?= htmlspecialchars($guild['name']) ?></dd>
            <?php endif; ?>
            <?php if ($house): ?>
              <dt class="col-6">House</dt><dd class="col-6 text-end"><?= htmlspecialchars($house['name']) ?></dd>
            <?php endif; ?>
          </dl>
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-4">
      <div class="card nx-glow h-100">
        <div class="card-header">Skills</div>
        <div class="card-body">
          <ul class="list-group list-group-flush">
            <?php foreach ($skills as $k => $v): ?>
              <li class="list-group-item bg-transparent d-flex justify-content-between">
                <span class="text-capitalize"><?= htmlspecialchars($k) ?></span>
                <span class="fw-semibold"><?= (int) $v ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-4">
      <div class="card nx-glow h-100">
        <div class="card-header">Equipment</div>
        <div class="card-body">
          <?php if ($equip): ?>
            <ul class="list-group list-group-flush">
              <?php foreach ($equip as $e): ?>
                <li class="list-group-item bg-transparent d-flex justify-content-between">
                  <span><?= htmlspecialchars($e['slot']) ?></span>
                  <span>#<?= (int) $e['item_id'] ?><?= $e['count'] > 1 ? ' × ' . (int) $e['count'] : '' ?></span>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?>
            <div class="text-muted">No equipment data.</div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-3 mt-1">
    <div class="col-12 col-lg-6">
      <div class="card nx-glow">
        <div class="card-header">Recent Deaths</div>
        <div class="card-body">
          <?php if ($deaths): ?>
            <div class="table-responsive"><table class="table table-striped align-middle mb-0">
              <thead><tr><th>Time</th><th>Killer</th><th>Level</th></tr></thead>
              <tbody>
                <?php foreach ($deaths as $d): ?>
                  <tr>
                    <td><?= dt($d['time']) ?></td>
                    <td><?= $d['killer'] ? char_link($d['killer']) : '—' ?></td>
                    <td><?= (int) $d['level'] ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table></div>
          <?php else: ?>
            <div class="text-muted">No recent deaths.</div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-6">
      <div class="card nx-glow">
        <div class="card-header">Recent Kills</div>
        <div class="card-body">
          <?php if ($kills): ?>
            <div class="table-responsive"><table class="table table-striped align-middle mb-0">
              <thead><tr><th>Time</th><th>Victim</th><th>Level</th></tr></thead>
              <tbody>
                <?php foreach ($kills as $k): ?>
                  <tr>
                    <td><?= dt($k['time']) ?></td>
                    <td><?= !empty($k['victim']) ? char_link($k['victim']) : '—' ?></td>
                    <td><?= (int) $k['level'] ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table></div>
          <?php else: ?>
            <div class="text-muted">No recent kills.</div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
