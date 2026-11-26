# getQuestionInformation devtools

Developer tools to check the plugin helpers against a real LimeSurvey instance. These are not unit tests: the tools only read the database of the instance where they run. The directory is not included in the distributed plugin (see `.gitattributes`).

The tools use the plugin directory that contains them: `helpers/` with LimeSurvey 7 and up, `legacy-pre7/helpers/` with LimeSurvey 4 to 6. The plugin does not need to be installed in the instance.

## Usage

```
devtools/run.sh <LimeSurvey directory> <action> <arguments>
```

Set `XDEBUG_MODE=off` before `bench`: Xdebug slows PHP down a lot.

### check : consistency with LimeSurvey core

```
devtools/run.sh /var/www/limesurvey check <sid> [<sid> …]
```

For each survey (survey must be active), compare the result of each helper function with the response table and the core field map:

- `surveyCodeHelper::getAllQuestions`: all question columns of response table, same expression codes as core, same order
- `surveyCodeHelper::getColumnName`: from code and from column name
- `surveyAnswers::getAllQuestionsAnswers`: columns, order, `answers` set for answer, checkbox and ranking type
- `surveyColumnsInformation`: `allQuestionsColumns`, `allQuestionsType`, `allQuestionListData` (column and expression code keys)
- grid value of each column for the 20 first responses: no PHP error

Text display questions are not checked: their empty column is voluntary excluded by the plugin.

The last line gives the number of errors: `==== ERRORS: 0`.

### bench : SQL requests and time

```
XDEBUG_MODE=off devtools/run.sh /var/www/limesurvey bench <sid> <function>
```

`<function>` is one of `getAllQuestions`, `getColumnName`, `getAllQuestionsAnswers`, `allQuestionsColumns`, `allQuestionsType`, `allQuestionListData`. Run one function by command: the static caches of the plugin are then empty, as at the first call of a page.

### dump and compare : compare two versions of the plugin

```
devtools/run.sh /var/www/limesurvey dump <sid> /tmp/dump/old/<sid>.json
# change the plugin code (other branch, commit …)
devtools/run.sh /var/www/limesurvey dump <sid> /tmp/dump/new/<sid>.json
python3 -I devtools/compare.py /tmp/dump [<number of differences shown, default 3>]
```

`dump` writes the full result of the helper functions as JSON. `compare.py` compares each file of `old/` with the file of same name in `new/`: added or removed keys, order changes and value differences.

## Requirements

- PHP CLI with access to the LimeSurvey database
- Python 3 for `compare.py`
