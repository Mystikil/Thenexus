<?php

declare(strict_types=1);

function char_link(string $name): string
{
    $n = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $u = '?p=character&name=' . rawurlencode($name);

    return '<a class="char-link" href="' . $u . '">' . $n . '</a>';
}
