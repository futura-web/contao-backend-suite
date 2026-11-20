<?php

declare(strict_types=1);

$GLOBALS['TL_LANG']['tl_bs_redirect_import']['title_legend'] = 'Import run';
$GLOBALS['TL_LANG']['tl_bs_redirect_import']['target_legend'] = 'Target in the new project';
$GLOBALS['TL_LANG']['tl_bs_redirect_import']['source_legend'] = 'Source of the old addresses';

$GLOBALS['TL_LANG']['tl_bs_redirect_import']['title'] = ['Name', 'Free text, e.g. the name of the old project.'];
$GLOBALS['TL_LANG']['tl_bs_redirect_import']['rootPage'] = ['Website root', 'Determines which page tree is searched for target pages. The brackets show the URL prefix and suffix an old address has to fit.'];
$GLOBALS['TL_LANG']['tl_bs_redirect_import']['targetFolder'] = ['Location of the redirect pages', 'The redirect pages are created below this page. The position in the tree does not affect the address, only the alias does.'];
$GLOBALS['TL_LANG']['tl_bs_redirect_import']['sourceType'] = ['Where do the old addresses come from?', ''];
$GLOBALS['TL_LANG']['tl_bs_redirect_import']['urlList'] = ['URL list', 'One address per line, as a full URL or as a path. Lines starting with # are skipped.'];
$GLOBALS['TL_LANG']['tl_bs_redirect_import']['waybackDomain'] = ['Domain of the old project', 'For example "old.com". The Internet Archive is queried for every archived address of that domain, including subdomains.'];
$GLOBALS['TL_LANG']['tl_bs_redirect_import']['waybackLimit'] = ['Maximum number', 'Upper limit for one query.'];

$GLOBALS['TL_LANG']['tl_bs_redirect_import']['list'] = 'Paste a URL list';
$GLOBALS['TL_LANG']['tl_bs_redirect_import']['wayback'] = 'Query the Internet Archive (Wayback)';

$GLOBALS['TL_LANG']['tl_bs_redirect_import']['children'] = ['Show suggestions', 'Show the redirect suggestions of this import run'];
$GLOBALS['TL_LANG']['tl_bs_redirect_import']['runImport'] = ['Create suggestions', 'Read the source and calculate redirect suggestions'];
$GLOBALS['TL_LANG']['tl_bs_redirect_import']['editheader'] = ['Edit settings', 'Edit the settings of this import run'];
$GLOBALS['TL_LANG']['tl_bs_redirect_import']['new'] = ['New import run', 'Create a new import run'];
