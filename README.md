# getQuestionInformation plugin for LimeSurvey. #

A collections of tools for opther plugin : have information about all questions in survey.

This plugiun is compatible with version 2.73 and 3.22 of LimeSurvey, can be test on any other 2.X and 3.X version.

**This plugin is not compatible with LimeSurvey 4.X version**

## Installation

### Via GIT
- Go to your LimeSurvey Directory
- Clone in plugins/getQuestionInformation directory : `git clone https://gitlab.com/SondagesPro/coreAndTools/getQuestionInformation.git`

### Via ZIP dowload
- Get the file [getQuestionInformation.zip](https://extensions.sondages.pro/IMG/auto/getQuestionInformation.zip)
- Extract : `unzip getQuestionInformation.zip`
- Move the directory to plugins/ directory inside LimeSurvey

## Usage

- `\getQuestionInformation\helpers\surveyCodeHelper::getAllQuestions($surveyId)` : get array of question column : key are column, value are code for expression
- `\getQuestionInformation\helpers\surveyAnswers::getAllQuestionsAnswers($surveyId,$language)` : get array of question answer type :
    - key are column name
    - value are an array with
        - `type` : freetext, answer, decimal, datetime, float or upload
        - `answers` :  the answers list code => text in language
- `\getQuestionInformation\helpers\surveyColumnsInformation($surveyId,$language)` give 2 primary function
    - `allQuestionListData` used for dropdown selector in plugins
    - `questionColumns` used for Yii grid 

## Contribute and issue

Contribution are welcome, for patch and issue : use [gitlab]( https://gitlab.com/SondagesPro/coreAndTools/getQuestionInformation).

## Home page & Copyright
- HomePage <http://extensions.sondages.pro/>
- Copyright © 2018-2020 Denis Chenu <http://sondages.pro>
- Licence : GNU Affero General Public License <https://www.gnu.org/licenses/agpl-3.0.html>
