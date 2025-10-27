<?php

declare(strict_types=1);

$GLOBALS['nx_meta_title'] = 'Features';

$features = [
    [
        'slug' => 'feature_echo_ai',
        'title' => 'E.C.H.O AI System',
        'summary' => 'Dynamic encounter guidance that keeps your adventures fresh, reactive, and rewarding in every session.',
    ],
    [
        'slug' => 'feature_dynamic_economy',
        'title' => 'Dynamic Player-Driven Economy',
        'summary' => 'A living marketplace that responds to player actions, supply shifts, and regional demand for resources.',
    ],
    [
        'slug' => 'feature_unified_reputation',
        'title' => 'Unified Reputation & Trading Economy System',
        'summary' => 'One standing to rule them all—your deeds influence trading perks, faction access, and world perception.',
    ],
];
?>
<section class="page page--features">
    <div class="container-page">
        <div class="card nx-card nx-glow mb-4">
            <div class="card-body">
                <h2 class="mb-3">Discover What Sets Devnexus Apart</h2>
                <p class="text-muted mb-0">
                    Explore the flagship systems that shape your journey. Each feature below dives into what you can expect as a
                    player, how to make the most of it, and why it matters when you step into Devnexus Online.
                </p>
            </div>
        </div>

        <div class="row g-4">
            <?php foreach ($features as $feature): ?>
                <div class="col-12 col-lg-4">
                    <div class="card h-100 nx-card nx-glow">
                        <div class="card-body d-flex flex-column">
                            <h3 class="h5 mb-3"><?php echo sanitize($feature['title']); ?></h3>
                            <p class="flex-grow-1 text-muted"><?php echo sanitize($feature['summary']); ?></p>
                            <a class="btn btn-primary mt-3" href="?p=<?php echo sanitize($feature['slug']); ?>">Learn more</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
