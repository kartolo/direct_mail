<?php
declare(strict_types=1);

namespace DirectMailTeam\DirectMail\Utility;

use DirectMailTeam\DirectMail\Repository\PagesRepository;
use DirectMailTeam\DirectMail\Utility\Typo3ConfVarsUtility;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Utility\CsvUtility;
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
    public function getCsvValues(string $str, string $sep = ','): array
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

    /**
     * Parse CSV lines into array form
     *
     * @param array $lines CSV lines
     * @param string $fieldList List of the fields
     *
     * @return array parsed CSV values
     */
    public function rearrangeCsvValues(array $lines, $fieldList): array
    {
        $out = [];
        if (is_array($lines) && count($lines) > 0) {
            // Analyse if first line is fieldnames.
            // Required is it that every value is either
            // 1) found in the list fieldsList in this class,
            // 2) the value is empty (value omitted then) or
            // 3) the field starts with "user_".
            // In addition fields may be prepended with "[code]".
            // This is used if the incoming value is true in which case '+[value]'
            // adds that number to the field value (accummulation) and '=[value]'
            // overrides any existing value in the field
            $first = $lines[0];
            $fieldListArr = explode(',', $fieldList);
            if ($dmConfigAddRecipFields = Typo3ConfVarsUtility::getDMConfigAddRecipFields()) {
                $fieldListArr = array_merge($fieldListArr, explode(',', $dmConfigAddRecipFields));
            }
            $fieldName = 1;
            $fieldOrder = [];

            foreach ($first as $v) {
                list($fName, $fConf) = preg_split('|[\[\]]|', $v);
                $fName = trim($fName);
                $fConf = trim($fConf);
                $fieldOrder[] = [$fName, $fConf];
                if ($fName && substr($fName, 0, 5) != 'user_' && !in_array($fName, $fieldListArr)) {
                    $fieldName = 0;
                    break;
                }
            }
            // If not field list, then:
            if (!$fieldName) {
                $fieldOrder = [
                    ['name'],
                    ['email']
                ];
            }
            // Re-map values
            reset($lines);
            if ($fieldName) {
                // Advance pointer if the first line was field names
                next($lines);
            }

            $c = 0;
            foreach ($lines as $data) {
                // Must be a line with content.
                // This sorts out entries with one key which is empty. Those are empty lines.
                if (count($data) > 1 || $data[0]) {
                    // Traverse fieldOrder and map values over
                    foreach ($fieldOrder as $kk => $fN) {
                        if ($fN[0]) {
                            if ($fN[1]) {
                                // If is true
                                if (trim($data[$kk])) {
                                    if (substr($fN[1], 0, 1) == '=') {
                                        $out[$c][$fN[0]] = trim(substr($fN[1], 1));
                                    }
                                    elseif (substr($fN[1], 0, 1) == '+') {
                                        $out[$c][$fN[0]] += substr($fN[1], 1);
                                    }
                                }
                            }
                            else {
                                $out[$c][$fN[0]] = trim($data[$kk]);
                            }
                        }
                    }
                    $c++;
                }
            }
        }
        return $out;
    }

    /**
     * Send csv values as download by sending appropriate HTML header
     *
     * @param array $idArr Values to be put into csv
     *
     * @return void Sent HML header for a file download
     */
    public function downloadCSV(array $idArr)
    {
        // https://api.typo3.org/master/class_t_y_p_o3_1_1_c_m_s_1_1_core_1_1_utility_1_1_csv_utility.html
        $lines = [];
        if (is_array($idArr) && count($idArr)) {
            reset($idArr);
            $lines[] = CsvUtility::csvValues(array_keys(current($idArr)));

            reset($idArr);
            foreach ($idArr as $rec) {
                $lines[] = CsvUtility::csvValues($rec);
            }
        }

        $filename = 'DirectMail_export_' . date('dmy-Hi') . '.csv';
        $mimeType = 'application/octet-stream';
        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: attachment; filename=' . $filename);
        echo implode(CR . LF, $lines);
        exit;
    }
}
