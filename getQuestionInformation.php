<?php
/**
 * Tool for others plugins
 *
 * @author Denis Chenu <denis@sondages.pro>
 * @copyright 2018-2023 Denis Chenu <http://www.sondages.pro>
 * @license GPL v3
 * @version 3.0;
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

class getQuestionInformation extends PluginBase
{

    static protected $description = 'A tool for other plugins';
    static protected $name = 'getQuestionInformation';

    public function init()
    {
        if (App()->getConfig('debug') || App()->getConfig('getQuestionInformationShowToolLink')) {
            $this->subscribe('beforeToolsMenuRender');
        }
        if (intval(App()->getConfig('versionnumber')) < 4) {
            Yii::setPathOfAlias(get_class($this), dirname(__FILE__) . DIRECTORY_SEPARATOR . 'legacy');
            App()->setConfig('getQuestionInformationAPI', \getQuestionInformation\Utilities::API);
            return;
        }
        Yii::setPathOfAlias(get_class($this), dirname(__FILE__));
        App()->setConfig('getQuestionInformationAPI', \getQuestionInformation\Utilities::API);
    }

    /**
     * Main function to test setting
     * @param int $surveyId Survey id
     *
     * @return string
     */
    public function actionSettings($surveyId)
    {
        $columToCode = \getQuestionInformation\helpers\surveyCodeHelper::getAllQuestions($surveyId);
        $allQuestionsColumns = \getQuestionInformation\helpers\surveyColumnsInformation::getAllQuestionsColumns($surveyId, null, true);
        $allQuestionAnswers = \getQuestionInformation\helpers\surveyAnswers::getAllQuestionsAnswers($surveyId, null);
        $allQuestionListData = \getQuestionInformation\helpers\surveyColumnsInformation::getAllQuestionListData($surveyId, null);
        $aData = array(
            'columToCode' => $columToCode,
            'allQuestionsColumns' => $allQuestionsColumns,
            'allQuestionAnswers' => $allQuestionAnswers,
            'allQuestionListData' => $allQuestionListData
        );
        $content = $this->renderPartial('settings', $aData, true);
        return $content;
    }

    /**
     * see beforeToolsMenuRender event
     *
     * @return void
     */
    public function beforeToolsMenuRender()
    {
        if (!$this->getEvent()) {
            throw new CHttpException(403);
        }
        $event = $this->getEvent();
        $surveyId = $event->get('surveyId');
        $oSurvey = Survey::model()->findByPk($surveyId);
        if (Permission::model()->hasSurveyPermission($surveyId, 'surveycontent', 'read')) {
            $aMenuItem = array(
                'label' => 'Check question information',
                'iconClass' => 'fa fa-object-group ',
                'href' => Yii::app()->createUrl(
                    'admin/pluginhelper',
                    array(
                        'sa' => 'sidebody',
                        'plugin' => get_class($this),
                        'method' => 'actionSettings',
                        'surveyId' => $surveyId
                    )
                ),
            );
            if (class_exists("\LimeSurvey\Menu\MenuItem")) {
                $menuItem = new \LimeSurvey\Menu\MenuItem($aMenuItem);
            } else {
                $menuItem = new \ls\menu\MenuItem($aMenuItem);
            }
            $event->append('menuItems', array($menuItem));
        }
    }

}