<?php
require dirname(__DIR__) . "/vendor/autoload.php";
use OpenSpout\Writer\XLSX\Writer;

use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Cell;

$options = new Options();
$options->DEFAULT_COLUMN_WIDTH = 25;
$writer = new Writer($options);
$writer->openToFile('test_spout2.xlsx');

$styleCant = (new Style())->withFontName('Canterbury')->withFontSize(14);
$styleBold = (new Style())->withFontBold(true);

$writer->addRow(Row::fromValues(['Republic of the Philippines'], $styleCant));
$writer->addRow(Row::fromValues(['Department of Education'], $styleCant));
$writer->addRow(Row::fromValues(['Region X - Northern Mindanao'], $styleBold));
$writer->addRow(Row::fromValues(['SCHOOLS DIVISION OF ILIGAN CITY'], $styleBold));
$writer->addRow(Row::fromValues([]));

$writer->addRow(Row::fromValues(['Tracking Code', 'Title', 'Purpose', 'District', 'Submitted By', 'Date Released'], $styleBold));

$writer->close();
echo "Done";
