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

namespace moodlehq\performancetoolkit\sitegenerator;
use moodlehq\performancetoolkit\sitegenerator\installer as moodle_installer;
use Symfony\Component\Yaml\Yaml as symfonyyaml;

/**
 * Utils for performance-related stuff
 *
 * @package    moodlehq_performancetoolkit_sitegenerator
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

    /**
     * @var list of exit codes.
     */
    const PERFORMANCE_EXITCODE_CONFIG = "err_config";
    const PERFORMANCE_EXITCODE_INSTALL = "err_install";
    const PERFORMANCE_EXITCODE_INSTALLED = "err_installed";
    const PERFORMANCE_EXITCODE_REINSTALL = "err_reinstall";

    /**
     * Install moodle site.
     *
     * @return bool|string
     * @throws coding_exception
     */
    public static function install_site() {
        return moodle_installer::install_site();
    }

    /**
     * Save state of current site. Dataroot and database.
     *
     * @param string $statename
     */
    public static function store_site_state($statename = "default") {
        echo "Saving database state" . PHP_EOL;
        // Save database and dataroot state, before proceeding.
        moodle_installer::store_database_state($statename);

        echo "Saving dataroot state" . PHP_EOL;
        moodle_installer::store_data_root_state($statename);

        echo "Site state is stored at " . self::get_performance_generator_dir() . DIRECTORY_SEPARATOR . $statename
            . ".*" . PHP_EOL;
    }

    /**
     * Restore state of current site. Dataroot and database.
     *
     * @param string $statename
     */
    public static function restore_site_state($statename = "default") {
        // Restore database and dataroot state, before proceeding.
        echo "Restoring database state" . PHP_EOL;
        if (!moodle_installer::restore_database_state($statename)) {
            self::performance_exception("Error restoring state db: " . $statename);
        }

        echo "Restoring dataroot state" . PHP_EOL;
        if (!moodle_installer::restore_dataroot($statename)) {
            self::performance_exception("Error restoring state data: " . $statename);
        }

        echo "Site restored to $statename state" . PHP_EOL;
    }

    /**
     * Return directory in which performance data is saved.
     *
     * @static
     * @param bool $create if ture then it will also create directory if not present.
     * @return string
     */
    public static function get_performance_dir($create = false) {
        global $CFG;

        if (empty($CFG->performance_dataroot)) {
            self::performance_exception("\$CFG->performance_dataroot is not set.");
        }

        $dir = $CFG->performance_dataroot;

        // Create dir if required.
        if ($create && !is_dir($dir)) {
            make_writable_directory($dir, true);
        }

        return $dir;
    }

    /**
     * Create generator_dir where all feature and context will be saved.
     *
     * @return string
     * @throws \coding_exception
     * @throws \invalid_dataroot_permissions
     */
    public static function get_performance_generator_dir() {
        $dir = self::get_performance_dir() . DIRECTORY_SEPARATOR . 'generator';

        // Create dir if required.
        if (!is_dir($dir)) {
            make_writable_directory($dir, true);
        }
        return $dir;
    }

    /**
     * Drops dataroot and remove test database tables
     * @throws coding_exception
     * @return void
     */
    public static function drop_site() {

        if (!defined('PERFORMANCE_SITE_GENERATOR')) {
            self::performance_exception('This method can be only used by performance site generator.');
        }

        moodle_installer::drop_database(true);
        moodle_installer::drop_dataroot();
        moodle_installer::drop_generator_data();
    }

    /**
     * Checks if $CFG->wwwroot is available
     *
     * @return bool
     */
    public static function is_server_running() {
        global $CFG;

        $request = new curl();
        $request->get($CFG->wwwroot);

        if ($request->get_errno() === 0) {
            return true;
        }
        return false;
    }

    /**
     * Does this site (db and dataroot) appear to be used for production?
     * We try very hard to prevent accidental damage done to production servers!!
     *
     * @static
     * @return bool
     */
    public static function is_performance_site() {
        global $DB;

        if (!file_exists(self::get_performance_generator_dir() . DIRECTORY_SEPARATOR . 'performancesite.txt')) {
            // This is already tested in bootstrap script,
            // but anyway presence of this file means that site is enabled for performance testing.
            return false;
        }

        $tables = $DB->get_tables(false);
        if ($tables) {
            if (!$DB->get_manager()->table_exists('config')) {
                return false;
            }
            if (!get_config('core', 'perfromancesitehash')) {
                return false;
            }
        }

        return true;
    }

    /**
     * Returns whether test database and dataroot were created using the current version codebase
     *
     * @return bool
     */
    public static function is_site_data_updated() {

        $datarootpath = self::get_performance_dir();

        if (!is_dir($datarootpath)) {
            return 1;
        }

        if (!file_exists($datarootpath . '/versionshash.txt')) {
            return 1;
        }

        $hash = \core_component::get_all_versions_hash();
        $oldhash = file_get_contents($datarootpath . '/versionshash.txt');

        if ($hash !== $oldhash) {
            return false;
        }

        $dbhash = get_config('core', 'perfromancesitehash');
        if ($hash !== $dbhash) {
            return false;
        }

        return true;
    }

    /**
     * Checks whether the test database and dataroot is ready
     * Stops execution if something went wrong
     * @throws moodle_exception
     * @return void
     */
    protected static function test_environment_problem() {
        global $DB;

        if (!defined('PERFORMANCE_SITE_GENERATOR')) {
            self::performance_exception('This method can be only used by performance site generator.');
        }

        $tables = $DB->get_tables(false);
        if (empty($tables)) {
            return self::PERFORMANCE_EXITCODE_INSTALL;
        }

        if (!self::is_site_data_updated()) {
            return self::PERFORMANCE_EXITCODE_REINSTALL;
        }

        // No error.
        return self::PERFORMANCE_EXITCODE_INSTALLED;
    }

    /**
     * Checks if required config vaues are set.
     *
     * @return int Error code or 0 if all ok
     */
    public static function check_setup_problem() {
        global $CFG;

        // No empty values.
        if (empty($CFG->dataroot) || empty($CFG->prefix) || empty($CFG->wwwroot)) {
            return self::PERFORMANCE_EXITCODE_CONFIG;
        }

        if (empty($CFG->dataroot) || !is_dir($CFG->dataroot) || !is_writable($CFG->dataroot)) {
            return self::PERFORMANCE_EXITCODE_CONFIG;
        }

        return 0;
    }

    /**
     * Enables test mode
     *
     * It uses CFG->dataroot/performance
     *
     * Starts the test mode checking the composer installation and
     * the test environment and updating the available
     * features and steps definitions.
     *
     * Stores a file in dataroot/performance to allow Moodle to switch
     * to the test environment when using cli-server.
     *
     * @param string $sitesize size of site
     * @param string $optionaltestdata (optional), replace default template with this value.
     * @throws moodle_exception
     * @return void
     */
    public static function enable_performance_sitemode($sitesize, $optionaltestdata = '') {
        global $CFG;

        if (!defined('PERFORMANCE_SITE_GENERATOR')) {
            self::performance_exception('This method can be only used by performance site generator.');
        }

        // Checks the behat set up and the PHP version.
        if ($errorcode = self::check_setup_problem()) {
            return $errorcode;
        }

        // Check that test environment is correctly set up.
        if (self::test_environment_problem() !== self::PERFORMANCE_EXITCODE_INSTALLED) {
            return $errorcode;
        }

        // Make it a performance site, we have already checked for tables.
        if (!self::is_performance_site() && empty(get_config('core', 'perfromancesitehash'))) {
            moodle_installer::store_versions_hash();
        }

        self::get_performance_dir(true);

        $release = null;
        require("$CFG->dirroot/version.php");

        $contents = "release=".$release.PHP_EOL;
        if ($hash = self::get_performance_tool_hash()) {
            $contents .= "hash=" . $hash . PHP_EOL;
        }

        $filepath = self::get_performance_generator_dir() . DIRECTORY_SEPARATOR . 'performancesite.txt';
        if (!file_put_contents($filepath, $contents)) {
            echo 'File ' . $filepath . ' can not be created' . PHP_EOL;
            exit(1);
        }

        self::create_test_feature($sitesize, $optionaltestdata);

       return 0;
    }

    /**
     * Try to get current git hash of the performance-tool-kit
     * @return string null if unknown, sha1 hash if known
     */
    public static function get_performance_tool_hash() {

        // This is a bit naive, but it should mostly work for all platforms.

        if (!file_exists(__DIR__ . "/../../.git/HEAD")) {
            return null;
        }

        $headcontent = file_get_contents(__DIR__ . "/../../.git/HEAD");
        if ($headcontent === false) {
            return null;
        }

        $headcontent = trim($headcontent);

        // If it is pointing to a hash we return it directly.
        if (strlen($headcontent) === 40) {
            return $headcontent;
        }

        if (strpos($headcontent, 'ref: ') !== 0) {
            return null;
        }

        $ref = substr($headcontent, 5);

        if (!file_exists(__DIR__ . "/../../.git/$ref")) {
            return null;
        }

        $hash = file_get_contents(__DIR__ . "/../../.git/$ref");

        if ($hash === false) {
            return null;
        }

        $hash = trim($hash);

        if (strlen($hash) != 40) {
            return null;
        }

        return $hash;
    }

    /**
     * Returns the status of the behat test environment
     *
     * @return int Error code
     */
    public static function get_site_status() {

        if (!defined('PERFORMANCE_SITE_GENERATOR')) {
            self::performance_exception('This method can be only used by performance site generator.');
        }

        // Checks the behat set up and the PHP version, returning an error code if something went wrong.
        if ($errorcode = self::check_setup_problem()) {
            return $errorcode;
        }

        // Check that test environment is correctly set up, stops execution.
        return self::test_environment_problem();
    }

    /**
     * Disables test mode
     * @throws coding_exception
     * @return void
     */
    public static function disable_performance_sitemode() {

        if (!defined('PERFORMANCE_SITE_GENERATOR')) {
            self::performance_exception('This method can be only used by performance site generator.');
        }

        if (!self::is_performance_site()) {
            echo "Test environment was already disabled\n";
        } else {
            if (file_exists(self::get_performance_generator_dir() . DIRECTORY_SEPARATOR . 'performancesite.txt')) {
                unlink(self::get_performance_generator_dir() . DIRECTORY_SEPARATOR . 'performancesite.txt');
            }
        }
    }

    /**
     * Return config value for generator.
     *
     * @return array.
     */
    public static function get_config() {
        global $CFG;
        if (file_exists($CFG->dirroot . DIRECTORY_SEPARATOR . 'generator-config.json')) {
            $jsonconfig = file_get_contents($CFG->dirroot . DIRECTORY_SEPARATOR . 'generator-config.json');
        } else {
            $jsonconfig = file_get_contents(__DIR__ . '/../generator-config.json-dist');
        }
        return json_decode($jsonconfig, true);
    }

    /**
     * Replace required values in feature with the config.
     *
     * @param array $generatorconfig generator config with all config values.
     * @param string $featurename feature name if know.
     * @param string $sitesize site size
     * @param string $data raw feature file data.
     * @return $data modified feature data with replaced values from config.
     *
     * @throws \moodle_exception
     */
    public static function replace_values_in_feature($generatorconfig, $featurename, $sitesize, $data) {
        // Replace given values, which is quick way to replace values in feature file.
        if (!empty($generatorconfig[$featurename]['scenario'])) {
            foreach ($generatorconfig[$featurename]['scenario'] as $search => $replace) {
                if (isset($replace[$sitesize])) {
                    $replace = $replace[$sitesize];
                } else if (is_array($replace)) {
                    $size = $generatorconfig;
                    foreach ($replace as $value) {
                        $size = $size[$value];
                    }
                    if (isset($size[$sitesize])) {
                        $replace = $size[$sitesize];
                    } else {
                        self::performance_exception("Invalid size passed for feature $featurename, param: " . $search);
                    }
                } else if (is_number($replace)) {
                    $replace = (int) $replace;
                } else {
                    self::performance_exception("Invalid size passed for feature $featurename, param: " . $search);
                }
                $data = str_replace('#!'.$search.'!#', $replace, $data);
            }
        }

        // Search and replace any other refrences used.
        preg_match_all('/#!([a-z]*)!#/i', $data, $matches);
        $matches = $matches[1];
        // For each match search and replace the values.
        foreach ($matches as $match) {
            $replace = false;
            foreach ($generatorconfig as $featureconfig => $config) {
                if (isset($config['scenario']) && isset($config['scenario'][$match])) {
                    if (isset($config['scenario'][$match][$sitesize])) {
                        $replace = $config['scenario'][$match][$sitesize];
                    } else if (is_number($config['scenario'][$match])) {
                        $replace = (int) $config['scenario'][$match];
                    } else if (is_array($config['scenario'][$match])) {
                        $replace = $generatorconfig;
                        foreach ($config['scenario'][$match] as $value) {
                            $replace = $replace[$value];
                        }
                        if (isset($replace[$sitesize])) {
                            $replace = $replace[$sitesize];
                        } else {
                            self::performance_exception("Invalid size passed for feature $featurename, param: " . $match);
                        }
                    }
                    // Once found the replacement value, then break.
                    if ($replace !== false) {
                        break;
                    }
                }
            }
            if ($replace === false) {
                self::performance_exception("Replacement value not found: " . $match . " in feature: " . $featurename);
            } else {
                $data = str_replace('#!'.$match.'!#', $replace, $data);
            }
        }

        return $data;
    }

    /**
     * Execption used by performance tool.
     *
     * @param string $msg message in exception.
     * @throws \moodle_exception
     */
    public static function performance_exception($msg) {
        throw new \moodle_exception($msg);
    }

    /**
     * Create feature contents and return contents.
     *
     * @param string $featurename resource to create.
     * @param string $sitesize size of site to create
     * @return bool|string
     */
    public static function get_feature_contents($featurename, $sitesize) {

        $generatorconfig = self::get_config();

        if (!empty($generatorconfig[$featurename]['featurepath'])) {
            $featurepath = $generatorconfig[$featurename]['featurepath'];
        } else {
            $featurepath = __DIR__ . '/../features/' . $featurename . '.feature';
        }

        if (!file_exists($featurepath)) {
            return false;
        }

        // Create feature file for creating resource.
        $data = file_get_contents($featurepath);

        // Replace required values in feature file.
        $data = self::replace_values_in_feature($generatorconfig, $featurename, $sitesize, $data);

        if (empty($generatorconfig[$featurename]['scenario_outline'])) {
            return $data;
        }

        // Get count for scenario_outline example and unset it.
        if (!isset($generatorconfig[$featurename]['scenario_outline']['count'])) {
            self::performance_exception("Reference counter is not set for ");
        }

        $examplecount = $generatorconfig[$featurename]['scenario_outline']['count'];
        $generatorconfig[$featurename]['scenario_outline']['count'] = null;
        unset($generatorconfig[$featurename]['scenario_outline']['count']);
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

        $replacementparams = array_keys($generatorconfig[$featurename]['scenario_outline']);

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
                $data .= " " . str_replace('#!count!#', $count, $generatorconfig[$featurename]['scenario_outline'][$param]) . " |";
            }
            $data .= PHP_EOL;
            $count++;
        }
        return $data;
    }

    /**
     * Create test feature and enable behat config.
     *
     * @param string $sitesize size of site
     * @param array $optionaltestdata (optional), replace default template with this value.
     */
    public static function create_test_feature($sitesize, $optionaltestdata = array()) {
        $generatorfeaturepath = self::get_performance_generator_dir();
        $generatorconfig = self::get_config();
        if (empty($generatorconfig)) {
            self::performance_exception("Check generator config file.");
        }

        // Create test feature file depending on what is given.
        foreach ($generatorconfig as $featuretoadd => $config) {
            if (!isset($optionaltestdata[$featuretoadd])) {
                if ($featurecontents = self::get_feature_contents($featuretoadd, $sitesize)) {
                    $optionaltestdata[$featuretoadd] = $featurecontents;
                } else {
                    echo "Feature file not found for: " . $featuretoadd;
                }
            }
            if (isset($optionaltestdata[$featuretoadd])) {
                $finalfeaturepath = $generatorfeaturepath . DIRECTORY_SEPARATOR . $featuretoadd . '.feature';
                file_put_contents($finalfeaturepath, $optionaltestdata[$featuretoadd]);
            }
        }

        // Update config file.
        self::update_config_file(array_keys($optionaltestdata));
    }

    /**
     * Updates a config file
     *
     * @param  array $featurestoadd list of features to add.
     * @return void
     */
    public static function update_config_file($featurestoadd) {
        global $CFG;

        // Behat must have a separate behat.yml to have access to the whole set of features and steps definitions.
        $configfilepath = self::get_performance_generator_dir() . DIRECTORY_SEPARATOR . 'behat.yml';
        $featurepath = self::get_performance_generator_dir();

        // Gets all the components with features.
        $featureslist = glob("$featurepath/*.feature");
        $features = array();

        foreach ($featurestoadd as $featuretoadd) {
            $feature = preg_grep('/.\/' . $featuretoadd . '\.feature/', $featureslist);
            if (!empty($feature)) {
                if (count($feature) > 1) {
                    echo "Found more then 1 feature for the requested order set: " , $featuretoadd . PHP_EOL;
                }
                $features = array_merge($features, $feature);
            }
        }

        // Gets all the components with steps definitions.
        $stepsdefinitions = array();
        $steps = \behat_config_manager::get_components_steps_definitions();
        if ($steps) {
            foreach ($steps as $key => $filepath) {
                $stepsdefinitions[$key] = $filepath;
            }
        }

        // We don't want the deprecated steps definitions here.
        unset($stepsdefinitions['behat_deprecated']);

        // Remove default hooks.
        unset($stepsdefinitions['behat_hooks']);

        // Add generator contexts.
        $contexts = glob(__DIR__ . "/behat_*.php");
        foreach ($contexts as $context) {
            preg_match('/.*\/(behat_[a-z_].*)\.php$/', $context, $matches);
            $classname = $matches[1];
            $stepsdefinitions[$classname] = $context;
        }

        // Add any other context file defined in config.
        $generatorconfig = self::get_config();

        foreach ($featurestoadd as $featurename) {
            if (!empty($generatorconfig[$featurename]['contextpath'])) {
                if (!is_array($generatorconfig[$featurename]['contextpath'])) {
                    $customcontextspaths = array($generatorconfig[$featurename]['contextpath']);
                } else {
                    $customcontextspaths = $generatorconfig[$featurename]['contextpath'];
                }

                foreach ($customcontextspaths as $customcontextpath) {
                    preg_match('/.*\/(behat_[a-z_].*)\.php$/', $customcontextpath, $matches);
                    $classname = $matches[1];
                    $stepsdefinitions[$classname] = $customcontextpath;
                }

            }
        }

        // Behat config file specifing the main context class,
        // the required Behat extensions and Moodle test wwwroot.
        $contents = self::get_config_file_contents($features, $stepsdefinitions);

        // Stores the file.
        if (!file_put_contents($configfilepath, $contents)) {
            self::performance_exception('File ' . $configfilepath . ' can not be created');
        }
    }

    /**
     * Behat config file specifing the main context class,
     * the required Behat extensions and Moodle test wwwroot.
     *
     * @param array $features The system feature files
     * @param array $stepsdefinitions The system steps definitions
     * @return string
     */
    protected static function get_config_file_contents($features, $stepsdefinitions) {
        global $CFG;

        // We require here when we are sure behat dependencies are available.
        require_once($CFG->dirroot . '/vendor/autoload.php');

        $selenium2wdhost = array('wd_host' => 'http://localhost:4444/wd/hub');

        $basedir = $CFG->dirroot . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'behat';

        $config = array(
            'default' => array(
                'paths' => array(
                    'features' => $basedir . DIRECTORY_SEPARATOR . 'features',
                    'bootstrap' => $basedir . DIRECTORY_SEPARATOR . 'features' . DIRECTORY_SEPARATOR . 'bootstrap',
                ),
                'context' => array(
                    'class' => 'behat_init_context'
                ),
                'extensions' => array(
                    'Behat\MinkExtension\Extension' => array(
                        'base_url' => $CFG->wwwroot,
                        'goutte' => null,
                        'selenium2' => $selenium2wdhost
                    ),
                    'Moodle\BehatExtension\Extension' => array(
                        'formatters' => array(
                            'moodle_progress' => 'Moodle\BehatExtension\Formatter\MoodleProgressFormatter',
                            'moodle_list' => 'Moodle\BehatExtension\Formatter\MoodleListFormatter',
                            'moodle_step_count' => 'Moodle\BehatExtension\Formatter\MoodleStepCountFormatter'
                        ),
                        'features' => $features,
                        'steps_definitions' => $stepsdefinitions,
                    )
                ),
                'formatter' => array(
                    'name' => 'moodle_progress'
                )
            )
        );

        // In case user defined overrides respect them over our default ones.
        if (!empty($CFG->performance_config)) {
            $config = self::merge_config($config, $CFG->performance_config);
        }

        return symfonyyaml::dump($config, 10, 2);
    }
}