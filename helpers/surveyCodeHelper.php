<?php

/**
 * This file is par of getQuestionInformation plugin
 * @since 3.1.0 : function getColumnName
 * @license AGPL v3
 */

namespace getQuestionInformation\helpers;

use Yii;
use PDO;
use Survey;
use Question;
use QuestionAttribute;
use Answer;
use Response;

class surveyCodeHelper
{
    /**
     * The current api version of this file
     */
    const apiversion = 1.2;

    /* Set usage in static : all system use DB call a lot, need static */
    /* null|array[] getAllQuestions function result */
    private static $aAllQuestionsColumnsToCode = null;
    /* null|array[] getQuestionColumn specific static */
    private static $aQuestionsColumn = null;

    /**
    /* Get an array with DB column name key and EM code for value or columns information
     * @param integer $iSurvey
     * @param null $unused
     * @param boolean $default column (id, submitdate … )
     * @return null|array
     */
    public static function getAllQuestions($iSurvey, $unused = null, $default = false)
    {
        if (self::$aAllQuestionsColumnsToCode == null) {
            self::$aAllQuestionsColumnsToCode = [];
        }
        // Check survey
        $oSurvey = Survey::model()->findByPk($iSurvey);
        if (!$oSurvey) {
            return null;
        }
        if (!isset(self::$aAllQuestionsColumnsToCode[$iSurvey])) {
            $questionTable = Question::model()->tableName();
            $command = Yii::app()->db->createCommand()
                ->select("{{questions}}.qid,{{groups}}.group_order, {{questions}}.question_order")
                ->from($questionTable)
                ->where("({{questions}}.sid = :sid AND {{questions}}.parent_qid = 0)")
                ->join('{{question_l10ns}}', "{{question_l10ns}}.qid = {{questions}}.qid")
                ->join('{{groups}}', "{{groups}}.gid = {{questions}}.gid")
                ->order("{{groups}}.group_order asc, {{questions}}.question_order asc")
                ->bindParam(":sid", $iSurvey, PDO::PARAM_INT);
            $allQuestions = $command->query()->readAll();
            $aQuestionsColumnsToCode = array();
            foreach ($allQuestions as $aQuestion) {
                $aQuestionsColumnsToCode = array_merge(
                    $aQuestionsColumnsToCode,
                    self::getQuestionColumn($aQuestion['qid'])
                );
            }
            self::$aAllQuestionsColumnsToCode[$iSurvey] = $aQuestionsColumnsToCode;
        } else {
            $aQuestionsColumnsToCode = self::$aAllQuestionsColumnsToCode[$iSurvey];
        }
        $aColumnsToCode = array();
        if ($default) {
            $aColumnsToCode['id'] = 'id';
            $aColumnsToCode['submitdate'] = 'submitdate';
            $aColumnsToCode['lastpage'] = 'lastpage';
            $aColumnsToCode['startlanguage'] = 'startlanguage';
            if (Yii::app()->getConfig('DBVersion') >= 290) {
                $aColumnsToCode['seed'] = 'seed';
            }
            if ($oSurvey->anonymized != "Y") {
                $aColumnsToCode['token'] = 'token';
            }
            if ($oSurvey->datestamp == "Y") {
                $aColumnsToCode['startdate'] = 'startdate';
                $aColumnsToCode['datestamp'] = 'datestamp';
            }
            if ($oSurvey->ipaddr == "Y") {
                $aColumnsToCode['ipaddr'] = 'ipaddr';
            }
            if ($oSurvey->refurl == "Y") {
                $aColumnsToCode['refurl'] = 'refurl';
            }
        }
        $aColumnsToCode = array_merge(
            $aColumnsToCode,
            $aQuestionsColumnsToCode
        );
        return $aColumnsToCode;
    }

