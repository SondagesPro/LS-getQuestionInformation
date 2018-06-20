<?php
/**
 * Description
 *
 * @author Denis Chenu <denis@sondages.pro>
 * @copyright 2018 Denis Chenu <http://www.sondages.pro>
 * @license AGPL v3
 * @version 0.0.0
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
use Survey;
Yii::import('application.helpers.viewHelper');
use viewHelper;
use CHtml;
use Question;
use QuestionAttribute;
use Answer;

/* @Todo : replace by non static function */
Class surveyColumnsInformation
{
    /* WIP */
    public $multipleSelect=false;

    /**
    /* Get an array with DB column name key and EM code for value or columns information for 
     * @param integer $iSurvey
     * @param string $language
     * @return null|array
     */
    public static function getAllQuestionsColumns($iSurvey,$language=null)
    {
        // First get all question
        $allQuestions = self::getAllQuestions($iSurvey,$language);
        $aColumnsToCode = array();
        foreach ($allQuestions as $aQuestion) {
            $aColumnsToCode = array_merge($aColumnsToCode,self::getQuestionColumns($aQuestion['qid'],$aQuestion['language']));
        }
        return $aColumnsToCode;
    }

    private static function getAllQuestions($iSurvey,$language=null)
    {
        /* Put in cache ? */
        $oSurvey = Survey::model()->findByPk($iSurvey);
        if(!$oSurvey) {
          return null;
        }
        if(!$language || !in_array($language,$oSurvey->getAllLanguages())) {
            $language = $oSurvey->language;
        }
        $questionTable = Question::model()->tableName();
        $command = Yii::app()->db->createCommand()
            ->select("qid,{{questions}}.language as language")
            ->from($questionTable)
            ->where("($questionTable.sid = :sid AND {{questions}}.language = :language AND $questionTable.parent_qid = 0)")
            ->join('{{groups}}', "{{groups}}.gid = {{questions}}.gid  AND {{questions}}.language = {{groups}}.language")
            ->bindParam(":sid", $iSurvey, PDO::PARAM_INT)
            ->bindParam(":language", $language, PDO::PARAM_STR)
            ->order("{{groups}}.group_order asc, {{questions}}.question_order asc");
        return $command->query()->readAll();
    }
    /**
     * Get question for listData
     * @return array[] : [$data,$option]
     */
    public static function getAllQuestionListData($iSurvey,$language=null)
    {
        $allQuestions = self::getAllQuestions($iSurvey,$language);
        $aListData = array(
            'data'=>array(),
            'options'=>array(),
        );
        foreach ($allQuestions as $aQuestion) {
            $aListData = array_merge_recursive($aListData,self::getQuestionListData($aQuestion['qid'],$aQuestion['language']));
        }
        return $aListData;
    }
    /**
     * return array  with DB column name key and EM code for value for one question
     * @param integer $qid
     * @throw Exception if debug
     * @return array|null
     */
    private static function getQuestionColumns($qid,$language) {
        
        $oQuestion = Question::model()->find("qid=:qid AND language=:language",array(":qid"=>$qid,":language"=>$language));
        if(!$oQuestion) {
            if(defined('YII_DEBUG') && YII_DEBUG) {
                throw new Exception('Invalid question iQid in getQuestionColumnToCode function.');
            }
            return null;
        }
        if($oQuestion->parent_qid) {
            if(defined('YII_DEBUG') && YII_DEBUG) {
                throw new Exception('Invalid question iQid in getQuestionColumnToCode function. This function must be call only for parent question.');
            }
            return null;
        }
        $language = $oQuestion->language;
        $aColumnsInfo = array();
        $questionClass= Question::getQuestionClass($oQuestion->type);
        $aDefaultColumnInfo = array(
            'htmlOptions' => array('class' => 'data-column column-'.$questionClass),
            'filterInputOptions' => array(
                'class'=>'form-control input-sm filter-'.$questionClass,
                //~ 'empty'=>gT("All"),
                ),
        );
        switch($questionClass) {
            /* Single text */
            case 'text-short':
            case 'text-long':
            case 'text-huge':
            case 'equation':
                $aColumnsInfo[$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid] = array_merge($aDefaultColumnInfo,
                    array(
                    'name'=>$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid,
                    'header'=>"<strong>[{$oQuestion->title}]</strong> <small>".viewHelper::flatEllipsizeText($oQuestion->question,true,40,'…',0.6)."</small>",
                ));
                break;
            case 'choice-5-pt-radio':
            case 'list-radio':
            case 'list-with-comment':
            case 'list-dropdown':
            case 'yes-no':
            case 'gender':
            case 'language':
                $aColumnsInfo[$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid] = array_merge($aDefaultColumnInfo,array(
                    'name'=>$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid,
                    'header'=>"<strong>[{$oQuestion->title}]</strong> <small>".viewHelper::flatEllipsizeText($oQuestion->question,true,40,'…',0.6)."</small>",
                    'filter'=>self::getFilter($oQuestion),
                    //~ 'filterInputOptions'=>array('multiple'=>true),
                    'type'=>'raw',
                    'value'=>'\getQuestionInformation\helpers\surveyColumnsInformation::getAnswerValue($data,$this,'.$oQuestion->qid.',"'.$oQuestion->type.'","'.$oQuestion->language.'")',
                ));
                break;
            case 'array-5-pt':
            case 'array-10-pt':
            case 'array-yes-uncertain-no':
            case 'array-increase-same-decrease':
            case 'array-flexible-row':
            case 'array-flexible-column':
                $oSubQuestions = Question::model()->findAll(array(
                    'select'=>'title',
                    'condition'=>"sid=:sid and language=:language and parent_qid=:qid",
                    'order'=>'question_order asc',
                    'params'=>array(":sid"=>$oQuestion->sid,":language"=>$language,":qid"=>$oQuestion->qid),
                ));
                if($oSubQuestions) {
                    foreach($oSubQuestions as $oSubQuestion) {
                        $header = "<strong>[{$oQuestion->title}_{$oSubQuestion->title}]</strong>"
                                . "<small>".viewHelper::flatEllipsizeText($oQuestion->question,true,40,'…',0.6)."</small>"
                                . "<small>".viewHelper::flatEllipsizeText($oSubQuestion->question,true,40,'…',0.6)."</small>";
                        $aColumnsInfo[$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid.$oSubQuestion->title] = array_merge($aDefaultColumnInfo,array(
                            'name'=>$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid.$oSubQuestion->title,
                            'header'=> $header,
                            'filter'=>self::getFilter($oQuestion),
                            //~ 'filterInputOptions'=>array('multiple'=>true),
                            'type'=>'raw',
                            'value'=>'\getQuestionInformation\helpers\surveyColumnsInformation::getAnswerValue($data,$this,'.$oQuestion->qid.',"'.$oQuestion->type.'","'.$oQuestion->language.'")',
                        ));
                    }
                }
                break;
            case 'array-flexible-duel-scale':
                $oSubQuestions = Question::model()->findAll(array(
                    'select'=>'title',
                    'condition'=>"sid=:sid and language=:language and parent_qid=:qid",
                    'order'=>'question_order asc',
                    'params'=>array(":sid"=>$oQuestion->sid,":language"=>$language,":qid"=>$oQuestion->qid),
                ));
                if($oSubQuestions) {
                    foreach($oSubQuestions as $oSubQuestion) {
                        $header = "<strong>[{$oQuestion->title}_{$oSubQuestion->title}][".gT("Scale 1")."]</strong>"
                                . "<small>".viewHelper::flatEllipsizeText($oQuestion->question,true,40,'…',0.6)."</small>"
                                . "<small>".viewHelper::flatEllipsizeText($oSubQuestion->question,true,40,'…',0.6)."</small>";
                        $aColumnsInfo[$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid.$oSubQuestion->title."#0"]=array_merge($aDefaultColumnInfo,array(
                            'name'=>$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid.$oSubQuestion->title."#0",
                            'header'=> $header,
                            'filter'=>self::getFilter($oQuestion),
                            //~ 'filterInputOptions'=>array('multiple'=>true),
                            'type'=>'raw',
                            'value'=>'\getQuestionInformation\helpers\surveyColumnsInformation::getAnswerValue($data,$this,'.$oQuestion->qid.',"'.$oQuestion->type.'","'.$oQuestion->language.'",0)',
                        ));
                        $header = "<strong>[{$oQuestion->title}_{$oSubQuestion->title}][".gT("Scale 2")."]</strong>"
                                . "<small>".viewHelper::flatEllipsizeText($oQuestion->question,true,40,'…',0.6)."</small>"
                                . "<small>".viewHelper::flatEllipsizeText($oSubQuestion->question,true,40,'…',0.6)."</small>";
                        $aColumnsInfo[$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid.$oSubQuestion->title."#1"]=array_merge($aDefaultColumnInfo,array(
                            'name'=>$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid.$oSubQuestion->title."#1",
                            'header'=> $header,
                            'filter'=>self::getFilter($oQuestion),
                            //~ 'filterInputOptions'=>array('multiple'=>true),
                            'type'=>'raw',
                            'value'=>'\getQuestionInformation\helpers\surveyColumnsInformation::getAnswerValue($data,$this,'.$oQuestion->qid.',"'.$oQuestion->type.'","'.$oQuestion->language.'",1)',
                        ));
                    }
                }
                break;
            case 'numeric':
                $aColumnsInfo[$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid] = array_merge($aDefaultColumnInfo,
                    array(
                    'name'=>$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid,
                    'header'=>"<strong>[{$oQuestion->title}]</strong> <small>".viewHelper::flatEllipsizeText($oQuestion->question,true,40,'…',0.6)."</small>",
                    'value'=>'\getQuestionInformation\helpers\surveyColumnsInformation::getDecimalValue($data,$this,'.$oQuestion->qid.')',
                    /* 'type'=>'number', // see https://www.yiiframework.com/doc/api/1.1/CLocalizedFormatter */
                ));
                break;
            case 'numeric-multi':
                $oSubQuestions = Question::model()->findAll(array(
                    'select'=>'title',
                    'condition'=>"sid=:sid and language=:language and parent_qid=:qid",
                    'order'=>'question_order asc',
                    'params'=>array(":sid"=>$oQuestion->sid,":language"=>$language,":qid"=>$oQuestion->qid),
                ));
                if($oSubQuestions) {
                    foreach($oSubQuestions as $oSubQuestion) {
                        $header = "<strong>[{$oQuestion->title}_{$oSubQuestion->title}]</strong>"
                                . "<small>".viewHelper::flatEllipsizeText($oQuestion->question,true,40,'…',0.6)."</small>"
                                . "<small>".viewHelper::flatEllipsizeText($oSubQuestion->question,true,40,'…',0.6)."</small>";
                        $aColumnsInfo[$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid.$oSubQuestion->title] = array_merge($aDefaultColumnInfo,array(
                            'name'=>$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid.$oSubQuestion->title,
                            'header'=> $header,
                            'value'=>'\getQuestionInformation\helpers\surveyColumnsInformation::getDecimalValue($data,$this,'.$oQuestion->qid.')',
                        ));
                    }
                }
                break;
            case 'multiple-opt':
            case 'multiple-opt-comments':
                $oSubQuestions = Question::model()->findAll(array(
                    'select'=>'title',
                    'condition'=>"sid=:sid and language=:language and parent_qid=:qid",
                    'order'=>'question_order asc',
                    'params'=>array(":sid"=>$oQuestion->sid,":language"=>$language,":qid"=>$oQuestion->qid),
                ));
                if($oSubQuestions) {
                    foreach($oSubQuestions as $oSubQuestion) {
                        $header = "<strong>[{$oQuestion->title}_{$oSubQuestion->title}]</strong>"
                                . "<small>".viewHelper::flatEllipsizeText($oQuestion->question,true,40,'…',0.6)."</small>"
                                . "<small>".viewHelper::flatEllipsizeText($oSubQuestion->question,true,40,'…',0.6)."</small>";
                        $aColumnsInfo[$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid.$oSubQuestion->title] = array_merge($aDefaultColumnInfo,array(
                            'name'=>$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid.$oSubQuestion->title,
                            'header'=> $header,
                            'filter'=>self::getFilter($oQuestion),
                            'value'=>'\getQuestionInformation\helpers\surveyColumnsInformation::getCheckValue($data,$this,'.$oQuestion->qid.')',
                        ));
                        if($questionClass=='multiple-opt-comments') {
                           $aColumnsInfo[$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid.$oSubQuestion->title.'_comment'] = array_merge($aDefaultColumnInfo,array(
                                'name'=>$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid.$oSubQuestion->title.'comment',
                                'header'=> "<strong>[{$oQuestion->title}_{$oSubQuestion->title}_comment]</strong>",
                            ));
                        }
                    }
                }
                break;
            case 'array-multi-flexi':
            case 'array-multi-flexi-text':
                $oSubQuestionsY = Question::model()->findAll(array(
                    'select'=>'title',
                    'condition'=>"sid=:sid and language=:language and parent_qid=:qid and scale_id=0",
                    'order'=>'question_order asc',
                    'params'=>array(":sid"=>$oQuestion->sid,":language"=>$language,":qid"=>$oQuestion->qid),
                ));
                if($oSubQuestionsY) {
                    foreach($oSubQuestionsY as $oSubQuestionY) {
                        $oSubQuestionsX = Question::model()->findAll(array(
                            'select'=>'title',
                            'condition'=>"sid=:sid and language=:language and parent_qid=:qid and scale_id=1",
                            'order'=>'question_order asc',
                            'params'=>array(":sid"=>$oQuestion->sid,":language"=>$language,":qid"=>$oQuestion->qid),
                        ));
                        if($oSubQuestionsX) {
                            foreach($oSubQuestionsX as $oSubQuestionX) {
                               $aColumnsInfo[$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid.$oSubQuestionY->title."_".$oSubQuestionX->title] = array_merge($aDefaultColumnInfo,array(
                                    'name'=>$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid.$oSubQuestionY->title."_".$oSubQuestionX->title,
                                    'header'=> "<strong>[{$oQuestion->title}__{$oSubQuestionY->title}_{$oSubQuestionX->title}]</strong>",
                                ));// No need to set decimal value since fexi is float
                            }
                        }
                    }
                }
                break;
            case 'multiple-short-txt' :
                $oSubQuestions = Question::model()->findAll(array(
                    'select'=>'title',
                    'condition'=>"sid=:sid and language=:language and parent_qid=:qid",
                    'order'=>'question_order asc',
                    'params'=>array(":sid"=>$oQuestion->sid,":language"=>$language,":qid"=>$oQuestion->qid),
                ));
                if($oSubQuestions) {
                    foreach($oSubQuestions as $oSubQuestion) {
                        $header = "<strong>[{$oQuestion->title}_{$oSubQuestion->title}]</strong>"
                                . "<small>".viewHelper::flatEllipsizeText($oQuestion->question,true,40,'…',0.6)."</small>"
                                . "<small>".viewHelper::flatEllipsizeText($oSubQuestion->question,true,40,'…',0.6)."</small>";
                        $aColumnsInfo[$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid.$oSubQuestion->title] = array_merge($aDefaultColumnInfo,array(
                            'name'=>$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid.$oSubQuestion->title,
                            'header'=> $header,
                        ));
                    }
                }
                break;
            case 'date':
                $aColumnsInfo[$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid] = array_merge($aDefaultColumnInfo,
                    array(
                    'name'=>$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid,
                    'header'=>"<strong>[{$oQuestion->title}]</strong> <small>".viewHelper::flatEllipsizeText($oQuestion->question,true,40,'…',0.6)."</small>",
                ));
                break;
            case 'ranking':
                $oQuestionAttribute = \QuestionAttribute::model()->find(
                    "qid = :qid AND attribute = 'max_subquestions'",
                    array(':qid' => $oQuestion->qid)
                );
                if($oQuestionAttribute) {
                    $maxAnswers = intval($oQuestionAttribute->value);
                }
                if(empty($maxAnswers)) {
                    $maxAnswers = intval(Answer::model()->count(
                        "qid=:qid and language=:language",
                        array(":qid"=>$oQuestion->qid,":language"=>$oQuestion->language)
                    ));
                }
                for ($count = 1; $count <= $maxAnswers; $count++) {
                    $header = "<strong>[{$oQuestion->title}_{$count}]</strong>"
                            . "<small>".viewHelper::flatEllipsizeText($oQuestion->question,true,40,'…',0.6)."</small>"
                            . "<small>".sprintf(gT("Rank %s"),$count)."</small>";
                    $aColumnsInfo[$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid.$count] = array_merge($aDefaultColumnInfo,array(
                        'name'=>$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid.$count,
                        'header'=> $header,
                        'filter'=>self::getFilter($oQuestion),
                        'value'=>'\getQuestionInformation\helpers\surveyColumnsInformation::getAnswerValue($data,$this,'.$oQuestion->qid.',"'.$oQuestion->type.'","'.$oQuestion->language.'")',
                    ));
                    $aColumnsToCode[$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid.$count]=$oQuestion->title."_".$count;
                }
                break;
            case 'upload-files':
                // @todo
            case 'boilerplate':
                /* Don't show it*/
                break;
            default:
                //~ if(defined('YII_DEBUG') && YII_DEBUG) {
                    //~ throw new Exception(sprintf('Unknow question type %s.',$oQuestion->type));
                //~ }  
                // NUll
        }
        if(self::allowOther($oQuestion->type) and $oQuestion->other=="Y") {
            $aColumnsInfo[$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid."other"] = array_merge($aDefaultColumnInfo,
                array(
                'name'=>$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid."other",
                'header'=>"<strong>[{$oQuestion->title}_other]</strong> <small>".viewHelper::flatEllipsizeText($oQuestion->question,true,40,'…',0.6)."</small>",
            ));
            //~ $aColumnsToCode[$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid."other"]=$oQuestion->title."_other";
        }
        return $aColumnsInfo;
    }

    /**
     * return array  with DB column name key and EM code for value for one question
     * @param integer $qid
     * @param string $language
     * @param boolean @todo allow usage of em code or not
     * @throw Exception if debug
     * @return array|null
     */
    private static function getQuestionListData($qid,$language,$byEm=false) {
        $oQuestion = Question::model()->find("qid=:qid AND language=:language",array(":qid"=>$qid,":language"=>$language));
        if(!$oQuestion) {
            if(defined('YII_DEBUG') && YII_DEBUG) {
                throw new Exception('Invalid question iQid in getQuestionColumnToCode function.');
            }
            return null;
        }
        if($oQuestion->parent_qid) {
            if(defined('YII_DEBUG') && YII_DEBUG) {
                throw new Exception('Invalid question iQid in getQuestionColumnToCode function. This function must be call only for parent question.');
            }
            return null;
        }
        $language = $oQuestion->language;
        $aListData = array(
            'data'=>array(),
            'options'=>array(),
        );
        $aDefaultOptions = array(
            'data-html'=>true,
            'data-trigger'=>'hover focus',
        );
        switch ($oQuestion->type) {
            /* Simple one column question */
            case "5": // 'choice-5-pt-radio';
            case "D": // 'date'
            case "G": // Gender
            case "I": // 'language';
            case "N": // numeric
            case "L": // list-radio
            case "O": // list-with-comment
            case "S": // short-text
            case "T": // long-text
            case "U": // huge-text
            case "Y": // yes-no
            case "!": // list-dropdown
            case "*": // equation
                $key = $oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid;
                if($byEm) {
                    $key = $oQuestion->title;
                }
                $aListData['data'][$key] = "[{$oQuestion->title}] ".viewHelper::flatEllipsizeText($oQuestion->question,true,60,'…',0.6);
                $aListData['options'][$key] = array_merge($aDefaultOptions,array(
                    'data-content'=>viewHelper::purified($oQuestion->question),
                    'data-title'=>$oQuestion->title,
                    'title'=>viewHelper::purified($oQuestion->question)
                ));
                break;
            /* Multiple column question (array/multiple choice) */
            case "A": // 'array-5-pt';
            case "B": // 'array-10-pt';
            case "C": // 'array-yes-uncertain-no';
            case "E": // 'array-increase-same-decrease';
            case "F": // 'array-flexible-row';
            case "H": // 'array-flexible-column';
            case "K": // 'numeric-multi';
            case "M": // 'multiple-opt';
            case "P": // 'multiple-opt-comments';
            case "Q": // 'multiple-short-txt';
                $oSubQuestions = Question::model()->findAll(array(
                    'select'=>'title,question',
                    'condition'=>"sid=:sid and language=:language and parent_qid=:qid",
                    'order'=>'question_order asc',
                    'params'=>array(":sid"=>$oQuestion->sid,":language"=>$language,":qid"=>$oQuestion->qid),
                ));
                if($oSubQuestions) {
                    foreach($oSubQuestions as $oSubQuestion) {
                        $key = $oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid.$oSubQuestion->title;
                        if($byEm) {
                            $key = $oQuestion->title."_".$oSubQuestion->title;
                        }
                        $aListData['data'][$key] = "[{$oQuestion->title}_{$oSubQuestion->title}] (".viewHelper::flatEllipsizeText($oQuestion->question,true,30,'…',0.7).") ".viewHelper::flatEllipsizeText($oSubQuestion->question,true,40,'…',0.6);
                        $aListData['options'][$key] = array_merge($aDefaultOptions,array(
                            'data-content'=>viewHelper::purified($oQuestion->question).'<hr>'.viewHelper::purified($oSubQuestion->question),
                            'data-title'=>$oQuestion->title."_".$oSubQuestion->title,
                            'title'=>viewHelper::purified($oQuestion->question)."\n".viewHelper::purified($oSubQuestion->question),
                        ));
                    }
                }
                break;
            case "1": // array-dual-scale
                $oSubQuestions = Question::model()->findAll(array(
                    'select'=>'title,question',
                    'condition'=>"sid=:sid and language=:language and parent_qid=:qid",
                    'order'=>'question_order asc',
                    'params'=>array(":sid"=>$oQuestion->sid,":language"=>$language,":qid"=>$oQuestion->qid),
                ));
                if($oSubQuestions) {
                    foreach($oSubQuestions as $oSubQuestion) {
                        $key = $oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid.$oSubQuestion->title."#0";
                        if($byEm) {
                            $key = $oQuestion->title."_".$oSubQuestion->title."_0";
                        }
                        $aListData['data'][$key] = "[{$oQuestion->title}_{$oSubQuestion->title}_0] (".viewHelper::flatEllipsizeText($oQuestion->question,true,30,'…',0.7).") (".gT("Scale 1").") ".viewHelper::flatEllipsizeText($oSubQuestion->question,true,40,'…',0.6);
                        $aListData['options'][$key] = array_merge($aDefaultOptions,array(
                            'data-content'=>viewHelper::purified($oQuestion->question).'<hr>'.gT("Scale 1").'<hr>'.viewHelper::purified($oSubQuestion->question),
                            'data-title'=>$oQuestion->title."_".$oSubQuestion->title."_0",
                            'title'=>viewHelper::purified($oQuestion->question)."\n".gT("Scale 1")."\n".viewHelper::purified($oSubQuestion->question),
                        ));
                        $key = $oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid.$oSubQuestion->title."#1";
                        if($byEm) {
                            $key = $oQuestion->title."_".$oSubQuestion->title."_1";
                        }
                        $aListData['data'][$key] = "[{$oQuestion->title}_{$oSubQuestion->title}_1] (".viewHelper::flatEllipsizeText($oQuestion->question,true,30,'…',0.7).") (".gT("Scale 2").") ".viewHelper::flatEllipsizeText($oSubQuestion->question,true,40,'…',0.6);
                        $aListData['options'][$key] = array_merge($aDefaultOptions,array(
                            'data-content'=>viewHelper::purified($oQuestion->question).'<hr>'.gT("Scale 2").'<hr>'.viewHelper::purified($oSubQuestion->question),
                            'data-title'=>$oQuestion->title."_".$oSubQuestion->title."_1",
                            'title'=>viewHelper::purified($oQuestion->question)."\n".gT("Scale 2")."\n".viewHelper::purified($oSubQuestion->question),
                        ));
                    }
                }
                break;
        }
        /* @todo other and comments */
        
        return $aListData;
    }
    /**
     * @param string $type
     * return boolean
     */
    private static function allowOther($type){
        $allowOther = array("L","!","P","M");
        return in_array($type,$allowOther);
    }

    /**
     * get the filter for this column
     * @param Question
     * @param $scale
     * return array|null
     */
    public static function getFilter($oQuestion,$scale=0)
    {
        /* @TODO : add other */
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
                if(!empty($answers)) {
                    return CHtml::listData($answers,'code',function($answers) {
                        return strip_tags(viewHelper::purified($answers->answer));
                    });
                }
                if(self::allowOther($oQuestion->type) && $oQuestion->other=="Y"){
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
                return self::getFilterLanguage($oQuestion->sid);
            case 'multiple-opt':
            case 'multiple-opt-comments':
                return array(
                    "Y" => gT("Yes"),
                );
            default:
                return null;
        }
    }

    public static function getAnswerValue($data,$column,$iQid,$type,$language,$scale=0) {
        $name = $column->name;
        if(empty($data->$name)) {
            return "";
        }
        $oQuestion = Question::model()->find("qid =:qid AND language=:language",array(":qid"=>$iQid,":language"=>$language));
        $questionClass= Question::getQuestionClass($type);
        switch ($questionClass) {
            case 'choice-5-pt-radio':
            case 'array-5-pt':
            case 'array-10-pt':
                return $data->$name;
                break;
            default:
                $aAnswers = self::getFilter($oQuestion,$scale);
                if(isset($aAnswers[$data->$name])) {
                    $answer = $aAnswers[$data->$name];
                    return "[".$data->$name."] ".$answer;
                }
                return CHtml::encode($data->$name);
                break;
        }
    }

    public static function getDateValue() {

    }

    public static function getDecimalValue($data,$column,$iQid) {
        $name = $column->name;
        $value = $data->$name;
        if(is_null($data->$name) || $data->$name==="") {
            return "";// disable nullDisplay since no diff between null and not answered $data->$name;
        }
        return floatval($data->$name);// Quickly done
    }

    public static function getCheckValue($data,$column,$iQid) {
        $name = $column->name;
        $value = $data->$name;
        if(is_null($data->$name)) {
            return "";
        }
        return ($data->$name) ? gT("Yes") : "";/* @todo : find filter for "" VS null */
    }

    public static function getFilterLanguage($surveyId) {
        $aLanguages = Survey::model()->findByPk($surveyId)->getAllLanguages();
        $aFilterLanguages = array();
        foreach($aLanguages as $language) {
            $aFilterLanguages[$language] = getLanguageNameFromCode($language,false,App()->getLanguage());
        }
        return $aFilterLanguages;
    }
}
