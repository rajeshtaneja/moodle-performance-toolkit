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

namespace moodlehq\performancetoolkit\testplangenerator;

use moodlehq\performancetoolkit\testplangenerator\util;
use moodlehq\performancetoolkit\testplangenerator\browsermobproxyclient;

/**
 * Utils for performance-related stuff
 *
 * @package    moodlehq_performancetoolkit_testplangenerator
 * @copyright  2015 Rajesh Taneja <rajesh@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->libdir . "/behat/classes/util.php");
require_once($CFG->libdir . "/behat/classes/behat_command.php");

/**
 * Init/reset utilities for Performance test site.
 *
 * @package   moodlehq_performancetoolkit_testplangenerator
 * @copyright 2015 Rajesh Taneja
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class generator {

    /**
     * Create test plan.
     *
     * @param string $proxy proxy url
     * @param string $port (optional) port to be used for proxy server.
     */
    public static function create_test_plan($size, $proxy = "localhost:9090", $port = '') {
        global $CFG, $DB;

        // Check if site is initialised for performance testing.

        // Initialise BrowserMobProxy.
        $browsermobproxy = new browsermobproxyclient($proxy);

        // Use the proxy server url now.
        $proxyurl = $browsermobproxy->create_connection($port);
        echo "Proxy server running at: " . $proxyurl . PHP_EOL;

        // Create behat.yml.
        util::create_test_feature($size, array(), $proxyurl);

        // Check if proxy is working.

        // Run test plan.

        // Close BrowserMobProxy connection.
        $browsermobproxy->close_connection();
    }

}