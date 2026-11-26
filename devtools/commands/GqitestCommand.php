<?php

/**
 * Read-only check of getQuestionInformation helpers against the real response table and core fieldmap
 * Usage : see devtools/README.md
 */
class GqitestCommand extends CConsoleCommand
{
    private $errors = 0;

    private function init_()
    {
        /* Plugin directory : devtools/commands/../.. */
        $base = dirname(__DIR__, 2);
        $path = intval(App()->getConfig('versionnumber')) < 7 ? $base . '/legacy-pre7' : $base;
        Yii::setPathOfAlias('getQuestionInformation', $path);
        Yii::import('application.helpers.common_helper', true);
        Yii::import('application.helpers.viewHelper', true);
        Yii::import('application.helpers.expressions.em_manager_helper', true);
        Yii::app()->setLanguage('en');
        /* LimeSurvey runtime directory is usually writable only by web server user */
        $runtime = sys_get_temp_dir() . '/getQuestionInformation-tests';
        if (!is_dir($runtime)) {
            mkdir($runtime);
        }
        Yii::app()->setRuntimePath($runtime);
    }

    public function run($args)
    {
        $this->init_();
        $action = array_shift($args);
        if ($action === "dump") {
            $this->dump(intval($args[0]), $args[1]);
            return;
        }
        if ($action === 'bench') {
            $this->bench(intval($args[0]), $args[1]);
            return;
        }
        foreach ($args as $sid) {
            $this->checkSurvey(intval($sid));
        }
        echo "\n==== ERRORS: {$this->errors}\n";
    }

    /**
     * One function, cold static cache (own process), count SQL and time
     */
    private function bench($sid, $function)
    {
        $db = Yii::app()->db;
        Survey::model()->findByPk($sid); /* not counted : always done by caller */
        $db->enableProfiling = true;
        $start = microtime(true);
        switch ($function) {
            case 'getAllQuestions':
                $result = \getQuestionInformation\helpers\surveyCodeHelper::getAllQuestions($sid);
                break;
            case 'getAllQuestionsAnswers':
                $result = \getQuestionInformation\helpers\surveyAnswers::getAllQuestionsAnswers($sid);
                break;
            case 'allQuestionsColumns':
                $result = (new \getQuestionInformation\helpers\surveyColumnsInformation($sid))->allQuestionsColumns();
                break;
            case 'allQuestionsType':
                $result = (new \getQuestionInformation\helpers\surveyColumnsInformation($sid))->allQuestionsType();
                break;
            case 'allQuestionListData':
                $sci = new \getQuestionInformation\helpers\surveyColumnsInformation($sid);
                $sci->ByEmCode = true;
                $result = $sci->allQuestionListData()['data'];
                break;
            case 'getColumnName':
                $codes = \getQuestionInformation\helpers\surveyCodeHelper::getAllQuestions($sid);
                $before = $db->getStats()[0];
                $start = microtime(true);
                foreach ($codes as $code) {
                    \getQuestionInformation\helpers\surveyCodeHelper::getColumnName($sid, $code);
                }
                $result = $codes;
                $stats = $db->getStats();
                printf("%-24s %5d SQL %8.1f ms (%d columns)\n", $function, $stats[0] - $before, (microtime(true) - $start) * 1000, count($result));
                return;
            default:
                echo "Unknown $function\n";
                return;
        }
        $stats = $db->getStats();
        printf("%-24s %5d SQL %8.1f ms (SQL %6.1f ms) (%d columns)\n", $function, $stats[0], (microtime(true) - $start) * 1000, $stats[1] * 1000, count($result));
    }

    private function checkOrder($name, array $keys, array $coreOrder)
    {
        $keys = array_values(array_intersect($keys, $coreOrder));
        if ($keys !== array_values(array_intersect($coreOrder, $keys))) {
            $this->fail("$name : order differs from core fieldmap");
        }
    }

