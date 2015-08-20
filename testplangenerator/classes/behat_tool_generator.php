<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Data generators for acceptance testing.
 *
 * @package   core_generator
 * @copyright 2015 rajesh Taneja
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

global $CFG;

require_once(__DIR__.'/../../../../../lib/tests/behat/behat_data_generators.php');

use Behat\Gherkin\Node\TableNode as TableNode;
use Behat\Behat\Exception\PendingException as PendingException;
use moodlehq\performancetoolkit\sitegenerator\generator as moodle_generator;

/**
 * Class containing bulk steps for setting up site for performance testing.
 *
 * @package   core_generator
 * @copyright 2015 rajesh Taneja
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_tool_generator extends behat_base {

    /**
     * Start capturing http request by browsermobproxy.
     *
     * @Given /^I start capturing http requests$/
     */
    public function start_capture_request() {

    }

    /**
     * Stop capturing http request by browsermobproxy.
     *
     * @Given /^I stop capturing http requests$/
     */
    public function stop_capture_request() {

    }
}
