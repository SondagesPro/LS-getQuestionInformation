# getQuestionInformation plugin for LimeSurvey. #

A collections of tools for opther plugin : have information about all questions in survey.

This plugin is compatible and chzcked with version 2.73, 3.22, 5.6, 6.17 and 7.0 of LimeSurvey, can be test on any other version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
See the GNU Affero General Public License for more details.

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
- Copyright © 2018-2026 Denis Chenu <http://sondages.pro>
- Licence : GNU Affero General Public License <https://www.gnu.org/licenses/agpl-3.0.html>
- [Donate](https://support.sondages.pro/open.php?topicId=12), [Liberapay](https://liberapay.com/SondagesPro/), [OpenCollective](https://opencollective.com/sondagespro)
