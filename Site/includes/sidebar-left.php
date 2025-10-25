<?php
require_once __DIR__ . '/../widgets/_registry.php';

echo render_widget_box('top_levels', 10);
echo render_widget_box('top_guilds', 8);
echo render_widget_box('vote_links');
