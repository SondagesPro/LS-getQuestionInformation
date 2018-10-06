<?php
/**
 * Description
 *
 * @author Denis Chenu <denis@sondages.pro>
 * @copyright 2018 Denis Chenu <http://www.sondages.pro>
 * @license AGPL v3
 * @version 1.0.0
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
Yii::import('application.helpers.viewHelper');
use viewHelper;
use CHtml;
use Question;
use QuestionAttribute;
use Answer;

/* @Todo : replace by non static function */
Class surveyColumnsInformation
{
    const apiversion=1;
    /**
     * @var integer survey id
     */
    public $iSurvey;
    /**
     * @var string language
     */
    public $language;
    
    /* WIP */
    public $multipleSelect = false;

    /**
     * @var array|null
     * route and params for create url @see https://www.yiiframework.com/doc/api/1.1/CUrlManager#createUrl-detail
     * @example ['route'=>"plugins/direct",['plugin' => 'myPlugin', 'action'=>'downloadfile']]
     * params are autocompleted with sid=$sid , srid=$srid, qid=$qid, fileindex=$fileindex
     */
    public $downloadUrl = null;

    /**
     * @var model
     */
    public $model = null;

    /**
     * @var boolean
     * return EM code for title (currently only in listData), by default column value
     */
    public $ByEmCode = false;

    /**
     * constructor
     * @param integer survey id
     * @param string language
     * @param array options
     * @throw error
     */
    function __construct($iSurvey,$language=null,$options = array()) {
        $this->iSurvey = $iSurvey;
        $oSurvey = Survey::model()->findByPk($this->iSurvey);
        if(!$oSurvey) {
            throw new \CHttpException(404);
        }
        if(!$language || !in_array($language,$oSurvey->getAllLanguages()) ) {
            $language = $oSurvey->language;
        }
        $this->language = $language;
    }

    /**
     * Get an array with DB column name key and EM code for value or columns information for 
     * @return null|array
     */
    public function allQuestionsColumns()
    {
        // First get all question
        $allQuestions = $this->allQuestions();
        $aColumnsToCode = array();
        foreach ($allQuestions as $aQuestion) {
            $aColumnsToCode = array_merge($aColumnsToCode,$this->questionColumns($aQuestion['qid']));
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
    public static function getAllQuestionsColumns($iSurvey,$language=null)
    {
        $surveyColumnsInformation = new self($iSurvey,$language);
        return $surveyColumnsInformation->allQuestionsColumns();
    }

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
     * Get question for listData, shortcut to allQuestionListData
     * @param integer $iSurvey
     * @param string $language
     * @return array[] : [$data,$option]
     */
    public static function getAllQuestionListData($iSurvey,$language=null)
    {
        $surveyColumnsInformation = new self($iSurvey,$language);
        return $surveyColumnsInformation->allQuestionListData();
    }

    /**
     * Get question for listData
     * @return array[] : [$data,$option]
     */
    public function allQuestionListData()
    {
        $allQuestions = $this->allQuestions();
        $aListData = array(
            'data'=>array(),
            'options'=>array(),
        );
        foreach ($allQuestions as $aQuestion) {
            $aListData = array_merge_recursive($aListData,$this->questionListData($aQuestion['qid']));
        }
        return $aListData;
    }

    /**
     * Get question for listData
     * @return array[] : [$data,$option]
     */
    public function allQuestionsType()
    {
        $allQuestions = $this->allQuestions();
        $aQuestionsType = array();
        foreach ($allQuestions as $aQuestion) {
            $aQuestionsType = array_merge($aQuestionsType,$this->questionTypes($aQuestion['qid']));
        }
        return $aQuestionsType;
    }

    /**
     * static shortcut to questionColumns
     * @param integer $question id
     * @return array|null
     */
    public static function getQuestionColumns($qid,$language = null)
    {
        $oQuestion = Question::model()->find("qid=:qid",array(":qid"=>$qid));
        if(!$oQuestion) {
            if(defined('YII_DEBUG') && YII_DEBUG) {
                throw new Exception('Invalid question iQid in getQuestionColumnToCode function.');
            }
            return null;
        }
        $surveyColumnsInformation = new self($oQuestion->sid,$language);
        return $surveyColumnsInformation->questionColumns($qid);
    }
    
    /**
     * return array  with DB column name key and EM code for value for one question
     * @param integer $qid
     * @throw Exception if debug
     * @return array|null
     */
    public function questionColumns($qid) {
        $oQuestion = Question::model()->find("qid=:qid AND language=:language",array(":qid"=>$qid,":language"=>$this->language));
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
            case 'equation':
            case 'text-long':
            case 'text-huge':
                $aColumnsInfo[$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid] = array_merge($aDefaultColumnInfo,
                    array(
                    'name'=>$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid,
                    'header'=> CHTml::tag('strong',array(),"[{$oQuestion->title}]") . self::getExtraHtmlHeader($oQuestion),
                    'type'=>'raw',
                    'value' => '\getQuestionInformation\helpers\surveyColumnsInformation::getFreeAnswerValue($data,$this)',
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
                    'header'=> CHTml::tag('strong',array(),"[{$oQuestion->title}]") . self::getExtraHtmlHeader($oQuestion),
                    'filter'=>$this->getFilter($oQuestion),
                    //~ 'filterInputOptions'=>array('multiple'=>true),
                    'type'=>'raw',
                    'value'=>'\getQuestionInformation\helpers\surveyColumnsInformation::getAnswerValue($data,$this,'.$oQuestion->qid.',"'.$oQuestion->type.'","'.$oQuestion->language.'")',
                ));
                if($oQuestion->type == "O") {
                    $aColumnsInfo[$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid."comment"] = array_merge($aDefaultColumnInfo,array(
                        'name'=>$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid."comment",
                        'header'=> CHTml::tag('strong',array(),"[{$oQuestion->title}_comment]") . CHTml::tag('small',array(),gT('Comment')). self::getExtraHtmlHeader($oQuestion),
                        'type'=>'raw',
                        'value'=>'\getQuestionInformation\helpers\surveyColumnsInformation::getFreeAnswerValue($data,$this)',
                    ));
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
                if($oSubQuestions) {
                    foreach($oSubQuestions as $oSubQuestion) {
                        $aColumnsInfo[$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid.$oSubQuestion->title] = array_merge($aDefaultColumnInfo,array(
                            'name'=>$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid.$oSubQuestion->title,
                            'header'=> CHTml::tag('strong',array(),"[{$oQuestion->title}_{$oSubQuestion->title}]") . self::getExtraHtmlHeader($oQuestion,$oSubQuestion),
                            'filter'=>$this->getFilter($oQuestion),
                            //~ 'filterInputOptions'=>array('multiple'=>true),
                            'type'=>'raw',
                            'value'=>'\getQuestionInformation\helpers\surveyColumnsInformation::getAnswerValue($data,$this,'.$oQuestion->qid.',"'.$oQuestion->type.'","'.$oQuestion->language.'")',
                        ));
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
                if($oSubQuestions) {
                    foreach($oSubQuestions as $oSubQuestion) {
                        $aColumnsInfo[$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid.$oSubQuestion->title."#0"]=array_merge($aDefaultColumnInfo,array(
                            'name'=>$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid.$oSubQuestion->title."#0",
                            'header'=> CHTml::tag('strong',array(),"[{$oQuestion->title}_{$oSubQuestion->title}_1]") . self::getExtraHtmlHeader($oQuestion,$oSubQuestion) . CHtml::tag("small",array(),gT("SCale 1")),
                            'filter'=>$this->getFilter($oQuestion),
                            //~ 'filterInputOptions'=>array('multiple'=>true),
                            'type'=>'raw',
                            'value'=>'\getQuestionInformation\helpers\surveyColumnsInformation::getAnswerValue($data,$this,'.$oQuestion->qid.',"'.$oQuestion->type.'","'.$oQuestion->language.'",0)',
                        ));
                        $aColumnsInfo[$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid.$oSubQuestion->title."#1"]=array_merge($aDefaultColumnInfo,array(
                            'name'=>$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid.$oSubQuestion->title."#1",
                            'header'=> CHTml::tag('strong',array(),"[{$oQuestion->title}_{$oSubQuestion->title}_2]") . self::getExtraHtmlHeader($oQuestion,$oSubQuestion) . CHtml::tag("small",array(),gT("SCale 2")),
                            'filter'=>$this->getFilter($oQuestion),
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
                    'header'=> CHTml::tag('strong',array(),"[{$oQuestion->title}]"). self::getExtraHtmlHeader($oQuestion),
                    'value'=>'\getQuestionInformation\helpers\surveyColumnsInformation::getDecimalValue($data,$this,'.$oQuestion->qid.')',
                    /* 'type'=>'number', // see https://www.yiiframework.com/doc/api/1.1/CLocalizedFormatter , broke with string (decimal)*/
                ));
                break;
            case 'numeric-multi':
                $oSubQuestions = Question::model()->findAll(array(
                    'select'=>'title,question',
                    'condition'=>"sid=:sid and language=:language and parent_qid=:qid",
                    'order'=>'question_order asc',
                    'params'=>array(":sid"=>$oQuestion->sid,":language"=>$language,":qid"=>$oQuestion->qid),
                ));
                if($oSubQuestions) {
                    foreach($oSubQuestions as $oSubQuestion) {
                        $aColumnsInfo[$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid.$oSubQuestion->title] = array_merge($aDefaultColumnInfo,array(
                            'name'=>$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid.$oSubQuestion->title,
                            'header'=> CHTml::tag('strong',array(),"[{$oQuestion->title}_{$oSubQuestion->title}]") . self::getExtraHtmlHeader($oQuestion,$oSubQuestion),
                            'value'=>'\getQuestionInformation\helpers\surveyColumnsInformation::getDecimalValue($data,$this,'.$oQuestion->qid.')',
                            /* 'type'=>'number', // see https://www.yiiframework.com/doc/api/1.1/CLocalizedFormatter */
                        ));
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
                if($oSubQuestions) {
                    foreach($oSubQuestions as $oSubQuestion) {
                        $aColumnsInfo[$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid.$oSubQuestion->title] = array_merge($aDefaultColumnInfo,array(
                            'name'=>$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid.$oSubQuestion->title,
                            'header'=> CHTml::tag('strong',array(),"[{$oQuestion->title}_{$oSubQuestion->title}]") . self::getExtraHtmlHeader($oQuestion,$oSubQuestion),
                            'filter'=>$this->getFilter($oQuestion),
                            'value'=>'\getQuestionInformation\helpers\surveyColumnsInformation::getCheckValue($data,$this,'.$oQuestion->qid.')',
                        ));
                        if($questionClass=='multiple-opt-comments') {
                           $aColumnsInfo[$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid.$oSubQuestion->title.'comment'] = array_merge($aDefaultColumnInfo,array(
                                'name'=>$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid.$oSubQuestion->title.'comment',
                                'header'=> CHTml::tag('strong',array(),"[{$oQuestion->title}_{$oSubQuestion->title}_comment]"). CHTml::tag('small',array(),gT('Comment')). self::getExtraHtmlHeader($oQuestion,$oSubQuestion),
                            ));
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
                if($oSubQuestionsY) {
                    foreach($oSubQuestionsY as $oSubQuestionY) {
                        $oSubQuestionsX = Question::model()->findAll(array(
                            'select'=>'title,question',
                            'condition'=>"sid=:sid and language=:language and parent_qid=:qid and scale_id=1",
                            'order'=>'question_order asc',
                            'params'=>array(":sid"=>$oQuestion->sid,":language"=>$language,":qid"=>$oQuestion->qid),
                        ));
                        if($oSubQuestionsX) {
                            foreach($oSubQuestionsX as $oSubQuestionX) {
                                $aColumnsInfo[$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid.$oSubQuestionY->title."_".$oSubQuestionX->title] = array_merge($aDefaultColumnInfo,array(
                                    'name'=>$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid.$oSubQuestionY->title."_".$oSubQuestionX->title,
                                    'header'=> CHTml::tag('strong',array(),"[{$oQuestion->title}__{$oSubQuestionY->title}_{$oSubQuestionX->title}]") . self::getExtraHtmlHeader($oQuestion,$oSubQuestionY,$oSubQuestionX),
                                ));// No need to set decimal value since flexi is float
                                if($questionClass == 'array-multi-flexi-text') {
                                    $aColumnsInfo[$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid.$oSubQuestionY->title."_".$oSubQuestionX->title] = array_merge(
                                        $aColumnsInfo[$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid.$oSubQuestionY->title."_".$oSubQuestionX->title],
                                        array(
                                            'type'=>'raw',
                                            'value' => '\getQuestionInformation\helpers\surveyColumnsInformation::getFreeAnswerValue($data,$this)',
                                        )
                                    );
                                }
                            }
                        }
                    }
                }
                break;
            case 'multiple-short-txt' :
                $oSubQuestions = Question::model()->findAll(array(
                    'select'=>'title,question',
                    'condition'=>"sid=:sid and language=:language and parent_qid=:qid",
                    'order'=>'question_order asc',
                    'params'=>array(":sid"=>$oQuestion->sid,":language"=>$language,":qid"=>$oQuestion->qid),
                ));
                if($oSubQuestions) {
                    foreach($oSubQuestions as $oSubQuestion) {
                        $aColumnsInfo[$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid.$oSubQuestion->title] = array_merge($aDefaultColumnInfo,array(
                            'name'=>$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid.$oSubQuestion->title,
                            'header'=> CHTml::tag('strong',array(),"[{$oQuestion->title}_{$oSubQuestion->title}]") . self::getExtraHtmlHeader($oQuestion,$oSubQuestion),
                            'type'=>'raw',
                            'value' => '\getQuestionInformation\helpers\surveyColumnsInformation::getFreeAnswerValue($data,$this)',
                        ));
                    }
                }
                break;
            case 'date':
                $aColumnsInfo[$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid] = array_merge($aDefaultColumnInfo,
                    array(
                    'name'=>$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid,
                    'header'=>CHTml::tag('strong',array(),"[{$oQuestion->title}]") . self::getExtraHtmlHeader($oQuestion),
                    'value' => '\getQuestionInformation\helpers\surveyColumnsInformation::getDateValue($data,$this,'.$oQuestion->qid.','.$oQuestion->sid.')',
                    'filter'=>$this->getFilter($oQuestion),
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
                            . self::getExtraHtmlHeader($oQuestion)
                            . "<small>".sprintf(gT("Rank %s"),$count)."</small>";
                    $aColumnsInfo[$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid.$count] = array_merge($aDefaultColumnInfo,array(
                        'name'=>$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid.$count,
                        'header'=> CHTml::tag('strong',array(),"[{$oQuestion->title}_{$count}]") . self::getExtraHtmlHeader($oQuestion). "<small>".sprintf(gT("Rank %s"),$count)."</small>",
                        'filter'=>$this->getFilter($oQuestion),
                        'type'=>'raw',
                        'value'=>'\getQuestionInformation\helpers\surveyColumnsInformation::getAnswerValue($data,$this,'.$oQuestion->qid.',"'.$oQuestion->type.'","'.$oQuestion->language.'")',
                    ));
                    $aColumnsToCode[$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid.$count]=$oQuestion->title."_".$count;
                }
                break;
            case 'upload-files':
                $url = $this->downloadUrl;
                if(!isset($url['route']) || !is_string($url['route']) ) {
                    $url = null;
                }
                if($url && (!isset($url['params']) || !is_array($url['params'])) ) {
                    $url['params'] = array();
                }
                $url = base64_encode(json_encode($url));
                $aColumnsInfo[$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid] = array_merge($aDefaultColumnInfo,
                    array(
                    'name'=>$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid,
                    'header'=> CHTml::tag('strong',array(),"[{$oQuestion->title}]") . self::getExtraHtmlHeader($oQuestion),
                    'sortable'=>false,
                    'type'=>'raw',
                    'value' => '\getQuestionInformation\helpers\surveyColumnsInformation::getUploadAnswerValue($data,$this,'.$oQuestion->qid.',"'.$url.'")',
                ));
                $aColumnsInfo[$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid."_filecount"] = array_merge($aDefaultColumnInfo,
                    array(
                    'name'=>$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid."_filecount" ,
                    'header'=> CHTml::tag('strong',array(),"[{$oQuestion->title}]") .CHTml::tag('small',array(),gT("File count")),
                ));
                break;
            case 'boilerplate':
                /* Don't show it*/
                break;
            default:
                tracevar($questionClass);
                //~ if(defined('YII_DEBUG') && YII_DEBUG) {
                    //~ throw new Exception(sprintf('Unknow question type %s.',$oQuestion->type));
                //~ }  
                // NUll
        }
        if(self::allowOther($oQuestion->type) and $oQuestion->other=="Y") {
            $aColumnsInfo[$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid."other"] = array_merge($aDefaultColumnInfo,
                array(
                'name'=>$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid."other",
                'header'=>"<strong>[{$oQuestion->title}_other]</strong>".self::getExtraHtmlHeader($oQuestion). CHTml::tag('small',array(),gT('Other')),
            ));
            if($oQuestion->type == "P") { /* Specific with comment … */
                $aColumnsInfo[$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid."othercomment"] = array_merge($aDefaultColumnInfo,
                    array(
                    'name'=>$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid."othercomment",
                    'header'=>"<strong>[{$oQuestion->title}_othercomment]</strong>".self::getExtraHtmlHeader($oQuestion). CHTml::tag('small',array(),gT('Other')." - ".gT('Comment')),
                ));
            }
        }
        return $aColumnsInfo;
    }

    /**
     * return array with DB column name key and EM code for value for one question
     * @param integer $qid
     * @throw Exception if debug
     * @return array|null
     */
    public function questionListData($qid) {
        $byEm = $this->ByEmCode;
        $language = $this->language;
        $oQuestion = Question::model()->find("qid=:qid AND language=:language",array(":qid"=>$qid,":language"=>$this->language));
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
                    'title'=>viewHelper::flatEllipsizeText($oQuestion->question),
                ));
                if($oQuestion->type == "O") {
                    $key = $oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid."comment";
                    if($byEm) {
                        $key = $oQuestion->title."_".$oSubQuestion->title."comment";
                    }
                    $aListData['data'][$key] = "[{$oQuestion->title}_comment] (".viewHelper::flatEllipsizeText($oQuestion->question,true,30,'…',0.7).") ".gT("Comments");
                    $aListData['options'][$key] = array_merge($aDefaultOptions,array(
                        'data-content'=>viewHelper::purified($oQuestion->question).'<hr>'.gT("Comments"),
                        'data-title'=>$oQuestion->title."_comment",
                        'title'=>viewHelper::flatEllipsizeText($oQuestion->question)."\n".gT("Comments"),
                    ));
                }
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
                            'title'=>viewHelper::flatEllipsizeText($oQuestion->question)."\n".viewHelper::flatEllipsizeText($oSubQuestion->question),
                        ));
                        if($oQuestion->type == "P") {
                            $key = $oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid.$oSubQuestion->title."comment";
                            if($byEm) {
                                $key = $oQuestion->title."_".$oSubQuestion->title."comment";
                            }
                            $aListData['data'][$key] = "[{$oQuestion->title}_{$oSubQuestion->title}comment] (".viewHelper::flatEllipsizeText($oQuestion->question,true,30,'…',0.7).") (".gT("Comments").") ".viewHelper::flatEllipsizeText($oSubQuestion->question,true,40,'…',0.6);
                            $aListData['options'][$key] = array_merge($aDefaultOptions,array(
                                'data-content'=>viewHelper::purified($oQuestion->question).'<hr>'.gT("Comments")."<hr>".viewHelper::purified($oSubQuestion->question),
                                'data-title'=>$oQuestion->title."_".$oSubQuestion->title."comment",
                                'title'=>viewHelper::flatEllipsizeText($oQuestion->question)."\n".gT("Comments")."\n".viewHelper::flatEllipsizeText($oSubQuestion->question),
                            ));
                        }
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
                            'title'=>viewHelper::flatEllipsizeText($oQuestion->question)."\n".gT("Scale 1")."\n".viewHelper::flatEllipsizeText($oSubQuestion->question),
                        ));
                        $key = $oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid.$oSubQuestion->title."#1";
                        if($byEm) {
                            $key = $oQuestion->title."_".$oSubQuestion->title."_1";
                        }
                        $aListData['data'][$key] = "[{$oQuestion->title}_{$oSubQuestion->title}_1] (".viewHelper::flatEllipsizeText($oQuestion->question,true,30,'…',0.7).") (".gT("Scale 2").") ".viewHelper::flatEllipsizeText($oSubQuestion->question,true,40,'…',0.6);
                        $aListData['options'][$key] = array_merge($aDefaultOptions,array(
                            'data-content'=>viewHelper::purified($oQuestion->question).'<hr>'.gT("Scale 2").'<hr>'.viewHelper::purified($oSubQuestion->question),
                            'data-title'=>$oQuestion->title."_".$oSubQuestion->title."_1",
                            'title'=>viewHelper::flatEllipsizeText($oQuestion->question)."\n".gT("Scale 2")."\n".viewHelper::flatEllipsizeText($oSubQuestion->question),
                        ));
                    }
                }
                break;
            /* Double column question (array text/number) */
            case ';':
            case ':':
                $oSubQuestionsY = Question::model()->findAll(array(
                    'select'=>'title,question',
                    'condition'=>"sid=:sid and language=:language and parent_qid=:qid and scale_id=0",
                    'order'=>'question_order asc',
                    'params'=>array(":sid"=>$oQuestion->sid,":language"=>$language,":qid"=>$oQuestion->qid),
                ));
                if($oSubQuestionsY) {
                   
                    foreach($oSubQuestionsY as $oSubQuestionY) {
                        $oSubQuestionsX = Question::model()->findAll(array(
                            'select'=>'title,question',
                            'condition'=>"sid=:sid and language=:language and parent_qid=:qid and scale_id=1",
                            'order'=>'question_order asc',
                            'params'=>array(":sid"=>$oQuestion->sid,":language"=>$language,":qid"=>$oQuestion->qid),
                        ));
                        if($oSubQuestionsX) {
                            foreach($oSubQuestionsX as $oSubQuestionX) {
                                $key = $oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid.$oSubQuestionY->title."_".$oSubQuestionX->title;
                                if($byEm) {
                                    $key = $oQuestion->title.$oSubQuestionY->title."_".$oSubQuestionX->title;
                                }
                                $aListData['data'][$key] = "[{$oQuestion->title}_{$oSubQuestionY->title}_{$oSubQuestionX->title}] (".viewHelper::flatEllipsizeText($oQuestion->question,true,30,'…',0.7).") ".viewHelper::flatEllipsizeText($oSubQuestionY->question,true,40,'…',0.6)." - ".viewHelper::flatEllipsizeText($oSubQuestionX->question,true,40,'…',0.6);
                                $aListData['options'][$key] = array_merge($aDefaultOptions,array(
                                    'data-content'=>viewHelper::purified($oQuestion->question).'<hr>'.viewHelper::purified($oSubQuestionY->question).'<hr>'.viewHelper::purified($oSubQuestionX->question),
                                    'data-title'=>$oQuestion->title.$oSubQuestionY->title."_".$oSubQuestionX->title,
                                    'title'=>viewHelper::purified($oQuestion->question)."\n".viewHelper::flatEllipsizeText($oSubQuestionY->question)."\n".viewHelper::flatEllipsizeText($oSubQuestionX->question),
                                ));
                            }
                        }
                    }
                }
                break;
            case 'X':
                // Unselectable
                break;
            case 'R': // Ranking
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
                    $key = $oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid.$count;
                    if($byEm) {
                        $key = $oQuestion->title."_".$count;
                    }
                    $aListData['data'][$key] = "[{$oQuestion->title}_{$count}] ".viewHelper::flatEllipsizeText($oQuestion->question,true,30,'…',0.7)." (".sprintf(gT("Rank %s"),$count).")";
                    $aListData['options'][$key] = array_merge($aDefaultOptions,array(
                        'data-content'=>viewHelper::purified($oQuestion->question).'<hr>'.sprintf(gT("Rank %s"),$count),
                        'data-title'=>$oQuestion->title."_".$count,
                        'title'=>viewHelper::purified($oQuestion->question)."\n".sprintf(gT("Rank %s"),$count),
                    ));
                }
                break;
            case '|': // Upload
                $key = $oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid;
                if($byEm) {
                    $key = $oQuestion->title;
                }
                $aListData['data'][$key] = "[{$oQuestion->title}] ".viewHelper::flatEllipsizeText($oQuestion->question,true,30,'…',0.7);
                $aListData['options'][$key] = array_merge($aDefaultOptions,array(
                    'data-content'=>viewHelper::purified($oQuestion->question),
                    'data-title'=>$oQuestion->title,
                    'title'=>viewHelper::purified($oQuestion->question),
                ));
                $key = $oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid."_filecount";
                $aListData['data'][$key] = "[{$oQuestion->title}_filecount] ".viewHelper::flatEllipsizeText($oQuestion->question,true,30,'…',0.7);
                $aListData['options'][$key] = array_merge($aDefaultOptions,array(
                    'data-content'=>gT("File count")."<hr>".viewHelper::purified($oQuestion->question),
                    'data-title'=>$oQuestion->title."_filecount",
                    'title'=>gT("File count")."\n".viewHelper::purified($oQuestion->question),
                ));
                break;
                // Upload todo
                break;
            default :
                tracevar($oQuestion->type);
        }
        if(self::allowOther($oQuestion->type) and $oQuestion->other=="Y") {
            $key = $oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid."other";
            if($byEm) {
                $key = $oQuestion->title."_other";
            }
            $aListData['data'][$key] = "[{$oQuestion->title}_other] (".viewHelper::flatEllipsizeText($oQuestion->question,true,30,'…',0.7).") ".gT("Other");
            $aListData['options'][$key] = array_merge($aDefaultOptions,array(
                'data-content'=>viewHelper::purified($oQuestion->question)."<hr>".gT("Other"),
                'data-title'=>$oQuestion->title."_other",
                'title'=>viewHelper::purified($oQuestion->question)."\n".gT("Other"),
            ));
            if($oQuestion->type == "P") { /* Specific with comment … */
                $key = $oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid."other"."comment";
                if($byEm) {
                    $key = $oQuestion->title."_other"."comment";
                }
                $aListData['data'][$key] = "[{$oQuestion->title}_othercomment] (".viewHelper::flatEllipsizeText($oQuestion->question,true,30,'…',0.7).") (".gT("Comments").") ".gT("Other");
                $aListData['options'][$key] = array_merge($aDefaultOptions,array(
                    'data-content'=>viewHelper::purified($oQuestion->question).'<hr>'.gT("Comments")."<hr>".gT("Other"),
                    'data-title'=>$oQuestion->title."_other"."comment",
                    'title'=>viewHelper::flatEllipsizeText($oQuestion->question)."\n".gT("Comments")."\n".gT("Other"),
                ));
            }
        }
        return $aListData;
    }

    /**
     * @param string $type
     * return boolean
     */
    public static function allowOther($type){
        $allowOther = array("L","!","P","M");
        return in_array($type,$allowOther);
    }

    public function getDateFilter($column,$iQid=null) {
        if(empty($this->model)) {
            return false;
        }
        if(!$this->model->filterOnDate) {
            /* Model need to explicit allowing filterOnDate */
            return false;
        }
        if(method_exists($this->model,'getDateFilter')) {
            return $this->model->getDateFilter($column,$iQid);
        }
        $value = empty($this->model->$column) ? null : $this->model->$column;
        return CHtml::dateField(get_class($this->model)."[".$column."]",$value,array("class"=>'form-control input-sm filter-date'));
    }

    /**
     * get the filter for this column
     * @param \Question
     * @param integer $scale
     * @param boolean striped;
     * return array|string|null
     */
    public function getFilter($oQuestion,$scale=0,$strip=true)
    {
        $questionClass= Question::getQuestionClass($oQuestion->type);
        if($questionClass == "date") {
            return $this->getDateFilter($oQuestion->sid."X".$oQuestion->gid."X".$oQuestion->qid,$oQuestion->qid);
        }
        return self::getFixedFilter($oQuestion,$scale,$strip);
    }

    /**
     * get fixed filter for this column
     * @param \Question
     * @param integer $scale
     * @param boolean striped;
     * return array|false|null
     */
    public static function getFixedFilter($oQuestion,$scale=0,$strip=true) {
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
                    return CHtml::listData($answers,'code',function($answers) use ($strip) {
                        if($strip) {
                            return strip_tags(viewHelper::purified($answers->answer));
                        }
                        return viewHelper::purified($answers->answer);
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
                return $this->getFilterLanguage($oQuestion->sid);
            case 'multiple-opt':
            case 'multiple-opt-comments':
                return array(
                    "Y" => gT("Yes"),
                );
            case 'date' :
                return false;
            default:
                return null;
        }
    }

    /**
     * @see
     * @param Object $data current model
     * @param string $column name
     * @return string
     */
    public static function getFreeAnswerValue($data,$column) {
        $name = $column->name;
        if(empty($data->$name)) {
            return "";
        }
        return CHtml::tag("div",array('class'=>'answer-value'),CHtml::encode($data->$name));
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
                $aAnswers = self::getFixedFilter($oQuestion,$scale,false);
                if(isset($aAnswers[$data->$name])) {
                    $answer = $aAnswers[$data->$name];
                    return CHtml::tag("div",array('class'=>'answer-value'),"<code>[".$data->$name."]</code> ".viewHelper::purified($answer));
                }
                return CHtml::tag("div",array('class'=>'answer-value'),CHtml::encode($data->$name));
                break;
        }
    }

    public static function getUploadAnswerValue($data,$column,$iQid,$url) {
        $name = $column->name;
        if(empty($data->$name)) {
            return "";
        }
        $filesData = @json_decode(stripslashes($data->$name), true);
        if(!is_array($filesData)) {
            return "";
        }
        $oQuestion = Question::model()->find("qid = :qid",array(":qid"=>$iQid));
        if($oQuestion->type != "|") {
            return;
        }
        $aQuestionAttributes = \QuestionAttribute::model()->getQuestionAttributes($iQid);
        $elementTag = 'dt';
        $listTag = 'dl';
        if(empty($aQuestionAttributes['show_title']) && empty($aQuestionAttributes['show_comment']) ) {
            $elementTag = 'li';
            $listTag = 'ul';
        }
        $url= @json_decode(base64_decode($url),1);
        $htmlList = array();
        foreach($filesData as $fileData) {
            $index = 0;
            if(isset($fileData['name'])) {
                $element = CHtml::encode(urldecode($fileData['name']));
                if($url) {
                    $params = array_merge($url['params'],array('sid'=>$oQuestion->sid,'srid'=>$data->id,'qid'=>$oQuestion->qid,'fileindex'=>$index));
                    $element = CHtml::link($element,Yii::app()->getController()->createUrl($url['route'],$params),array('download'=>true));
                }
                $htmlElement = CHtml::tag($elementTag,array(),$element);
                if(!empty($aQuestionAttributes['show_title'])) {
                    $title = !empty($fileData['title']) ? $fileData['title'] : '-';
                    $htmlElement .= CHtml::tag("dd",array(),$title);
                }
                if(!empty($aQuestionAttributes['show_comment'])) {
                    $comment = !empty($fileData['comment']) ? $fileData['comment'] : '-';
                    $htmlElement .= CHtml::tag("dd",array(),$comment);
                }
                $htmlList[] = $htmlElement;
            }
        }
        return CHtml::tag("div",array('class'=>'answer-value','title'=>gT("Files")),CHtml::tag($listTag,array('class'=>'file-list'),implode($htmlList)));
    }
    public static function getDateValue($data,$column,$iQid,$surveyid) {
        $name = $column->name;
        if(is_null($data->$name) || $data->$name==="") {
            return "";// disable nullDisplay since no diff between null and not answered $data->$name;
        }
        $dateValue = $data->$name;
        $dateFormatData = getDateFormatData(\SurveyLanguageSetting::model()->getDateFormat($surveyid,Yii::app()->getLanguage()));
        $dateFormat = $dateFormatData['phpdate'];
        $attributeDateFormat = QuestionAttribute::model()->find("qid = :qid and attribute = 'date_format'",array(":qid"=>$iQid));
        if(!empty($attributeDateFormat) && trim($attributeDateFormat->value)) {
            $dateFormat = getPHPDateFromDateFormat($attributeDateFormat->value);
        }
        
        $datetimeobj = \DateTime::createFromFormat('!Y-m-d H:i:s', $dateValue);
        if ($datetimeobj) {
            $dateValue = $datetimeobj->format($dateFormat);
        } else {
            $dateValue = '';
        }
        return $dateValue;
        //~ $this->language;
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

    public function getFilterLanguage($surveyId) {
        $aLanguages = Survey::model()->findByPk($surveyId)->getAllLanguages();
        $aFilterLanguages = array();
        foreach($aLanguages as $language) {
            $aFilterLanguages[$language] = getLanguageNameFromCode($language,false,App()->getLanguage());
        }
        return $aFilterLanguages;
    }

    /**
     * Get the header for question and subquestion
     * @param \Question
     * @param \Question|null first subquestion
     * @param \Question|null second subquestion
     * @param string, tag wrapper for each part
     * @return string
     */
    public static function getExtraHtmlHeader($oQuestion,$oSubQuestion = null, $oSubXQuestion = null,$tag = 'small') {
        $sExtraHtmlHeader = CHTml::tag($tag,array('title'=>viewHelper::purified($oQuestion->question)),viewHelper::flatEllipsizeText($oQuestion->question,true,40,'…',0.6));
        if($oSubQuestion) {
            $sExtraHtmlHeader .= CHTml::tag($tag,array('title'=>viewHelper::purified($oSubQuestion->question)),viewHelper::flatEllipsizeText($oSubQuestion->question,true,40,'…',0.6));
        }
        if($oSubXQuestion) {
            $sExtraHtmlHeader .= CHTml::tag($tag,array('title'=>viewHelper::purified($oSubXQuestion->question)),viewHelper::flatEllipsizeText($oSubXQuestion->question,true,40,'…',0.6));
        }
        return $sExtraHtmlHeader;
    }

    /**
     * return array  with DB column name key and type of data (float,decimal,text,choice, number)
     * @todo : add system with attribute (equation as number, only integer …)
     * @todo : add an attribute to show as
     * @param integer $qid
     * @throw Exception if debug
     * @return array|null
     */
    public function questionTypes($qid) {
        $oQuestion = Question::model()->find("qid=:qid AND language=:language",array(":qid"=>$qid,":language"=>$this->language));
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
        $aColumnsType = array();
        $questionClass= Question::getQuestionClass($oQuestion->type);
        /* Get is forced number */

        switch($questionClass) {
            /* Single text */
            case 'text-short':
            case 'text-long':
            case 'text-huge':
            case 'equation':
                $aColumnsInfo[$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid] = 'text';
                if(self::getAttribute($qid,'numbers_only')) {
                    $aColumnsInfo[$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid] = 'number';
                }
                break;
            case 'choice-5-pt-radio':
            case 'list-radio':
            case 'list-with-comment':
            case 'list-dropdown':
            case 'yes-no':
            case 'gender':
            case 'language':
                $aColumnsInfo[$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid] = 'choice';
                if($oQuestion->type == "O") {
                    $aColumnsInfo[$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid."comment"] = 'text';
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
                if($oSubQuestions) {
                    foreach($oSubQuestions as $oSubQuestion) {
                        $aColumnsInfo[$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid.$oSubQuestion->title] = 'choice';
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
                if($oSubQuestions) {
                    foreach($oSubQuestions as $oSubQuestion) {
                        $aColumnsInfo[$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid.$oSubQuestion->title."#0"] = 'choice';
                        $aColumnsInfo[$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid.$oSubQuestion->title."#1"] = 'choice';
                    }
                }
                break;
            case 'numeric':
                $aColumnsInfo[$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid] = 'decimal';
                break;
            case 'numeric-multi':
                $oSubQuestions = Question::model()->findAll(array(
                    'select'=>'title,question',
                    'condition'=>"sid=:sid and language=:language and parent_qid=:qid",
                    'order'=>'question_order asc',
                    'params'=>array(":sid"=>$oQuestion->sid,":language"=>$language,":qid"=>$oQuestion->qid),
                ));
                if($oSubQuestions) {
                    foreach($oSubQuestions as $oSubQuestion) {
                        $aColumnsInfo[$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid.$oSubQuestion->title] = 'decimal';
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
                if($oSubQuestions) {
                    foreach($oSubQuestions as $oSubQuestion) {
                        $aColumnsInfo[$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid.$oSubQuestion->title] = 'boolean';
                        if($questionClass=='multiple-opt-comments') {
                           $aColumnsInfo[$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid.$oSubQuestion->title.'comment'] = 'text';
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
                if($oSubQuestionsY) {
                    foreach($oSubQuestionsY as $oSubQuestionY) {
                        $oSubQuestionsX = Question::model()->findAll(array(
                            'select'=>'title,question',
                            'condition'=>"sid=:sid and language=:language and parent_qid=:qid and scale_id=1",
                            'order'=>'question_order asc',
                            'params'=>array(":sid"=>$oQuestion->sid,":language"=>$language,":qid"=>$oQuestion->qid),
                        ));
                        if($oSubQuestionsX) {
                            foreach($oSubQuestionsX as $oSubQuestionX) {
                                $aColumnsInfo[$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid.$oSubQuestionY->title."_".$oSubQuestionX->title] = 'text';
                                if($questionClass == 'array-multi-flexi') {
                                    $aColumnsInfo[$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid.$oSubQuestionY->title."_".$oSubQuestionX->title] = 'float';
                                }
                                if(self::getAttribute($qid,'numbers_only')) {
                                    $aColumnsInfo[$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid.$oSubQuestionY->title."_".$oSubQuestionX->title] = 'number';
                                }
                            }
                        }
                    }
                }
                break;
            case 'multiple-short-txt' :
                $oSubQuestions = Question::model()->findAll(array(
                    'select'=>'title,question',
                    'condition'=>"sid=:sid and language=:language and parent_qid=:qid",
                    'order'=>'question_order asc',
                    'params'=>array(":sid"=>$oQuestion->sid,":language"=>$language,":qid"=>$oQuestion->qid),
                ));
                $type = 'text';
                if(self::getAttribute($qid,'numbers_only')) {
                    $type = 'number';
                }
                if($oSubQuestions) {
                    foreach($oSubQuestions as $oSubQuestion) {
                        $aColumnsInfo[$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid.$oSubQuestion->title] = $type;
                    }
                }
                break;
            case 'date':
                $aColumnsInfo[$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid] = 'date';
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
                    $aColumnsInfo[$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid.$count] = 'choice';
                }
                break;
            case 'upload-files':
                $aColumnsInfo[$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid] = 'upload';
                $aColumnsInfo[$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid."_filecount"] = 'integer';
                break;
            case 'boilerplate':
                /* Don't show it*/
                break;
            default:
                tracevar($questionClass);
                //~ if(defined('YII_DEBUG') && YII_DEBUG) {
                    //~ throw new Exception(sprintf('Unknow question type %s.',$oQuestion->type));
                //~ }  
                // NUll
        }
        if(self::allowOther($oQuestion->type) and $oQuestion->other=="Y") {
            $aColumnsInfo[$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid."other"] = 'text';
            if($oQuestion->type == "P") { /* Specific with comment … */
                $aColumnsInfo[$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid."othercomment"] = 'text';
            }
        }
        return $aColumnsInfo;
    }

    /**
     * get value of an attribute
     * @param integer $qid
     * @param $name
     * return null|string
     */
    private static function getAttribute($qid,$name)
    {
        $AttributeCriteria = new \CDbCriteria;
        $AttributeCriteria->select = 'value';
        $AttributeCriteria->compare('qid',$qid);
        $AttributeCriteria->compare('attribute',$name);
        $oAttribute = QuestionAttribute::model()->find($AttributeCriteria);
        if(empty($oAttribute)) {
            return null;
        }
        return $oAttribute->value;
    }
}
