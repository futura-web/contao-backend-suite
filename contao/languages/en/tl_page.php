<?php

declare(strict_types=1);

$GLOBALS['TL_LANG']['tl_page']['backendSuite']['backendSuiteFlatUrl'] = ['Flat article URLs', 'Strips this page\'s own alias from the URL of news articles (or other content) read through it - e.g. "domain.com/beitrag/article-slug" becomes "domain.com/article-slug", sitewide including the sitemap. Only affects how the URL is shown; making "domain.com/article-slug" actually reachable additionally needs a one-time .htaccess/router setup (see the bundle README).'];
