<?php

declare(strict_types=1);

namespace DirectMailTeam\DirectMail\Utility;

use DirectMailTeam\DirectMail\Repository\PagesRepository;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class TsUtility
{
    /**
     * Implodes a multi dimensional TypoScript array, $p,
     * into a one-dimensional array (return value)
     *
     * @param array $p TypoScript structure
     * @param string $k Prefix string
     *
     * @return array Imploded TypoScript objectstring/values
     */
    public function implodeTSParams(array $p, string $k = ''): array
    {
        $implodeParams = [];
        if (is_array($p)) {
            foreach ($p as $kb => $val) {
                if (is_array($val)) {
                    $implodeParams = array_merge($implodeParams, $this->implodeTSParams($val, $k . $kb));
                } else {
                    $implodeParams[$k . $kb] = $val;
                }
            }
        }
        return $implodeParams;
    }

    /**
     * Updates Page TSconfig for a page with $id
     * The function seems to take $pageTS as an array with properties
     * and compare the values with those that already exists for the "object string",
     * $TSconfPrefix, for the page, then sets those values which were not present.
     * $impParams can be supplied as already known Page TSconfig, otherwise it's calculated.
     *
     * THIS DOES NOT CHECK ANY PERMISSIONS. SHOULD IT?
     * More documentation is needed.
     *
     * @param int $id Page id
     * @param array $pageTs Page TS array to write
     * @param string $tsConfPrefix Prefix for object paths
     * @param array|string $impParams [Description needed.]
     *
     *
     * @see implodeTSParams(), getPagesTSconfig()
     */
    public function updatePagesTSconfig(int $id, array $pageTs, string $tsConfPrefix, $impParams = '')
    {
        $done = false;
        $id = (int)$id;
        if (is_array($pageTs) && $id > 0) {
            if (!is_array($impParams)) {
                $impParams = $this->implodeTSParams(BackendUtility::getPagesTSconfig($id));
            }
            $set = [];
            foreach ($pageTs as $f => $v) {
                // only get the first line of input and ignore the rest
                $v = strtok(trim($v), "\r\n");
                // if token is not found (false)
                if ($v === false) {
                    // then set empty string
                    $v = '';
                }
                $f = $tsConfPrefix . $f;
                $tempF = isset($impParams[$f]) ? trim($impParams[$f]) : '';
                if (strcmp($tempF, $v)) {
                    $set[$f] = $v;
                }
            }
            if (count($set)) {
                // Get page record and TS config lines
                $pRec = BackendUtility::getRecord('pages', $id);
                $tsLines = explode(LF, $pRec['TSconfig'] ?: '');
                $tsLines = array_reverse($tsLines);
                // Reset the set of changes.
                foreach ($set as $f => $v) {
                    $inserted = 0;
                    foreach ($tsLines as $ki => $kv) {
                        if (substr($kv, 0, strlen($f) + 1) == $f . '=') {
                            $tsLines[$ki] = $f . '=' . $v;
                            $inserted = 1;
                            break;
                        }
                    }
                    if (!$inserted) {
                        $tsLines = array_reverse($tsLines);
                        $tsLines[] = $f . '=' . $v;
                        $tsLines = array_reverse($tsLines);
                    }
                }
                $tsLines = array_reverse($tsLines);

                // store those changes
                $tsConf = implode(LF, $tsLines);
                $done = GeneralUtility::makeInstance(PagesRepository::class)->updatePageTSconfig((int)$id, $tsConf);
            }
        }

        return $done;
    }
}
