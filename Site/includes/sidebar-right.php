<?php
require_once __DIR__ . '/../widgets/_registry.php';

echo render_widget_box('online', 10);
echo render_widget_box('server_status');
echo render_widget_box('recent_deaths', 8);
