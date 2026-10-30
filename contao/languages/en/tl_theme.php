<?php

declare(strict_types=1);

$GLOBALS['TL_LANG']['tl_theme']['backendSuite']['backendSuiteQuicklinkModules'] = 'Modules';
$GLOBALS['TL_LANG']['tl_theme']['backendSuite']['backendSuiteQuicklinkLayouts'] = 'Page layouts';
$GLOBALS['TL_LANG']['tl_theme']['backendSuite']['backendSuiteQuicklinkImageSizes'] = 'Image sizes';
$GLOBALS['TL_LANG']['tl_theme']['backendSuite']['switchProject'] = 'Switch project';
$GLOBALS['TL_LANG']['tl_theme']['backendSuite']['backendSuiteShrinkOnUpload'] = ['Shrink & rename images on upload', 'Oversized images are automatically shrunk, file names are cleaned up (lowercase, umlauts, spaces → underscore, ".min" before the extension).'];
$GLOBALS['TL_LANG']['tl_theme']['backend_suite_legend'] = 'Backend Suite settings';

$GLOBALS['TL_LANG']['tl_theme']['backendSuite']['backendSuiteFonts'] = ['Fonts (automatic import)', 'Search for a font and select subsets/variants - downloads the files via google-webfonts-helper and writes them to _fontface.scss. Multiple entries possible.'];
$GLOBALS['TL_LANG']['tl_theme']['backendSuite']['backendSuiteFontsDeleteOld'] = ['Delete old fonts', 'Removes all font files on save that are not part of the current selection (otherwise files from a previous selection remain).'];
$GLOBALS['TL_LANG']['tl_theme']['backendSuite']['backendSuiteFontSupport'] = ['Browser support', 'Which formats are generated per variant - matches the three levels on gwfh.mranftl.com/fonts. Applies to all fonts in the list above.'];
$GLOBALS['TL_LANG']['tl_theme']['backendSuite']['backendSuiteFontSupportRef'] = [
    'modern' => 'Modern Browsers (woff2 only)',
    'legacy' => 'Legacy Support (woff2 + ttf)',
    'historic' => 'Historic Support (eot, woff2, woff, ttf, svg)',
];
$GLOBALS['TL_LANG']['tl_theme']['backendSuite']['backendSuiteColors'] = ['Colors', 'One line per variable: "$variable | value" or "$variable value". Optional color name ALWAYS after "//": "$variable | value //Name" or "$variable value //Name". Examples: $orange | #ff8c05 - or - $orange #ff8c05 //Orange. Writes/updates the variable in _vars_colors.scss, new variable names are appended at the end of the file.'];
$GLOBALS['TL_LANG']['tl_theme']['backend_suite_font_color_legend'] = 'Fonts & colors (automatic import)';
