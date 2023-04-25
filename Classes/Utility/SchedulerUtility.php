<?php

declare(strict_types=1);

namespace DirectMailTeam\DirectMail\Utility;

/**
 * Code from TYPO3\CMS\Scheduler\Controller\SchedulerModuleController listTasksAction
 */

use DirectMailTeam\DirectMail\Repository\TempRepository;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Scheduler\Scheduler;

class SchedulerUtility
{
    protected static function getRegisteredClasses(LanguageService $languageService): array
    {
        $list = [];
        foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'] ?? [] as $class => $registrationInformation) {
            $list[$class] = [
                'extension' => $registrationInformation['extension'],
                'title' => isset($registrationInformation['title']) ? $languageService->sL($registrationInformation['title']) : '',
                'description' => isset($registrationInformation['description']) ? $registrationInformation['description'] : '',
                'provider' => $registrationInformation['additionalFields'] ?? '',
            ];
        }
        return $list;
    }

    public static function getDMTable(LanguageService $languageService): array
    {
        $tasks = GeneralUtility::makeInstance(TempRepository::class)->seachDMTask();
        $taskTable = [];

        if (is_array($tasks) && count($tasks) > 0) {
            $lllFile = 'LLL:EXT:scheduler/Resources/Private/Language/locallang.xlf';

            $registeredClasses = self::getRegisteredClasses($languageService);
            $dateFormat = Typo3ConfVarsUtility::getDateFormat();
            foreach ($tasks as $task) {
                $isRunning = false;
                $showAsDisabled = false;

                $taskTableRow = [
                    'translationKey' => ((int)$task['disable'] === 1) ? 'enable' : 'disable',
                    'class' => '',
                    'classTitle' => '',
                    'classExtension' => '',
                    'validClass' => false,
                    'uid' => $task['uid'],
                    'description' => $task['description'],
                    'additionalInformation' => '',
                    'progress' => '',
                    'task' => '',
                    'status' => '',
                    'type' => '',
                    'frequency' => '',
                    'lastExecution' => '',
                    'nextExecution' => '-',
                    'execType' => '',
                    'frequency' => '',
                    'multiple' => ''
                ];

                $exceptionWithClass = false;
                $taskObj = null;
                try {
                    $taskObj = unserialize($task['serialized_task_object']);

                    $class = get_class($taskObj);
                    if ($class === \__PHP_Incomplete_Class::class && preg_match('/^O:[0-9]+:"(?P<classname>.+?)"/', $task['serialized_task_object'], $matches) === 1) {
                        $class = $matches['classname'];
                    }

                    $taskTableRow['class'] = $class;

                    if (!empty($task['lastexecution_time'])) {
                        $taskTableRow['lastExecution'] = date($dateFormat, (int)$task['lastexecution_time']);
                        $context = ($task['lastexecution_context'] === 'CLI') ? 'label.cron' : 'label.manual';
                        $taskTableRow['lastExecution'] .= ' (' . $languageService->sL($lllFile . ':' . $context) . ')';
                    }
                } catch (\BadMethodCallException $e) {
                    $exceptionWithClass = true;
                }

                $scheduler = GeneralUtility::makeInstance(Scheduler::class);

                if (!$exceptionWithClass && isset($registeredClasses[get_class($taskObj)]) && $scheduler->isValidTaskObject($taskObj)) {
                    $taskTableRow['validClass'] = true;

                    $labels = [];

                    if ($task instanceof ProgressProviderInterface) {
                        $taskTableRow['progress'] = round((float)$taskObj->getProgress(), 2);
                    }
                    $taskTableRow['classTitle'] = $registeredClasses[$class]['title'];
                    $taskTableRow['classExtension'] = $registeredClasses[$class]['extension'];
                    $taskTableRow['additionalInformation'] = $taskObj->getAdditionalInformation();

                    if (!empty($task['serialized_executions'])) {
                        $labels[] = [
                            'class' => 'success',
                            'text' => $languageService->sL($lllFile . ':status.running'),
                        ];
                        $isRunning = true;
                        $taskTableRow['status'] = $languageService->sL($lllFile . ':status.running');
                    }

                    if (!$isRunning && $task['disable'] !== 1) {
                        $nextDate = date($dateFormat, (int)$task['nextexecution']);
                        if (empty($task['nextexecution'])) {
                            $nextDate = $languageService->sL($lllFile . ':none');
                        } elseif ($task['nextexecution'] < $GLOBALS['EXEC_TIME']) {
                            $labels[] = [
                                'class' => 'warning',
                                'text' => $languageService->sL($lllFile . ':status.late'),
                                'description' => $languageService->sL($lllFile . ':status.legend.scheduled'),
                            ];
                        }
                        $taskTableRow['nextExecution'] = $nextDate;
                    }

                    if ($taskObj->getType() === 1) {//AbstractTask::TYPE_SINGLE
                        $execType = $languageService->sL($lllFile . ':label.type.single');
                        $frequency = '-';
                    } else {
                        $execType = $languageService->sL($lllFile . ':label.type.recurring');
                        if ($taskObj->getExecution()->getCronCmd() == '') {
                            $frequency = $taskObj->getExecution()->getInterval();
                        } else {
                            $frequency = $taskObj->getExecution()->getCronCmd();
                        }
                    }

                    if ($task['disable'] && !$isRunning) {
                        $labels[] = [
                            'class' => 'default',
                            'text' => $languageService->sL($lllFile . ':status.disabled'),
                        ];
                        $showAsDisabled = true;
                    }

                    $taskTableRow['execType'] = $execType;
                    $taskTableRow['frequency'] = $frequency;

                    $multiple = $taskObj->getExecution()->getMultiple() ? 'yes' : 'no';
                    $taskTableRow['multiple'] = $languageService->sL($lllFile . ':' . $multiple);

                    if (!empty($task['lastexecution_failure'])) {
                        $exceptionArray = @unserialize($task['lastexecution_failure']);
                        if (!is_array($exceptionArray) || empty($exceptionArray)) {
                            $labelDescription = $languageService->sL($lllFile . ':' . 'msg.executionFailureDefault');
                        } else {
                            $labelDescription = ''; sprintf($languageService->sL($lllFile . ':' . 'msg.executionFailureReport'), $exceptionArray['code'], $exceptionArray['message']);
                        }
                        $labels[] = [
                            'class' => 'danger',
                            'text' => 'status.failure',
                            'description' => $labelDescription,
                        ];
                    }
                    $taskTableRow['labels'] = $labels;

                    if ($showAsDisabled) {
                        $taskTableRow['showAsDisabled'] = 'disabled';
                    }

                    $taskTable[] = $taskTableRow;
                }
            }
        }

        return $taskTable;
    }
}
