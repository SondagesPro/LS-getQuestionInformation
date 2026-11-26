<?php

/**
 * This file is part of getQuestionInformation plugin
 * Questions, subquestions, texts and attributes of a survey loaded once for current request :
 * helpers need them for each question and subquestion.
 * @license AGPL v3
 */

namespace getQuestionInformation\helpers;

use Yii;
use CDbCriteria;
use Question;
use QuestionL10n;
use QuestionAttribute;

class surveyQuestions
{
    /** @var Question[] questions and subquestions by qid */
    private static $questions = [];

    /** @var Question[][] subquestions by parent qid, ordered by scale and question_order */
    private static $subQuestions = [];

    /** @var integer[][] qid of questions and subquestions by survey id */
    private static $surveyQids = [];

    /** @var QuestionL10n[][] text by qid and language */
    private static $l10ns = [];

    /** @var boolean[][] texts loaded by survey id and language */
    private static $loadedL10ns = [];

    /** @var string[][] attribute value by qid and attribute name */
    private static $attributes = [];

    /**
     * Get a question or a subquestion, load all questions of the survey
     * @param integer $qid
     * @return Question|null
     */
    public static function getQuestion($qid)
    {
        $qid = intval($qid);
        if (!isset(self::$questions[$qid])) {
            $oQuestion = Question::model()->findByPk($qid);
            if (!$oQuestion) {
                return null;
            }
            self::loadSurvey($oQuestion->sid);
        }
        return self::$questions[$qid] ?? null;
    }

    /**
     * Get subquestions of a question, ordered by question_order
     * @param integer $qid parent question
     * @param integer|null $scale scale_id, null for all scales
     * @return Question[]
     */
    public static function getSubQuestions($qid, $scale = null)
    {
        if (!self::getQuestion($qid)) {
            return array();
        }
        $subQuestions = self::$subQuestions[intval($qid)] ?? array();
        if (is_null($scale)) {
            return $subQuestions;
        }
        return array_values(array_filter($subQuestions, fn($oSubQuestion) => intval($oSubQuestion->scale_id) === intval($scale)));
    }

    /**
     * Get the text of a question or subquestion in a language, load all texts of the survey in this language
     * @param integer $qid
     * @param string $language
     * @return QuestionL10n|null
     */
    public static function getQuestionL10n($qid, $language)
    {
        $oQuestion = self::getQuestion($qid);
        if (!$oQuestion) {
            return null;
        }
        $sid = intval($oQuestion->sid);
        if (empty(self::$loadedL10ns[$sid][$language])) {
            self::$loadedL10ns[$sid][$language] = true;
            $criteria = new CDbCriteria();
            $criteria->compare('language', $language);
            $criteria->addInCondition('qid', self::$surveyQids[$sid]);
            foreach (QuestionL10n::model()->resetScope()->findAll($criteria) as $oQuestionL10n) {
                self::$l10ns[$oQuestionL10n->qid][$language] = $oQuestionL10n;
            }
        }
        return self::$l10ns[intval($qid)][$language] ?? null;
    }

    /**
     * Get value of a question attribute, null if not set
     * @param integer $qid
     * @param string $name
     * @return string|null
     */
    public static function getAttribute($qid, $name)
    {
        if (!self::getQuestion($qid)) {
            return null;
        }
        return self::$attributes[intval($qid)][$name] ?? null;
    }

    /**
     * Load questions, subquestions and attributes of a survey
     * @param integer $sid
     * @return void
     */
    private static function loadSurvey($sid)
    {
        $sid = intval($sid);
        if (isset(self::$surveyQids[$sid])) {
            return;
        }
        self::$surveyQids[$sid] = array();
        $criteria = new CDbCriteria();
        $criteria->compare('sid', $sid);
        $criteria->order = 'parent_qid ASC, scale_id ASC, question_order ASC, qid ASC';
        foreach (Question::model()->resetScope()->findAll($criteria) as $oQuestion) {
            $qid = intval($oQuestion->qid);
            self::$questions[$qid] = $oQuestion;
            self::$surveyQids[$sid][] = $qid;
            if ($oQuestion->parent_qid) {
                self::$subQuestions[intval($oQuestion->parent_qid)][] = $oQuestion;
            }
        }
        if (empty(self::$surveyQids[$sid])) {
            return;
        }
        /* Raw rows : a survey can have thousands of attributes, active record are too slow here */
        $attributes = Yii::app()->db->createCommand()
            ->select('qid, attribute, value')
            ->from(QuestionAttribute::model()->tableName())
            ->where(array('in', 'qid', self::$surveyQids[$sid]))
            ->order('qid ASC')
            ->queryAll();
        foreach ($attributes as $attribute) {
            /* Keep the first one, as QuestionAttribute::model()->find did before */
            if (!isset(self::$attributes[$attribute['qid']][$attribute['attribute']])) {
                self::$attributes[$attribute['qid']][$attribute['attribute']] = $attribute['value'];
            }
        }
    }
}