    /**
     * Dump all API output as JSON (to compare two versions of the plugin)
     */
    private function dump($sid, $file)
    {
        $sci = new \getQuestionInformation\helpers\surveyColumnsInformation($sid);
        $data = [
            "codes" => \getQuestionInformation\helpers\surveyCodeHelper::getAllQuestions($sid, null, true),
            "answers" => \getQuestionInformation\helpers\surveyAnswers::getAllQuestionsAnswers($sid),
            "columns" => $sci->allQuestionsColumns(),
            "types" => $sci->allQuestionsType(),
            "list" => $sci->allQuestionListData(),
        ];
        $sci->ByEmCode = true;
        $data["listEm"] = $sci->allQuestionListData();
        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function fail($msg)
    {
        $this->errors++;
        echo "  FAIL $msg\n";
    }

    private function checkSurvey($sid)
    {
        echo "\n==== Survey $sid\n";
        $oSurvey = Survey::model()->findByPk($sid);
        $dbColumns = array_keys(Yii::app()->db->schema->getTable($oSurvey->responsesTableName)->columns);

        /* Core : fieldmap entries that are real DB question columns (boilerplate voluntary excluded by plugin) */
        $fieldmap = createFieldMap($oSurvey, 'full', true, false, $oSurvey->language);
        $coreCodes = [];
        foreach ($fieldmap as $fieldname => $field) {
            if (!empty($field['qid']) && $field['type'] !== 'X' && in_array($fieldname, $dbColumns, true)) {
                $coreCodes[$fieldname] = viewHelper::getFieldCode($field, ['LEMcompat' => true]);
            }
        }
        $questionDbColumns = array_keys($coreCodes);

        /* surveyCodeHelper::getAllQuestions */
        $codes = \getQuestionInformation\helpers\surveyCodeHelper::getAllQuestions($sid);
        foreach (array_diff(array_keys($codes), $questionDbColumns) as $c) {
            $this->fail("getAllQuestions : $c not in DB");
        }
        foreach (array_diff($questionDbColumns, array_keys($codes)) as $c) {
            $this->fail("getAllQuestions : DB column $c missing");
        }
        foreach ($codes as $column => $code) {
            if (isset($coreCodes[$column]) && $coreCodes[$column] !== $code) {
                $this->fail("getAllQuestions : $column code $code, core say {$coreCodes[$column]}");
            }
        }
        $this->checkOrder("getAllQuestions", array_keys($codes), $questionDbColumns);
        echo "  getAllQuestions : " . count($codes) . " columns checked\n";

        /* surveyCodeHelper::getColumnName */
        foreach ($codes as $column => $code) {
            $found = \getQuestionInformation\helpers\surveyCodeHelper::getColumnName($sid, $code);
            if ($found !== $column) {
                $this->fail("getColumnName($code) = " . var_export($found, true) . " expected $column");
            }
            $found = \getQuestionInformation\helpers\surveyCodeHelper::getColumnName($sid, $column);
            if ($found !== $column) {
                $this->fail("getColumnName($column) = " . var_export($found, true) . " expected $column");
            }
        }
        echo "  getColumnName checked\n";

        /* surveyAnswers */
        $answers = \getQuestionInformation\helpers\surveyAnswers::getAllQuestionsAnswers($sid, null);
        foreach (array_diff(array_keys($answers), $questionDbColumns) as $c) {
            $this->fail("getAllQuestionsAnswers : $c not in DB");
        }
        foreach (array_diff($questionDbColumns, array_keys($answers)) as $c) {
            $this->fail("getAllQuestionsAnswers : DB column $c missing");
        }
        foreach ($answers as $column => $info) {
            if (in_array($info['type'], ['answer', 'checkbox', 'ranking']) && !isset($info['answers'])) {
                $this->fail("getAllQuestionsAnswers : $column type {$info['type']} without answers");
            }
        }
        $this->checkOrder("getAllQuestionsAnswers", array_keys($answers), $questionDbColumns);
        echo "  getAllQuestionsAnswers : " . count($answers) . " columns\n";

        /* surveyColumnsInformation */
        $sci = new \getQuestionInformation\helpers\surveyColumnsInformation($sid, null);
        $gridColumns = $sci->allQuestionsColumns();
        foreach ($gridColumns as $key => $column) {
            if ($key !== $column['name'] || !in_array($column['name'], $questionDbColumns, true)) {
                $this->fail("allQuestionsColumns : key $key name {$column['name']} not in DB");
            }
        }
        foreach (array_diff($questionDbColumns, array_keys($gridColumns)) as $c) {
            $this->fail("allQuestionsColumns : DB column $c missing");
        }
        $this->checkOrder("allQuestionsColumns", array_keys($gridColumns), $questionDbColumns);
        echo "  allQuestionsColumns : " . count($gridColumns) . " columns\n";

        $types = $sci->allQuestionsType();
        foreach (array_diff(array_keys($types), $questionDbColumns) as $c) {
            $this->fail("allQuestionsType : $c not in DB");
        }
        foreach (array_diff($questionDbColumns, array_keys($types)) as $c) {
            $this->fail("allQuestionsType : DB column $c missing");
        }
        $this->checkOrder("allQuestionsType", array_keys($types), $questionDbColumns);
        echo "  allQuestionsType : " . count($types) . " columns\n";

        $listData = $sci->allQuestionListData();
        foreach (array_diff(array_keys($listData['data']), $questionDbColumns) as $c) {
            $this->fail("allQuestionListData : $c not in DB");
        }
        foreach (array_diff($questionDbColumns, array_keys($listData['data'])) as $c) {
            $this->fail("allQuestionListData : DB column $c missing");
        }
        $this->checkOrder("allQuestionListData", array_keys($listData["data"]), $questionDbColumns);
        $sci->ByEmCode = true;
        $listDataEm = $sci->allQuestionListData();
        foreach (array_diff(array_keys($listDataEm['data']), array_values($codes)) as $c) {
            $this->fail("allQuestionListData ByEmCode : $c not a getAllQuestions code");
        }
        echo "  allQuestionListData : " . count($listData['data']) . " / " . count($listDataEm['data']) . " (EM)\n";

        /* Rendering of grid value for each column with all responses : no PHP error */
        $rendered = 0;
        foreach (Response::model($sid)->findAll(['order' => 'id', 'limit' => 20]) as $oResponse) {
            foreach ($gridColumns as $name => $column) {
                if (empty($column['value'])) {
                    continue;
                }
                $columnObject = (object) ['name' => $column['name']];
                $code = str_replace('$this', '$columnObject', $column['value']);
                $data = $oResponse;
                try {
                    eval('return ' . $code . ';'); // test only : value expression from the helper itself
                    $rendered++;
                } catch (\Throwable $e) {
                    $this->fail("render $name response {$oResponse->id} : " . $e->getMessage());
                }
            }
        }
        echo "  rendered $rendered values\n";
    }
}
