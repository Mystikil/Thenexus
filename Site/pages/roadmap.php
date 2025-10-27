<?php

declare(strict_types=1);

$GLOBALS['nx_meta_title'] = 'Roadmap';

/**
 * Determine the bootstrap background class for a progress bar
 */
function nx_roadmap_progress_class(int $percent): string
{
    if ($percent >= 85) {
        return 'bg-success';
    }

    if ($percent >= 60) {
        return 'bg-info';
    }

    if ($percent >= 40) {
        return 'bg-warning text-dark';
    }

    return 'bg-danger';
}

$roadmapSections = [
    'Game World' => [
        [
            'title' => 'Instances (Parallel Worlds)',
            'stage' => 'Alpha Implementation',
            'percent' => 72,
            'description' => 'Parallel Nexus shards with unique modifiers and Sigil Quest-bound transfers for long-term progression.',
            'milestones' => [
                'Instance modifiers rotation finalized (Permadeath, Hardcore, Cursed, High EXP).',
                'Sigil Quest scripting in QA; Instance Pass inventory purge hooks pending.',
                'Cross-instance persistence for levels/skills completed.',
            ],
        ],
        [
            'title' => 'Dungeons',
            'stage' => 'Vertical Slice Testing',
            'percent' => 64,
            'description' => 'Instanced challenge runs with corruption variants, no-exit rules, and bespoke loot tables.',
            'milestones' => [
                'Dungeon generator supporting escalating difficulty tiers.',
                'Corrupted Dungeon roll (4%) implemented; reward tables need balancing.',
                'Solo & small-party lockouts validating on staging realms.',
            ],
        ],
        [
            'title' => 'Dynamic Weather & Environment',
            'stage' => 'Systems Integration',
            'percent' => 58,
            'description' => 'Region-specific climates adjusting combat, visibility, spawns, and NPC schedules in real time.',
            'milestones' => [
                'Weather controller broadcasting Rain, Snow, Fog, Sandstorm, Eclipse states.',
                'Combat modifiers for rain/lightning & snow/frost tuning underway.',
                'NPC routing and spawn weights reacting to weather data next on the list.',
            ],
        ],
        [
            'title' => 'World Epochs',
            'stage' => 'Design Finalization',
            'percent' => 46,
            'description' => 'Age-based global rule sets (Fire, Frozen Eclipse, etc.) driven by aggregate player behavior.',
            'milestones' => [
                'Age transition triggers drafted and reviewed with narrative team.',
                'Loot & dungeon theme overrides prototyped in Lua events.',
                'Epoch UI feedback loop still pending client implementation.',
            ],
        ],
    ],
    'Systems & Mechanics' => [
        [
            'title' => 'E.C.H.O. Adaptive AI',
            'stage' => 'Alpha',
            'percent' => 70,
            'description' => 'Monsters learn from player tactics, mutate over time, and escalate through up to 20 behavior phases.',
            'milestones' => [
                'Evolution memory storage optimized for regional shards.',
                'Phase-tier tuning scripts iterating with combat team.',
                'Mutation broadcast tooling queued for telemetry polish.',
            ],
        ],
        [
            'title' => 'Item Serial System',
            'stage' => 'Beta-ready',
            'percent' => 82,
            'description' => 'Unique serials track provenance, cloning recipes, and allow Summon Stone restoration across the world.',
            'milestones' => [
                'Serialization rolled out to crafted and dropped gear.',
                'Market verification hooks active; lineage UI pending final pass.',
                'Summon Stone workflow undergoing destructive edge case testing.',
            ],
        ],
        [
            'title' => 'Online Market System',
            'stage' => 'Economy Tuning',
            'percent' => 68,
            'description' => 'Account-level trading, economic taxes, and dynamic NPC pricing tied to marketplace activity.',
            'milestones' => [
                'Character listing lockout and audit logging complete.',
                'Dynamic fee sink influencing town inflation metrics.',
                'Regional stock adjustments entering live-fire simulations.',
            ],
        ],
        [
            'title' => 'Online Store Integration',
            'stage' => 'Implementation',
            'percent' => 55,
            'description' => 'Secure web store delivering purchases via in-game mail with economy reinforcement kickbacks.',
            'milestones' => [
                'MySQL API bridge authenticated and logging.',
                'Mail delivery pipeline working in QA; failsafe retries under review.',
                'NPC economy reinforcement percentages pending financial modeling.',
            ],
        ],
        [
            'title' => 'NPC Reputation',
            'stage' => 'Prototype',
            'percent' => 44,
            'description' => 'Per-NPC relationship scores granting price shifts, exclusive quests, and behavioral changes.',
            'milestones' => [
                'Reputation storage schema migrated to support per-character deltas.',
                'Pricing modifiers prototyped; quest unlock conditions drafting.',
                'Hostility reactions and suspicion states queued for AI sync.',
            ],
        ],
        [
            'title' => 'NPC Inventory & Supply',
            'stage' => 'Pre-Alpha',
            'percent' => 38,
            'description' => 'Globally shared stock pools, scheduled restocks, and player-triggered trade caravans.',
            'milestones' => [
                'Inventory pooling rules authored; persistence layer pending.',
                'Restock scheduler drafted with economy telemetry inputs.',
                'Caravan event scripting planned for next sprint.',
            ],
        ],
        [
            'title' => 'Player Affinity System',
            'stage' => 'Alpha Prototype',
            'percent' => 52,
            'description' => 'Passive elemental progressions unlocking traits based on the player’s preferred combat style.',
            'milestones' => [
                'Affinity tracking per combat action validated.',
                'First pass traits (Fire, Light, Venom) in balance iteration.',
                'Unique spell rewards concepted; implementation scheduled.',
            ],
        ],
        [
            'title' => 'Karma & Alignment',
            'stage' => 'Design Complete',
            'percent' => 48,
            'description' => 'Light, Neutral, and Dark alignment paths influencing city access, auras, and quest arcs.',
            'milestones' => [
                'Action weighting tables approved by narrative & systems leads.',
                'Aura visuals prototyped on Nexus client branch.',
                'Shrine gating logic being wired into faction hooks.',
            ],
        ],
        [
            'title' => 'Combat Evolution System',
            'stage' => 'Implementation',
            'percent' => 57,
            'description' => 'Contextual passives like Blindfighter, Soulhunter, and Stormedge earned through situational combat.',
            'milestones' => [
                'Environment tagging for combat encounters complete.',
                'Passive reward tables authored; stacking rules testing.',
                'UI feedback and tooltip communication next for client team.',
            ],
        ],
        [
            'title' => 'Mutation System (E.C.H.O. 2.0)',
            'stage' => 'Prototype',
            'percent' => 43,
            'description' => 'Boss/anomaly exposure triggering temporary mutations, corruption penalties, and social reactions.',
            'milestones' => [
                'Mutation affix library drafted with visual concepts.',
                'Corruption scaling rules synced with karma framework.',
                'Dialogue/NPC reaction scripting scheduled.',
            ],
        ],
    ],
    'Social & Economic Systems' => [
        [
            'title' => 'Faction Allegiances',
            'stage' => 'Alpha Planning',
            'percent' => 49,
            'description' => 'Join Scholars, Mercenaries, Shadowbound, or Guardians for ranks, emblems, and regional control bonuses.',
            'milestones' => [
                'Faction questlines drafted with unique reward ladders.',
                'Emblem progression art underway.',
                'Regional dungeon hooks to factions mapped in design docs.',
            ],
        ],
        [
            'title' => 'Player Governance',
            'stage' => 'Pre-Alpha',
            'percent' => 36,
            'description' => 'City leadership elections, taxation levers, and player-approved urban projects.',
            'milestones' => [
                'Voting booth UI wireframes complete.',
                'Taxation impact on NPC services in modeling phase.',
                'Project approval flow pending backend scaffold.',
            ],
        ],
        [
            'title' => 'Guild Territory Control',
            'stage' => 'Alpha',
            'percent' => 63,
            'description' => 'Claimable camps, mines, and fortresses that supply resources and PvP protection zones.',
            'milestones' => [
                'Territory claim rules and cooldowns implemented.',
                'Resource drip tables balancing with economy leads.',
                'Guild-wide buff delivery hooking into progression API.',
            ],
        ],
    ],
    'Crafting & Economy' => [
        [
            'title' => 'Professions & Mini-Games',
            'stage' => 'Alpha',
            'percent' => 61,
            'description' => 'Blacksmithing, Alchemy, Enchanting, and gathering with interactive mini-games for mastery.',
            'milestones' => [
                'Forging temperature/pressure mini-game playable internally.',
                'Alchemy ratio puzzles integrating with potion outputs.',
                'Lockpicking prototype pending audio/visual polish.',
            ],
        ],
        [
            'title' => 'Soulbinding & Corruption',
            'stage' => 'Implementation',
            'percent' => 54,
            'description' => 'High-risk item enhancement tying into serial tracking with destruction and power trade-offs.',
            'milestones' => [
                'Soulbinding flags sync with trade restrictions completed.',
                'Corruption failure states in QA with logging for rebuilds.',
                'Player messaging and UI warnings scheduled.',
            ],
        ],
        [
            'title' => 'Resource Ecosystem',
            'stage' => 'Design Integration',
            'percent' => 47,
            'description' => 'Global spawn rates adapt to harvesting data, promoting trade routes and competition.',
            'milestones' => [
                'Telemetry hooks capturing gathering density.',
                'Dynamic respawn curves authored by economy team.',
                'Trade route events planned with world builders.',
            ],
        ],
    ],
    'Endgame Systems' => [
        [
            'title' => 'Legacy / Ascension',
            'stage' => 'Alpha',
            'percent' => 59,
            'description' => 'Level-cap reset path unlocking new classes, relic crafting, and visible Legacy Titles.',
            'milestones' => [
                'Ascension reset workflow coded with passive retention.',
                'Legacy Title showcase UI iterating with art team.',
                'Post-ascension class unlock balance underway.',
            ],
        ],
        [
            'title' => 'Soul Echoes',
            'stage' => 'Prototype',
            'percent' => 41,
            'description' => 'Persistent death remnants enabling blessing or corruption interactions for social stakes.',
            'milestones' => [
                'Echo spawn & decay timers functioning on dev servers.',
                'Bless/Corrupt interactions wired to karma system next.',
                'Soul fragment reward tables drafted.',
            ],
        ],
        [
            'title' => 'Anomaly Rifts',
            'stage' => 'Alpha',
            'percent' => 62,
            'description' => 'Unstable world tears spawning elites and relics with escalating corruption if ignored.',
            'milestones' => [
                'Rift spawn cadence tuned for population scaling.',
                'Elite encounter templates scripted.',
                'Global corruption feedback loop awaiting client VFX.',
            ],
        ],
    ],
    'Immersion & Lore' => [
        [
            'title' => 'Lore Memory Codex',
            'stage' => 'Alpha',
            'percent' => 60,
            'description' => 'Discoverable lore compendium rewarding exploration XP and unlocking hidden quests.',
            'milestones' => [
                'Codex data schema and unlock tracking complete.',
                'Lore XP reward tuning in progress.',
                'Quest trigger integration queued for narrative scripting.',
            ],
        ],
        [
            'title' => 'Dream Realms',
            'stage' => 'Prototype',
            'percent' => 39,
            'description' => 'Inn-rest dream sequences delivering procedural stories, puzzles, and crafting materials.',
            'milestones' => [
                'Dream generator seeds authored with narrative beats.',
                'Puzzle module integration pending client-side UX.',
                'Unique material drop tables being balanced.',
            ],
        ],
        [
            'title' => 'Cinematic World Events',
            'stage' => 'Implementation',
            'percent' => 56,
            'description' => 'Client-driven camera pans and overlays for bosses, invasions, and epoch shifts.',
            'milestones' => [
                'OTClient scripting hooks firing in test builds.',
                'Event triggers tied to world bosses completed.',
                'Localization pipeline for captions being established.',
            ],
        ],
    ],
    'Technical & Platform' => [
        [
            'title' => 'Server Framework Enhancements',
            'stage' => 'Beta',
            'percent' => 78,
            'description' => 'Custom TFS 10.98 branch with Lua dynamic events, JSON serialization, and real-time weather timers.',
            'milestones' => [
                'Core fork stabilized with nightly builds.',
                'Lua event sandbox hardened for live updates.',
                'Weather timer service under load testing.',
            ],
        ],
        [
            'title' => 'External Integrations',
            'stage' => 'Implementation',
            'percent' => 53,
            'description' => 'RESTful APIs, web dashboard, and Discord webhooks syncing markets, store, and world events.',
            'milestones' => [
                'REST endpoints authenticated with rate limiting.',
                'Dashboard data views iterating with web team.',
                'Discord webhook formatter scheduled for polish.',
            ],
        ],
        [
            'title' => 'Art & Audio Direction',
            'stage' => 'Art Production',
            'percent' => 65,
            'description' => 'Classic 32×32 pixel art pipeline, curated palette, adaptive soundtrack, and modular UI vision.',
            'milestones' => [
                'Palette (emerald, ember, obsidian, plasma) locked for tilesets.',
                'Adaptive ambience tracks in mastering.',
                'UI component library entering implementation.',
            ],
        ],
    ],
];
?>

