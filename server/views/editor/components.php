<?php

use Components\Formatter;
use Components\Repository;
use Definitions\Formatter as DefinitionsFormatter;
use Definitions\Repository as DefinitionsRepository;
use Editor\ComponentPresenter;

$editorActive = 'components';

$pdo = get_db_connection();
$formatter = new Formatter();
$definitionsFormatter = new DefinitionsFormatter();
$definitionsRepository = new DefinitionsRepository($pdo);
$repository = new Repository($pdo, $formatter, $definitionsRepository);
$presenter = new ComponentPresenter($repository, $formatter, $definitionsRepository, $definitionsFormatter);

$componentViewModel = $presenter->presentInitial();
$componentSummaryData = $componentViewModel['summary'];
$componentCreateData = $componentViewModel['createForm'];
$componentListData = $componentViewModel['listHtml'];

require __DIR__ . '/../editor.php';
