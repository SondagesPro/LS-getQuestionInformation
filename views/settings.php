<div class="row">
    <div class="col-lg-12 content-right">
        <h3>getQuestionInformation test system</h3>
        <h4>columToCode</h4>
        <code>$columToCode = \getQuestionInformation\helpers\surveyCodeHelper::getAllQuestions($surveyId,App()->getLanguage());</code>
        <pre>
            <?= CHtml::encode(print_r($columToCode, true)) ?>
        </pre>
        <h4>allQuestionsColumns</h4>
        <code>$allQuestionsColumns = \getQuestionInformation\helpers\surveyColumnsInformation::getAllQuestionsColumns($surveyId, null, true);</code>
        <pre>
            <?= CHtml::encode(print_r($allQuestionsColumns, true)) ?>
        </pre>
        <h4>allQuestionAnswers</h4>
        <code>$allQuestionAnswers =  \getQuestionInformation\helpers\surveyAnswers::getAllQuestionsAnswers($surveyId, null);</code>
        <pre>
            <?= CHtml::encode(print_r($allQuestionAnswers, true)) ?>
        </pre>
        <h4>allQuestionListData</h4>
        <code>$allQuestionListData  = \getQuestionInformation\helpers\surveyColumnsInformation::getAllQuestionListData($surveyId, null);</code>
        <pre>
            <?= CHtml::encode(print_r($allQuestionListData, true)) ?>
        </pre>
    </div>
</div>
