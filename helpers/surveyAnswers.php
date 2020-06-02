<?php
/**
 * An helper to return answer type and list for a survey
 *
 * @author Denis Chenu <denis@sondages.pro>
 * @copyright 2020 Denis Chenu <http://www.sondages.pro>
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
namespace getQuestionInformation\helpers;

use Yii;
use PDO;
use Exception;
use Survey;

use viewHelper;
use CHtml;
use Question;
use QuestionAttribute;
use Answer;

class surveyAnswers
{
    /**
     * The current api version of this file
     */
    const apiversion=1;

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
     * @param array options
     * @throw error
     */
    public function __construct($iSurvey, $language = null, $options = array())
    {
        /* Must import viewHelper */
        Yii::import('application.helpers.viewHelper');

        $this->iSurvey = $iSurvey;
        $oSurvey = Survey::model()->findByPk($this->iSurvey);
        if (!$oSurvey) {
            throw new \CHttpException(404);
        }
        if (!$language || !in_array($language, $oSurvey->getAllLanguages())) {
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
            ->select("qid,{{questions}}.language as language,{{groups}}.group_order, {{questions}}.question_order")
            ->from($questionTable)
            ->where("({{questions}}.sid = :sid AND {{questions}}.language = :language AND {{questions}}.parent_qid = 0)")
            ->join('{{groups}}', "{{groups}}.gid = {{questions}}.gid  AND {{questions}}.language = {{groups}}.language")
            ->order("{{groups}}.group_order asc, {{questions}}.question_order asc")
            ->bindParam(":sid", $this->iSurvey, PDO::PARAM_INT)
            ->bindParam(":language", $this->language, PDO::PARAM_STR);
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
        $oQuestion = Question::model()->find("qid=:qid", array(":qid"=>$qid));
        if (!$oQuestion) {
            if (defined('YII_DEBUG') && YII_DEBUG) {
                throw new Exception('Invalid question iQid in getQuestionColumnToCode function.');
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
        $oQuestion = Question::model()->find("qid=:qid AND language=:language", array(":qid"=>$qid,":language"=>$this->language));
        if (!$oQuestion) {
            if (defined('YII_DEBUG') && YII_DEBUG) {
                throw new Exception('Invalid question iQid in getQuestionColumnToCode function.');
            }
            return null;
        }
        if ($oQuestion->parent_qid) {
            if (defined('YII_DEBUG') && YII_DEBUG) {
                throw new Exception('Invalid question iQid in getQuestionColumnToCode function. This function must be call only for parent question.');
            }
            return null;
        }
        $language = $oQuestion->language;
        $aColumnsInfo = array();
        $questionClass= Question::getQuestionClass($oQuestion->type);
        switch ($questionClass) {
            /* Single text */
            case 'text-short':
            case 'equation':
            case 'text-long':
            case 'text-huge':
                $aColumnsInfo[$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid] = array(
                    'type'=>'freetext',
                );
                break;
            case 'choice-5-pt-radio':
            case 'list-radio':
            case 'list-with-comment':
            case 'list-dropdown':
            case 'yes-no':
            case 'gender':
            case 'language':
                $aColumnsInfo[$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid] = array(
                    'type' => 'answer',
                    'answers'=>$this->getAnswers($oQuestion),
                );
                if ($oQuestion->type == "O") {
                    $aColumnsInfo[$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid."comment"] = array(
                        'type'=>'freetext',
                    );
                }
                break;
            case 'array-5-pt':
            case 'array-10-pt':
            case 'array-yes-uncertain-no':
            case 'array-increase-same-decrease':
            case 'array-flexible-row':
            case 'array-flexible-column':
                $oSubQuestions = Question::model()->findAll(array(
                    'select'=>'title,question',
                    'condition'=>"sid=:sid and language=:language and parent_qid=:qid",
                    'order'=>'question_order asc',
                    'params'=>array(":sid"=>$oQuestion->sid,":language"=>$language,":qid"=>$oQuestion->qid),
                ));
                if ($oSubQuestions) {
                    foreach ($oSubQuestions as $oSubQuestion) {
                        $aColumnsInfo[$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid.$oSubQuestion->title] = array(
                            'type' => 'answer',
                            'answers'=>$this->getAnswers($oQuestion),
                        );
                    }
                }
                break;
            case 'array-flexible-duel-scale':
                $oSubQuestions = Question::model()->findAll(array(
                    'select'=>'title,question',
                    'condition'=>"sid=:sid and language=:language and parent_qid=:qid",
                    'order'=>'question_order asc',
                    'params'=>array(":sid"=>$oQuestion->sid,":language"=>$language,":qid"=>$oQuestion->qid),
                ));
                if ($oSubQuestions) {
                    foreach ($oSubQuestions as $oSubQuestion) {
                        $aColumnsInfo[$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid.$oSubQuestion->title."#0"] = array(
                            'type' => 'answer',
                            'answers'=>$this->getAnswers($oQuestion),
                        );
                        $aColumnsInfo[$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid.$oSubQuestion->title."#1"]=array(
                            'type'=>'answer',
                            'answers'=>$this->getAnswers($oQuestion,1),
                        );
                    }
                }
                break;
            case 'numeric':
                $aColumnsInfo[$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid] = array(
                    'type' => 'decimal',
                );
                break;
            case 'numeric-multi':
                $oSubQuestions = Question::model()->findAll(array(
                    'select'=>'title,question',
                    'condition'=>"sid=:sid and language=:language and parent_qid=:qid",
                    'order'=>'question_order asc',
                    'params'=>array(":sid"=>$oQuestion->sid,":language"=>$language,":qid"=>$oQuestion->qid),
                ));
                if ($oSubQuestions) {
                    foreach ($oSubQuestions as $oSubQuestion) {
                        $aColumnsInfo[$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid.$oSubQuestion->title] = array(
                            'type' => 'decimal',
                        );
                    }
                }
                break;
            case 'multiple-opt':
            case 'multiple-opt-comments':
                $oSubQuestions = Question::model()->findAll(array(
                    'select'=>'title,question',
                    'condition'=>"sid=:sid and language=:language and parent_qid=:qid",
                    'order'=>'question_order asc',
                    'params'=>array(":sid"=>$oQuestion->sid,":language"=>$language,":qid"=>$oQuestion->qid),
                ));
                if ($oSubQuestions) {
                    foreach ($oSubQuestions as $oSubQuestion) {
                        $aColumnsInfo[$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid.$oSubQuestion->title] = array(
                            'type' => 'checkbox',
                            'answers'=>$this->getAnswers($oQuestion),
                        );
                        if ($questionClass=='multiple-opt-comments') {
                            $aColumnsInfo[$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid.$oSubQuestion->title.'comment'] = array(
                                'type' => 'freetext'
                            );
                        }
                    }
                }
                break;
            case 'array-multi-flexi':
            case 'array-multi-flexi-text':
                $oSubQuestionsY = Question::model()->findAll(array(
                    'select'=>'title,question',
                    'condition'=>"sid=:sid and language=:language and parent_qid=:qid and scale_id=0",
                    'order'=>'question_order asc',
                    'params'=>array(":sid"=>$oQuestion->sid,":language"=>$language,":qid"=>$oQuestion->qid),
                ));
                if ($oSubQuestionsY) {
                    foreach ($oSubQuestionsY as $oSubQuestionY) {
                        $oSubQuestionsX = Question::model()->findAll(array(
                            'select'=>'title,question',
                            'condition'=>"sid=:sid and language=:language and parent_qid=:qid and scale_id=1",
                            'order'=>'question_order asc',
                            'params'=>array(":sid"=>$oQuestion->sid,":language"=>$language,":qid"=>$oQuestion->qid),
                        ));
                        if ($oSubQuestionsX) {
                            foreach ($oSubQuestionsX as $oSubQuestionX) {
                                if ($questionClass == 'array-multi-flexi-text') {
                                    $aColumnsInfo[$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid.$oSubQuestionY->title."_".$oSubQuestionX->title] = array(
                                        'type'=>'freetext',
                                    );
                                } else {
                                    $aColumnsInfo[$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid.$oSubQuestionY->title."_".$oSubQuestionX->title] = array(
                                        'type'=>'float',
                                    );
                                }
                            }
                        }
                    }
                }
                break;
            case 'multiple-short-txt':
                $oSubQuestions = Question::model()->findAll(array(
                    'select'=>'title,question',
                    'condition'=>"sid=:sid and language=:language and parent_qid=:qid",
                    'order'=>'question_order asc',
                    'params'=>array(":sid"=>$oQuestion->sid,":language"=>$language,":qid"=>$oQuestion->qid),
                ));
                if ($oSubQuestions) {
                    foreach ($oSubQuestions as $oSubQuestion) {
                        $aColumnsInfo[$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid.$oSubQuestion->title] = array(
                            'type'=>'freetext',
                        );
                    }
                }
                break;
            case 'date':
                $aColumnsInfo[$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid] = array(
                    'type' => 'datetime',
                );
                break;
            case 'ranking':
                $oQuestionAttribute = \QuestionAttribute::model()->find(
                    "qid = :qid AND attribute = 'max_subquestions'",
                    array(':qid' => $oQuestion->qid)
                );
                if ($oQuestionAttribute) {
                    $maxAnswers = intval($oQuestionAttribute->value);
                }
                if (empty($maxAnswers)) {
                    $maxAnswers = intval(Answer::model()->count(
                        "qid=:qid and language=:language",
                        array(":qid"=>$oQuestion->qid,":language"=>$oQuestion->language)
                    ));
                }
                for ($count = 1; $count <= $maxAnswers; $count++) {
                    $header = "<strong>[{$oQuestion->title}_{$count}]</strong>"
                            . self::getExtraHtmlHeader($oQuestion)
                            . "<small>".sprintf(gT("Rank %s"), $count)."</small>";
                    $aColumnsInfo[$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid.$count] = array(
                        'type' => 'answer',
                        'filter'=> $this->getAnswers($oQuestion),
                    );
                    $aColumnsToCode[$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid.$count]=$oQuestion->title."_".$count;
                }
                break;
            case 'upload-files':
                $aColumnsInfo[$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid] = array(
                    'type'=>'upload',
                );
                $aColumnsInfo[$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid."_filecount"] = array(
                    'type'=>'float',
                );
                break;
            case 'boilerplate':
                /* Don't show it*/
                break;
            default:
                // Nothing to to do : throw error ?
        }
        if (self::allowOther($oQuestion->type) and $oQuestion->other=="Y") {
            $aColumnsInfo[$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid."other"] = array(
                'type' => "freetext",
            );
            if ($oQuestion->type == "P") { /* Specific with comment … */
                $aColumnsInfo[$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid."othercomment"] = array(
                    'type' => "freetext",
                );
            }
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
     * @param integer $scale
     * @param boolean striped;
     * return array|false|null
     */
    public static function getAnswers($oQuestion, $scale = 0, $strip = true)
    {
        $questionClass= Question::getQuestionClass($oQuestion->type);
        switch ($questionClass) {
            case 'list-radio':
            case 'list-with-comment':
            case 'list-dropdown':
            case 'array-flexible-row':
            case 'array-flexible-column':
            case 'array-flexible-duel-scale':
            case 'ranking':
                $answers = Answer::model()->findAll(array(
                    'condition' => "qid=:qid and language=:language and scale_id=:scale",
                    'order'=> 'sortorder',
                    'params' => array(":qid"=>$oQuestion->qid,":language"=>$oQuestion->language,":scale"=>$scale)
                ));
                if (!empty($answers)) {
                    return CHtml::listData($answers, 'code', function ($answers) use ($strip) {
                        if ($strip) {
                            return strip_tags(viewHelper::purified($answers->answer));
                        }
                        return viewHelper::purified($answers->answer);
                    });
                }
                if (self::allowOther($oQuestion->type) && $oQuestion->other=="Y") {
                    $aAnswers['-oth']=gT('Other');
                }
                break;
            case 'choice-5-pt-radio':
            case 'array-5-pt':
                return [1=>1,2=>2,3=>3,4=>4,5=>5];
                break;
            case 'array-10-pt':
                return [1=>1,2=>2,3=>3,4=>4,5=>5,6=>6,7=>7,8=>8,9=>9,10=>10];
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
        $AttributeCriteria = new \CDbCriteria;
        $AttributeCriteria->select = 'value';
        $AttributeCriteria->compare('qid', $qid);
        $AttributeCriteria->compare('attribute', $name);
        $oAttribute = QuestionAttribute::model()->find($AttributeCriteria);
        if (empty($oAttribute)) {
            return null;
        }
        return $oAttribute->value;
    }
}
