<?php
require dirname(__DIR__) . "/vendor/autoload.php";
use OpenSpout\Writer\XLSX\Writer;

use OpenSpout\Writer\Common\Creator\Style\StyleBuilder;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Cell;

$writer = new Writer();
$writer->openToFile('test_spout.xlsx');

$styleCant = (new StyleBuilder())->setFontName('Canterbury')->setFontSize(14)->build();
$styleBold = (new StyleBuilder())->setFontBold()->build();

$writer->addRow(Row::fromValues(['Republic of the Philippines'], $styleCant));
$writer->addRow(Row::fromValues(['Department of Education'], $styleCant));
$writer->addRow(Row::fromValues(['Region X - Northern Mindanao'], $styleBold));
$writer->addRow(Row::fromValues(['SCHOOLS DIVISION OF ILIGAN CITY'], $styleBold));
$writer->addRow(Row::fromValues([]));

$writer->addRow(Row::fromValues(['Tracking Code', 'Title', 'Purpose', 'District', 'Submitted By', 'Date Released'], $styleBold));

$writer->close();
echo "Done";
