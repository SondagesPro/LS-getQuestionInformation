<?php
/**
 * Some other plugin helpe
 *
 * @author Denis Chenu <denis@sondages.pro>
 * @copyright 2021 Denis Chenu <http://www.sondages.pro>
 * @license AGPL v3
 * @version 0.1.0
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */
namespace getQuestionInformation;

Class Utilities
{
    /**
     * @var float current api version of this file
     */
    const API = 0.2;

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