    /**
     * return array  with DB column name key and EM code for value for one question
     * @param integer $qid
     * @throw Exception if debug
     * @return array|null
     */
    public static function getQuestionColumn($qid)
    {
        /* static part */
        if (self::$aQuestionsColumn == null) {
            self::$aQuestionsColumn = [];
        }
        if (isset(self::$aQuestionsColumn[$qid])) {
            return self::$aQuestionsColumn[$qid];
        }

        $oQuestion = Question::model()->find("qid=:qid", array(":qid" => $qid));
        if (!$oQuestion) {
            if (Yii::app()->getConfig('debug') >= 2) {
                throw new \Exception('Invalid question iQid in getQuestionColumnToCode function.');
            }
            return null;
        }
        if ($oQuestion->parent_qid) {
            if (Yii::app()->getConfig('debug') >= 2) {
                throw new Exception('Invalid question iQid in getQuestionColumnToCode function. This function must be call only for parent question.');
            }
            return null;
        }
        $aColumnsToCode = array();
        switch (self::getTypeFromType($oQuestion->type)) {
            case 'single':
                $aColumnsToCode[$oQuestion->sid . "X" . $oQuestion->gid . 'X' . $oQuestion->qid] = $oQuestion->title;
                $aCurrent[$oQuestion->sid . "X" . $oQuestion->gid . 'X' . $oQuestion->qid] = $oQuestion->title;
                break;
            case 'dual':
                $oSubQuestions = Question::model()->findAll(array(
                    'select' => 'title,question_order',
                    'condition' => "sid=:sid and parent_qid=:qid",
                    'order' => 'question_order asc',
                    'params' => array(":sid" => $oQuestion->sid, ":qid" => $oQuestion->qid),
                ));
                if ($oSubQuestions) {
                    foreach ($oSubQuestions as $oSubQuestion) {
                        $aColumnsToCode[$oQuestion->sid . "X" . $oQuestion->gid . 'X' . $oQuestion->qid . $oSubQuestion->title . "#0"] = $oQuestion->title . "_" . $oSubQuestion->title . "_0";
                        $aColumnsToCode[$oQuestion->sid . "X" . $oQuestion->gid . 'X' . $oQuestion->qid . $oSubQuestion->title . "#1"] = $oQuestion->title . "_" . $oSubQuestion->title . "_1";
                    }
                }
                break;
            case 'sub':
                $oSubQuestions = Question::model()->findAll(array(
                    'select' => 'title,question_order',
                    'condition' => "sid=:sid and parent_qid=:qid",
                    'order' => 'question_order asc',
                    'params' => array(":sid" => $oQuestion->sid, ":qid" => $oQuestion->qid),
                ));
                if ($oSubQuestions) {
                    foreach ($oSubQuestions as $oSubQuestion) {
                        $aColumnsToCode[$oQuestion->sid . "X" . $oQuestion->gid . 'X' . $oQuestion->qid . $oSubQuestion->title] = $oQuestion->title . "_" . $oSubQuestion->title;
                    }
                }
                break;
            case 'ranking':
                $oQuestionAttribute = QuestionAttribute::model()->find(
                    "qid = :qid AND attribute = 'max_subquestions'",
                    array(':qid' => $oQuestion->qid)
                );
                if ($oQuestionAttribute) {
                    $maxAnswers = intval($oQuestionAttribute->value);
                }
                if (empty($maxAnswers)) {
                    $maxAnswers = intval(Answer::model()->count(
                        "qid=:qid",
                        array(":qid" => $oQuestion->qid)
                    ));
                }
                for ($count = 1; $count <= $maxAnswers; $count++) {
                    $aColumnsToCode[$oQuestion->sid . "X" . $oQuestion->gid . 'X' . $oQuestion->qid . $count] = $oQuestion->title . "_" . $count;
                }
                break;
            case 'upload':
                $aColumnsToCode[$oQuestion->sid . "X" . $oQuestion->gid . 'X' . $oQuestion->qid] = $oQuestion->title;
                $aColumnsToCode[$oQuestion->sid . "X" . $oQuestion->gid . 'X' . $oQuestion->qid . "_filecount"] = $oQuestion->title . "_filecount";
                break;
            case 'double':
                $oSubQuestionsY = Question::model()->findAll(array(
                    'select' => 'title,question_order',
                    'condition' => "sid=:sid and parent_qid=:qid and scale_id=0",
                    'order' => 'question_order asc',
                    'params' => array(":sid" => $oQuestion->sid, ":qid" => $oQuestion->qid),
                ));
                if ($oSubQuestionsY) {
                    foreach ($oSubQuestionsY as $oSubQuestionY) {
                        $oSubQuestionsX = Question::model()->findAll(array(
                            'select' => 'title,question_order',
                            'condition' => "sid=:sid and parent_qid=:qid and scale_id=1",
                            'order' => 'question_order asc',
                            'params' => array(":sid" => $oQuestion->sid, ":qid" => $oQuestion->qid),
                        ));
                        if ($oSubQuestionsX) {
                            foreach ($oSubQuestionsX as $oSubQuestionX) {
                                $aColumnsToCode[$oQuestion->sid . "X" . $oQuestion->gid . 'X' . $oQuestion->qid . $oSubQuestionY->title . "_" . $oSubQuestionX->title] = $oQuestion->title . "_" . $oSubQuestionY->title . "_" . $oSubQuestionX->title;
                            }
                        }
                    }
                }
                break;
            case 'none':
                // Nothing needed
                break;
            default:
                // NUll
                if (Yii::app()->getConfig('debug') >= 2) {
                    throw new Exception(sprintf('Unknow question type %s.', $oQuestion->type));
                }
        }
        if (self::allowOther($oQuestion->type) and $oQuestion->other == "Y") {
            $aColumnsToCode[$oQuestion->sid . "X" . $oQuestion->gid . 'X' . $oQuestion->qid . "other"] = $oQuestion->title . "_other";
        }
        if ($oQuestion->type == 'P') {
            $aCommentColumns = array();
            foreach ($aColumnsToCode as $column => $code) {
                $aCommentColumns[$column . "comment"] = $code . "comment";
            }
            $aColumnsToCode = array_merge($aColumnsToCode, $aCommentColumns);
        }
        if ($oQuestion->type == 'O') {
            $aColumnsToCode[$oQuestion->sid . "X" . $oQuestion->gid . 'X' . $oQuestion->qid . "comment"] = $oQuestion->title . "_comment";
        }
        self::$aQuestionsColumn[$qid] = $aColumnsToCode;
        return $aColumnsToCode;
    }
    /**
     * retunr the global type by question type
     * @param string $type
     * @return string|null
     */
    public static function getTypeFromType($type)
    {
        $questionTypeByType = array(
            "1" => 'dual',
            "5" => 'single',
            "A" => 'sub',
            "B" => 'sub',
            "C" => 'sub',
            "D" => 'single',
            "E" => 'sub',
            "F" => 'sub',
            "G" => 'single',
            "H" => 'sub',
            "I" => 'single',
            "K" => 'sub',
            "L" => 'single',
            "M" => 'sub',
            "N" => 'single',
            "O" => 'single',
            "K" => 'sub',
            "P" => 'sub',
            "Q" => 'sub',
            "R" => 'ranking',
            "S" => 'single',
            "T" => 'single',
            "U" => 'single',
            "Y" => 'single',
            "!" => 'single',
            ":" => 'double',
            ";" => 'double',
            "|" => 'upload',
            "*" => 'single',
            "X" => 'none', // 'single' can work too
        );
        if (!isset($questionTypeByType[$type])) {
            return null;
        }
        return $questionTypeByType[$type];
    }

