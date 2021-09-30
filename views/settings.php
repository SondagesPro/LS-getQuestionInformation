<div class="row">
    <div class="col-lg-12 content-right">
        <h3>getQuestionInformation test system</h3>
        <h4>allQuestionsColumns</h4>
        <code>$allQuestionsColumns = \getQuestionInformation\helpers\surveyColumnsInformation::getAllQuestionsColumns($surveyId, null, true);</code>
        <pre>
            <?php print_r($allQuestionsColumns); ?>
        </pre>
        <h4>columToCode</h4>
        <code>$columToCode = \getQuestionInformation\helpers\surveyCodeHelper::getAllQuestions($surveyId,App()->getLanguage());</code>
        <pre>
            <?php print_r($columToCode); ?>
        </pre>
    </div>
</div>
