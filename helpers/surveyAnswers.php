<?php

/**
 * An helper to return answer type and list for a survey
 *
 * @author Denis Chenu <denis@sondages.pro>
 * @copyright 2020-2022 Denis Chenu <http://www.sondages.pro>
 * @license AGPL v3
 * @version 1.13.3
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

namespace getQuestionInformation\helpers;

use Yii;
use PDO;
use Exception;
use Survey;
use CHtml;
use Question;
use Answer;

class surveyAnswers
{
    /**
     * The current api version of this file
     */
    public const apiversion = 4.0;

    /**
     * @var integer survey id
     */
    public $iSurvey;

    /**
     * @var string language
     */
    public $language;

    /**
     * constructor
     * @param integer survey id
     * @param string language
     * @throw error
     */
    public function __construct($iSurvey, $language = null)
    {
        /* Must import viewHelper */
        Yii::import('application.helpers.viewHelper');

        $this->iSurvey = $iSurvey;
        $oSurvey = Survey::model()->findByPk($this->iSurvey);
        if (!$oSurvey) {
            throw new \CHttpException(404);
        }
        if (!$language || !in_array($language, $oSurvey->getAllLanguages(), true)) {
            $language = $oSurvey->language;
        }
        $this->language = $language;
    }

    /**
     * Get an array with DB column name key and EM code for value or columns information for
     * @return null|array
     */
    public function allQuestionsAnswers()
    {
        // First get all question
        $allQuestions = $this->allQuestions();
        $aColumnsToCode = array();
        foreach ($allQuestions as $aQuestion) {
            $aColumnsToCode = array_merge($aColumnsToCode, $this->questionAnswers($aQuestion['qid']));
        }
        return $aColumnsToCode;
    }

    /**
     * Static shortcut to self::allQuestionsColumns
     * Get an array with DB column name key and EM code for value or columns information for
     * @param integer $iSurvey
     * @param string $language
     * @return null|array
     */
    public static function getAllQuestionsAnswers($iSurvey, $language = null)
    {
        $surveyColumnsInformation = new self($iSurvey, $language);
        return $surveyColumnsInformation->allQuestionsAnswers();
    }

    /**
     * The question list
     * @return \Question[]
     */
    private function allQuestions()
    {
        $questionTable = Question::model()->tableName();
        $command = Yii::app()->db->createCommand()
            ->select("qid,{{groups}}.group_order, {{questions}}.question_order")
            ->from($questionTable)
            ->where("({{questions}}.sid = :sid AND {{questions}}.parent_qid = 0)")
            ->join('{{groups}}', "{{groups}}.gid = {{questions}}.gid ")
            ->order("{{groups}}.group_order asc, {{questions}}.question_order asc")
            ->bindParam(":sid", $this->iSurvey, PDO::PARAM_INT);
        $allQuestions = $command->query()->readAll();
        return $allQuestions;
    }


    /**
     * static shortcut to questionColumns
     * @param integer $question id
     * @return array|null
     */
    public static function getQuestionAnswers($qid, $language = null)
    {
        $oQuestion = surveyQuestions::getQuestion($qid);
        if (!$oQuestion) {
            if (defined('YII_DEBUG') && YII_DEBUG) {
                throw new \Exception('Invalid question iQid in getQuestionColumnToCode function.');
            }
            return null;
        }
        $surveyColumnsInformation = new self($oQuestion->sid, $language);
        return $surveyColumnsInformation->questionAnswers($qid);
    }

    /**
     * return array  with DB column name key and EM code for value for one question
     * @param integer $qid
     * @throw Exception if debug
     * @return array|null
     */
    public function questionAnswers($qid)
    {
        $oQuestion = surveyQuestions::getQuestion($qid);
        if (!$oQuestion) {
            if (defined('YII_DEBUG') && YII_DEBUG) {
                throw new \Exception('Invalid question iQid in getQuestionColumnToCode function.');
            }
            return null;
        }
        if ($oQuestion->parent_qid) {
            if (defined('YII_DEBUG') && YII_DEBUG) {
                throw new \Exception('Invalid question iQid in getQuestionColumnToCode function. This function must be call only for parent question.');
            }
            return null;
        }
        $language = $this->language;
        $aColumnsInfo = array();
        $questionClass = Question::getQuestionClass($oQuestion->type);
        switch ($questionClass) {
            /* Single text */
            case 'text-short':
            case 'map':
            case 'equation':
            case 'text-long':
            case 'text-huge':
                $aColumnsInfo['Q' . $oQuestion->qid] = array(
                    'type' => 'freetext',
                );
                break;
            case 'choice-5-pt-radio':
            case 'list-radio':
            case 'list-with-comment':
            case 'list-dropdown':
            case 'yes-no':
            case 'gender':
            case 'language':
                $aColumnsInfo['Q' . $oQuestion->qid] = array(
                    'type' => 'answer',
                    'answers' => $this->getAnswers($oQuestion, $language),
                );
                if ($oQuestion->type == "O") {
                    $aColumnsInfo['Q' . $oQuestion->qid . '_Ccomment'] = array(
                        'type' => 'freetext',
                    );
                }
                break;
            case 'array-5-pt':
            case 'array-10-pt':
            case 'array-yes-uncertain-no':
            case 'array-increase-same-decrease':
            case 'array-flexible-row':
            case 'array-flexible-column':
                $oSubQuestions = surveyQuestions::getSubQuestions($oQuestion->qid);
                if ($oSubQuestions) {
                    foreach ($oSubQuestions as $oSubQuestion) {
                        $aColumnsInfo['Q' . $oQuestion->qid . '_S' . $oSubQuestion->qid] = array(
                            'type' => 'answer',
                            'answers' => $this->getAnswers($oQuestion, $language),
                        );
                    }
                }
                break;
            case 'array-flexible-duel-scale':
            case 'array-flexible-dual-scale':
                $oSubQuestions = surveyQuestions::getSubQuestions($oQuestion->qid);
                if ($oSubQuestions) {
                    foreach ($oSubQuestions as $oSubQuestion) {
                        $aColumnsInfo['Q' . $oQuestion->qid . '_S' . $oSubQuestion->qid . '#0'] = array(
                            'type' => 'answer',
                            'answers' => $this->getAnswers($oQuestion, $language),
                        );
                        $aColumnsInfo['Q' . $oQuestion->qid . '_S' . $oSubQuestion->qid . '#1'] = array(
                            'type' => 'answer',
                            'answers' => $this->getAnswers($oQuestion, $language, 1),
                        );
                    }
                }
                break;
            case 'numeric':
                $aColumnsInfo['Q' . $oQuestion->qid] = array(
                    'type' => 'decimal',
                );
                break;
            case 'numeric-multi':
                $oSubQuestions = surveyQuestions::getSubQuestions($oQuestion->qid);
                if ($oSubQuestions) {
                    foreach ($oSubQuestions as $oSubQuestion) {
                        $aColumnsInfo['Q' . $oQuestion->qid . '_S' . $oSubQuestion->qid] = array(
                            'type' => 'decimal',
                        );
                    }
                }
                break;
            case 'multiple-opt':
            case 'multiple-opt-comments':
                $oSubQuestions = surveyQuestions::getSubQuestions($oQuestion->qid);
                if ($oSubQuestions) {
                    foreach ($oSubQuestions as $oSubQuestion) {
                        $aColumnsInfo['Q' . $oQuestion->qid . '_S' . $oSubQuestion->qid] = array(
                            'type' => 'checkbox',
                            'answers' => $this->getAnswers($oQuestion, $language),
                        );
                        if ($questionClass == 'multiple-opt-comments') {
                            $aColumnsInfo['Q' . $oQuestion->qid . '_S' . $oSubQuestion->qid . '_Ccomment'] = array(
                                'type' => 'freetext'
                            );
                        }
                    }
                }
                break;
            case 'array-multi-flexi':
            case 'array-multi-flexi-text':
                $oSubQuestionsY = surveyQuestions::getSubQuestions($oQuestion->qid, 0);
                if ($oSubQuestionsY) {
                    foreach ($oSubQuestionsY as $oSubQuestionY) {
                        $oSubQuestionsX = surveyQuestions::getSubQuestions($oQuestion->qid, 1);
                        if ($oSubQuestionsX) {
                            foreach ($oSubQuestionsX as $oSubQuestionX) {
                                if ($questionClass == 'array-multi-flexi-text') {
                                    $aColumnsInfo['Q' . $oQuestion->qid . '_S' . $oSubQuestionY->qid . '_S' . $oSubQuestionX->qid] = array(
                                        'type' => 'freetext',
                                    );
                                } else {
                                    $aColumnsInfo['Q' . $oQuestion->qid . '_S' . $oSubQuestionY->qid . '_S' . $oSubQuestionX->qid] = array(
                                        'type' => 'float',
                                    );
                                }
                            }
                        }
                    }
                }
                break;
            case 'multiple-short-txt':
                $oSubQuestions = surveyQuestions::getSubQuestions($oQuestion->qid);
                if ($oSubQuestions) {
                    foreach ($oSubQuestions as $oSubQuestion) {
                        $aColumnsInfo['Q' . $oQuestion->qid . '_S' . $oSubQuestion->qid] = array(
                            'type' => 'freetext',
                        );
                    }
                }
                break;
            case 'date':
                $aColumnsInfo['Q' . $oQuestion->qid] = array(
                    'type' => 'datetime',
                );
                break;
            case 'ranking':
                if (surveyCodeHelper::isRankingJson()) {
                    $aColumnsInfo['Q' . $oQuestion->qid] = array(
                        'type' => 'ranking',
                        'answers' => $this->getAnswers($oQuestion, $language),
                    );
                    break;
                }
                $maxAnswers = intval(surveyQuestions::getAttribute($oQuestion->qid, 'max_subquestions'));
                if (empty($maxAnswers)) {
                    $maxAnswers = count(surveyQuestions::getSubQuestions($oQuestion->qid));
                }
                $oSubQuestions = surveyQuestions::getSubQuestions($oQuestion->qid);
                if ($oSubQuestions) {
                    $count = 1;
                    foreach ($oSubQuestions as $oSubQuestion) {
                        $aColumnsInfo['Q' . $oQuestion->qid . '_S' . $oSubQuestion->qid] = array(
                            'type' => 'answer',
                            'answers' => $this->getAnswers($oQuestion, $language),
                        );
                        if ($count >= $maxAnswers) {
                            break;
                        }
                        $count++;
                    }
                }
                break;
            case 'upload-files':
                $aColumnsInfo['Q' . $oQuestion->qid] = array(
                    'type' => 'upload',
                );
                $aColumnsInfo['Q' . $oQuestion->qid . '_Cfilecount'] = array(
                    'type' => 'float',
                );
                break;
            case 'boilerplate':
                /* Don't show it*/
                break;
            default:
                // Nothing to to do : throw error ?
        }
        if (self::allowOther($oQuestion->type) and $oQuestion->other == "Y") {
            $aColumnsInfo['Q' . $oQuestion->qid . '_Cother'] = array(
                'type' => "freetext",
            );
            if ($oQuestion->type == "P") { /* Specific with comment … */
                $aColumnsInfo['Q' . $oQuestion->qid . '_Cothercomment'] = array(
                    'type' => "freetext",
                );
            }
        }
        if (surveyCodeHelper::hasOtherChecked($oQuestion)) {
            $aColumnsInfo['Q' . $oQuestion->qid . '_Cotherchecked'] = array(
                'type' => 'checkbox',
                'answers' => $this->getAnswers($oQuestion, $language),
            );
        }
        return $aColumnsInfo;
    }

    /**
     * @param string $type
     * return boolean
     */
    public static function allowOther($type)
    {
        $allowOther = array("L","!","P","M");
        return in_array($type, $allowOther);
    }

    /**
     * Get answer list in language, key are code
     * @param \Question
     * @param string $language
     * @param integer $scale
     * @param boolean striped;
     * return array|false|null
     */
    public static function getAnswers($oQuestion, $language = null, $scale = 0, $strip = true)
    {
        /* @var array[] answers by question, language, scale and strip : same answers for each subquestion */
        static $aAnswersCache = [];
        $key = $oQuestion->qid . "|" . (empty($language) ? App()->getLanguage() : $language) . "|" . $scale . "|" . intval($strip);
        if (!array_key_exists($key, $aAnswersCache)) {
            $aAnswersCache[$key] = self::findAnswers($oQuestion, $language, $scale, $strip);
        }
        return $aAnswersCache[$key];
    }

    /**
     * @see self::getAnswers
     * @param \Question
     * @param string $language
     * @param integer $scale
     * @param boolean $strip
     * return array|false|null
     */
    private static function findAnswers($oQuestion, $language, $scale, $strip)
    {
        Yii::import('application.helpers.viewHelper');
        $oSurvey = Survey::model()->findByPk($oQuestion->sid);
        if (!$oSurvey) {
            throw new \CHttpException(404);
        }
        if (empty($language)) {
            $language = App()->getLanguage();
        }
        if (!in_array($language, $oSurvey->getAllLanguages())) {
            $language = $oSurvey->language;
        }
        $questionClass = Question::getQuestionClass($oQuestion->type);
        switch ($questionClass) {
            case 'list-radio':
            case 'list-with-comment':
            case 'list-dropdown':
            case 'array-flexible-row':
            case 'array-flexible-column':
            case 'array-flexible-duel-scale':
            case 'array-flexible-dual-scale':
                $answers = Answer::model()->resetScope()->with('answerl10ns')->findAll(array(
                    'condition' => "qid=:qid and scale_id=:scale and language=:language",
                    'order' => 'sortorder, code',
                    'params' => array(":qid" => $oQuestion->qid,":scale" => $scale, ":language" => $language )
                ));
                $aAnswers = array();
                if (!empty($answers)) {
                    $aAnswers = CHtml::listData($answers, 'code', function ($answer) use ($strip, $language) {
                        if (empty($answer->answerl10ns[$language])) {
                            return null;
                        }
                        $answertext = $answer->answerl10ns[$language]->answer;
                        if ($strip) {
                            return textHelper::flatten($answertext);
                        }
                        return textHelper::purified($answertext);
                    });
                }
                $aAnswers = array_filter($aAnswers, fn($answer) => $answer !== null); /* Remove invalid answers (no l10n) */
                if (self::allowOther($oQuestion->type) && $oQuestion->other == "Y") {
                    $aAnswers['-oth-'] = gT('Other');
                }
                return $aAnswers;
                break;
            case 'ranking':
                $answers = Question::model()->resetScope()->with('questionl10ns')->findAll(
                    array(
                    'condition' => "sid=:sid and parent_qid=:qid and language=:language",
                    'order' => 'question_order asc',
                    'params' => array(":sid" => $oQuestion->sid, ":qid" => $oQuestion->qid, ":language" => $language),
                    )
                );
                $aAnswers = array();
                if (!empty($answers)) {
                    $aAnswers = CHtml::listData(
                        $answers,
                        'title',
                        function ($answer) use ($strip, $language) {
                            if (empty($answer->questionl10ns[$language])) {
                                return null;
                            }
                            $answertext = $answer->questionl10ns[$language]->question;
                            if ($strip) {
                                return textHelper::flatten($answertext);
                            }
                            return textHelper::purified($answertext);
                        }
                    );
                }
                $aAnswers = array_filter($aAnswers, fn($answer) => $answer !== null); /* Remove invalid answers (no l10n) */
                return $aAnswers;
            case 'choice-5-pt-radio':
            case 'array-5-pt':
                return [1 => 1,2 => 2,3 => 3,4 => 4,5 => 5];
                break;
            case 'array-10-pt':
                return [1 => 1,2 => 2,3 => 3,4 => 4,5 => 5,6 => 6,7 => 7,8 => 8,9 => 9,10 => 10];
            case 'yes-no':
                return array(
                    "Y" => gT("Yes"),
                    "N" => gT("No"),
                );
            case 'array-yes-uncertain-no':
                return array(
                    "Y" => gT("Yes"),
                    "N" => gT("No"),
                    "U" => gT("Uncertain"),
                );
            case 'array-increase-same-decrease':
                return array(
                    "I" => gT("Increase"),
                    "S" => gT("Same"),
                    "D" => gT("Decrease"),
                );
            case 'gender':
                return array(
                    "M" => gT("Male"),
                    "F" => gT("Female"),
                );
            case 'language':
                return self::getRestricedLanguage($oQuestion->sid);
            case 'multiple-opt':
            case 'multiple-opt-comments':
                return array(
                    "Y" => gT("Yes"),
                );
            case 'date':
                return false;
            default:
                return null;
        }
    }


    public static function getRestricedLanguage($surveyId)
    {
        $aLanguages = Survey::model()->findByPk($surveyId)->getAllLanguages();
        $aFilterLanguages = array();
        foreach ($aLanguages as $language) {
            $aFilterLanguages[$language] = getLanguageNameFromCode($language, false, App()->getLanguage());
        }
        return $aFilterLanguages;
    }

    /**
     * get value of an attribute
     * @param integer $qid
     * @param $name
     * return null|string
     */
    private static function getAttribute($qid, $name)
    {
        return surveyQuestions::getAttribute($qid, $name);
    }
}
