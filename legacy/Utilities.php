<?php

/**
 * This file is par of getQuestionInformation plugin
 * @license AGPL v3
 */

namespace getQuestionInformation;

Class Utilities
{
    /**
     * @var string current api version of this file
     */
    const API = "3.1.0";

    /**
     * @param integer $surveyId
     * @param string $extraFilters : one filter by line, colun and filter must be separated by :
     * @return array : column by key, value is value not managed
     */
    public static function getFinalOtherFilter($surveyId, $extraFilters)
    {
        $aFiltersFields = array();
        $extraFilters = trim($extraFilters);
        if($extraFilters == "") {
            return $aFiltersFields;
        }
        $aColumnToCode = \getQuestionInformation\helpers\surveyCodeHelper::getAllQuestions($surveyId);
        $aCodeToColumn = array_flip($aColumnToCode);
        $availableColumns = \SurveyDynamic::model($surveyId)->getAttributes();
        $aFieldsLines = preg_split('/\r\n|\r|\n/', $extraFilters, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($aFieldsLines as $aFieldLine) {
            if (!strpos($aFieldLine, ":")) {
                continue; // Invalid line
            }
            $key = substr($aFieldLine, 0, strpos($aFieldLine, ":"));
            $value = substr($aFieldLine, strpos($aFieldLine, ":")+1);
            if (array_key_exists($key, $availableColumns)) {
                $aFiltersFields[$key] = $value;
            } elseif(isset($aCodeToColumn[$key])) {
                $aFiltersFields[$aCodeToColumn[$key]] = $value;
            }
        }
        return $aFiltersFields;
    }
}