<section class="page page--roadmap">
    <div class="container-page">
        <div class="card nx-card nx-glow mb-4">
            <div class="card-body">
                <h1 class="h3 mb-3">DevNexus Online Development Roadmap</h1>
                <p class="text-muted mb-0">
                    Follow the heartbeat of DevNexus Online. Every major feature from the design vision is tracked here with its
                    current build status, percent completion, and the next milestones the team is tackling. This roadmap updates as
                    sprint goals shift, ensuring players always know where each system stands.
                </p>
            </div>
        </div>

        <?php foreach ($roadmapSections as $sectionTitle => $features): ?>
            <div class="card nx-card nx-glow mb-4">
                <div class="card-header bg-transparent border-bottom-0">
                    <h2 class="h5 mb-0"><?php echo sanitize($sectionTitle); ?></h2>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <?php foreach ($features as $feature): ?>
                            <div class="col-12 col-xl-6">
                                <div class="roadmap-feature h-100 p-3 border rounded-3 bg-dark bg-opacity-10">
                                    <div class="d-flex flex-column gap-2">
                                        <div>
                                            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                                <h3 class="h5 mb-0"><?php echo sanitize($feature['title']); ?></h3>
                                                <span class="badge bg-primary-subtle text-primary-emphasis px-3 py-2">
                                                    <?php echo sanitize($feature['stage']); ?>
                                                </span>
                                            </div>
                                            <p class="text-muted mb-0 small">
                                                <?php echo sanitize($feature['description']); ?>
                                            </p>
                                        </div>
                                        <div>
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <span class="small text-uppercase text-muted">Progress</span>
                                                <span class="fw-semibold small"><?php echo (int) $feature['percent']; ?>%</span>
                                            </div>
                                            <div class="progress" role="progressbar" aria-label="<?php echo sanitize($feature['title']); ?> progress" aria-valuenow="<?php echo (int) $feature['percent']; ?>" aria-valuemin="0" aria-valuemax="100">
                                                <div class="progress-bar <?php echo nx_roadmap_progress_class((int) $feature['percent']); ?>" style="width: <?php echo (int) $feature['percent']; ?>%"></div>
                                            </div>
                                        </div>
                                        <?php if (!empty($feature['milestones'])): ?>
                                            <div>
                                                <span class="small text-uppercase text-muted">Next Milestones</span>
                                                <ul class="small mb-0 mt-1">
                                                    <?php foreach ($feature['milestones'] as $item): ?>
                                                        <li><?php echo sanitize($item); ?></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>
