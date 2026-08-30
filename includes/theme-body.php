<?php
$darkPref = $_SESSION['dark_mode'] ?? null;
$colorTheme = getColorTheme();
$themeClass = $colorTheme !== 'default' ? 'theme-' . $colorTheme : '';
if ($darkPref === true || $darkPref == 1) {
    echo 'class="' . e(trim('dark ' . $themeClass)) . '"';
} elseif ($darkPref === false || $darkPref === 0 || $darkPref === '0') {
    echo 'class="' . e($themeClass) . '"';
} else {
    echo 'data-theme-auto="1"' . ($themeClass !== '' ? ' class="' . e($themeClass) . '"' : '');
}
?>