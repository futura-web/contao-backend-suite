<?php

declare(strict_types=1);

$GLOBALS['TL_LANG']['tl_bs_redirect']['candidate_legend'] = 'Old address';
$GLOBALS['TL_LANG']['tl_bs_redirect']['target_legend'] = 'Redirect target';

$GLOBALS['TL_LANG']['tl_bs_redirect']['source'] = ['Old address', 'The address as it was in the old project. Read-only, the alias is derived from it.'];
$GLOBALS['TL_LANG']['tl_bs_redirect']['alias'] = ['Alias of the redirect page', 'The new redirect page answers under this alias.'];
$GLOBALS['TL_LANG']['tl_bs_redirect']['targetPage'] = ['Target page', 'Redirected here permanently (301). A reference to the page is stored, not its path, so the redirect stays correct if the alias of the target page changes later.'];
$GLOBALS['TL_LANG']['tl_bs_redirect']['score'] = ['Match', ''];
$GLOBALS['TL_LANG']['tl_bs_redirect']['state'] = ['State', ''];
$GLOBALS['TL_LANG']['tl_bs_redirect']['createdPage'] = ['Created page', ''];

$GLOBALS['TL_LANG']['tl_bs_redirect']['states'] = [
    'ready' => 'target certain',
    'review' => 'check target',
    'notarget' => 'no target found',
    'exists' => 'address already taken',
    'created' => 'page created',
    'skip_query' => 'not mappable: query string',
    'skip_prefix' => 'not mappable: different language prefix',
    'skip_suffix' => 'not mappable: different URL suffix',
    'skip_invalid' => 'not mappable: characters not allowed in an alias',
    'skip_root' => 'home page, no redirect needed',
];

$GLOBALS['TL_LANG']['tl_bs_redirect']['createPages'] = ['Create redirect pages', 'Create redirect pages in the page tree from every suggestion that has a target'];
