<?php
declare(strict_types=1);

namespace DirectMailTeam\DirectMail\Utility;

use DirectMailTeam\DirectMail\Repository\PagesRepository;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class DmCsvUtility 
{
    /**
     * Parsing csv-formated text to an array
     *
     * @param string $str String in csv-format
     * @param string $sep Separator
     *
     * @return array Parsed csv in an array
     */
    public static function getCsvValues(string $str, string $sep = ','): array
    {
        $fh = tmpfile();
        fwrite($fh, trim($str));
        fseek($fh, 0);
        $lines = [];
        if ($sep == 'tab') {
            $sep = "\t";
        }
        while ($data = fgetcsv($fh, 1000, $sep)) {
            $lines[] = $data;
        }
        
        fclose($fh);
        return $lines;
    }
}