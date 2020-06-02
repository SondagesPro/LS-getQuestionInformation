<?php
/**
 * Tool for others plugins
 *
 * @author Denis Chenu <denis@sondages.pro>
 * @copyright 2018-2020 Denis Chenu <http://www.sondages.pro>
 * @license GPL v3
 * @version 1.8.0
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

class getQuestionInformation extends PluginBase {

    static protected $description = 'A tool for other plugins';
    static protected $name = 'getQuestionInformation';
    
    public function init() {
        Yii::setPathOfAlias(get_class($this), dirname(__FILE__));
    }
}