    /**
     * @param string $type
     * return boolean
     */
    public static function haveComment($type)
    {
        $haveComment = array('O','P');
        return in_array($type, $haveComment);
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
     * Get the column name from expression manager code
     * Except for comment colum
     * @param integer $surveyId
     * @param string $code
     * @return string|null
     */
    public static function getColumnName($surveyId, $code)
    {
        if (tableExists('survey_' . $surveyId)) {
            $availableColumns = Response::model($surveyId)->getAttributes();
            if (array_key_exists($code, $availableColumns)) {
                return $code;
            }
        }
        $aCode = explode("_", $code);
        $oQuestion = Question::model()->find([
            'select' => ['sid', 'qid', 'title'],
            'condition' => 'sid = :sid AND title = :title',
            'params' => [':sid' => $surveyId, ':title' => $aCode[0]]
        ]);
        if (is_null($oQuestion)) {
            return null;
        }
        $questionColumns = array_flip(self::getQuestionColumn($oQuestion->qid));
        if (isset($questionColumns[$code])) {
            return $questionColumns[$code];
        }
        if (!tableExists('survey_' . $surveyId)) {
            $allQuestionColumns = self::getAllQuestions($surveyId, null, true);
            /* core or real SGQA */
            if (isset($allQuestionColumns[$code])) {
                return $code;
            }
            $allQuestionColumns = array_flip($allQuestionColumns);
            /* code to SGQA */
            if (isset($allQuestionColumns[$code])) {
                return $allQuestionColumns[$code];
            }
        }
        return null;
    }
}
