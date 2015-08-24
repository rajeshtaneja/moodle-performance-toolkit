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

use moodlehq\performancetoolkit\toolkit_util;
use Symfony\Component\Yaml\Yaml as symfonyyaml;

/**
 * Utils for performance-related stuff
 *
 * @package    moodlehq_performancetoolkit_testplangenerator
 * @copyright  2015 Rajesh Taneja <rajesh@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Init/reset utilities for Performance test site.
 *
 * @package   moodlehq_performancetoolkit_sitegenerator
 * @copyright 2015 Rajesh Taneja
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class util {

    use toolkit_util;
    /**
     * Create generator_dir where all feature and context will be saved.
     *
     * @return string
     * @throws \coding_exception
     * @throws \invalid_dataroot_permissions
     */
    public static function get_tool_dir() {
        $dir = self::get_performance_dir() . DIRECTORY_SEPARATOR . 'testplangenerator';

        // Create dir if required.
        if (!is_dir($dir)) {
            make_writable_directory($dir, true);
        }
        return $dir;
    }

    /**
     * Return config value for generator.
     *
     * @return array.
     */
    public static function get_config() {
        global $CFG;
        if (file_exists($CFG->dirroot . DIRECTORY_SEPARATOR . 'testplan.json')) {
            $jsonconfig = file_get_contents($CFG->dirroot . DIRECTORY_SEPARATOR . 'testplan.json');
        } else {
            $jsonconfig = file_get_contents(__DIR__ . '/../testplan.json-dist');
        }
        return json_decode($jsonconfig, true);
    }

    /**
     * Create feature contents and return contents.
     *
     * @param string $featurename resource to create.
     * @param string $sitesize size of site to create
     * @return bool|string
     */
    protected static function get_feature_contents($featurename, $sitesize) {

        $generatorconfig = self::get_feature_config();

        $featurepath = self::get_feature_path($featurename);

        if (empty($featurepath)) {
            return false;
        }

        // Create feature file for creating resource.
        $data = file_get_contents($featurepath);

        if (empty($generatorconfig[$featurename]['scenario_outline'])) {
            return $data;
        }

        $scenariooutlineconfig = $generatorconfig[$featurename]['scenario_outline'];

        // Get count for scenario_outline example and unset it.
        if (!isset($scenariooutlineconfig['count'])) {
            self::performance_exception("Reference counter is not set for ");
        }

        $examplecount = $scenariooutlineconfig['count'];
        $scenariooutlineconfig['count'] = null;
        unset($scenariooutlineconfig['count']);

        // Check if reference or actual value passed.
        if (isset($examplecount[$sitesize])) {
            $scenarioreferencecounter = $examplecount[$sitesize];
        } else {
            $scenarioreferencecounter = $generatorconfig;
            foreach ($examplecount as $value) {
                $scenarioreferencecounter = $scenarioreferencecounter[$value];
            }
            if (!empty($scenarioreferencecounter[$sitesize])) {
                $scenarioreferencecounter = $scenarioreferencecounter[$sitesize];
            } else {
                self::performance_exception("Invalid refrence count for example passed: " . $examplecount);
            }
        }

        $replacementparams = array_keys($scenariooutlineconfig);

        $data .= "    Examples:\n    | ";

        // Write paramters to replace.
        foreach ($replacementparams as $param) {
            $data .= $param ." |";
        }

        // Write data.
        $count = 1;
        for ($i = 1; $i <= $scenarioreferencecounter; $i++) {
            $data .= PHP_EOL."    |";
            foreach ($replacementparams as $param) {
                $data .= " " . str_replace('#!count!#', $count, $scenariooutlineconfig[$param]) . " |";
            }
            $data .= PHP_EOL;
            $count++;
        }
        return $data;
    }
}