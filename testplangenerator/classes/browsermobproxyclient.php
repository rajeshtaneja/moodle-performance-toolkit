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
 * Helper class to interact with BrowserMobProxy.
 *
 * @package   moodlehq_performancetoolkit_testplangenerator
 * @copyright 2015 rajesh Taneja
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace moodlehq\performancetoolkit\testplangenerator;

use \Requests,
    moodlehq\performancetoolkit\testplangenerator\util;

class browsermobproxyclient {

    /** @var string BrowserMobProxy url */
    private $proxyurl;

    /** @var  string port on which proxy is running. */
    private $port;

    /**
     * Class constructor
     *
     * @param string $proxyurl proxy URL for BrowserMobProxy.
     */
    public function __construct($proxyurl) {

        if (preg_match("/^http(s)?:\/\/.*/i", $proxyurl) == 0) {
            $proxyurl = "http://" . $proxyurl;
        }

        $this->proxyurl = $proxyurl;
        util::set_option('proxyurl', $proxyurl);
    }

    /**
     * Create new connection to the proxy
     *
     * @param string $port speficy port if you want to open proxy at specific port.
     * @return string the url for proxy.
     */
    public function create_connection($port='') {

        $parts = parse_url($this->proxyurl);
        $hostname = $parts["host"];

        // Create request to open proxy connection.
        $options = array();
        $headers = array();
        if (!empty($port)) {
            $options = $this->encode_params(array('port' => $port));
        }

        $response = Requests::post($this->proxyurl . "/proxy/", $headers, $options);

        // Get port on which new proxy connection is created.
        $decoded = json_decode($response->body, true);
        if ($decoded) {
            $this->port = $decoded["port"];
            util::set_option('proxyport', $this->port);
        }

        // Return url on which the request will be handled.
        return $hostname . ":" . $this->port;
    }

    /**
     * Close connection to the proxy
     *
     * @return void
     */
    public function close_connection() {
        return Requests::delete($this->proxyurl. "/proxy/" . $this->port);
    }

    /**
     * Method for creating a new HAR file
     *
     * @param string $label optional label
     *
     * @return string
     */
    public static function new_har($label = '') {
        $proxyurl = util::get_option('proxyurl');
        $proxyport = util::get_option('proxyport');

        $data = array(
                "captureContent" => 'true',
                "initialPageRef" => $label,
                "captureHeaders" => 'true',
                "captureBinaryContent" => 'true',
                );
        $url = $proxyurl . "/proxy/" . $proxyport . "/har";
        $response = Requests::put(
            $url,
            array(),
            $data
        );
        return $response;
    }

    /**
     * Initialise HAR file, removing any old data.
     *
     * @return string json encoded har data.
     */
    public static function get_har($label = '') {

        $result = self::new_har($label);

        return $result->body;
    }

    /**
     * Encode an array of arguments
     *
     * @param array $params array of arguments to URLencode
     *
     * @return bool|string
     */
    private static function encode_params($params) {
        if (!is_array($params)) {
            return false;
        }

        $c = 0;
        $payload = array();
        foreach ($params as $name => $value) {
            $encodedstring = urlencode($name).'=';
            if (is_array($value)) {
                $encodedstring .= urlencode(serialize($value));
            } else {
                $encodedstring .= urlencode("$value");
            }
            $payload[] = $encodedstring;
        }

        return implode('&', $payload);
    }

    /**
     * Add regex pattern to the proxy blacklist
     *
     * @param string  $regexp      regular expression
     * @param integer $status_code HTTP status code
     *
     * @return string
     */
    public static function black_list($regexp, $status_code) {
        $proxyurl = util::get_option('proxyurl');
        $proxyport = util::get_option('proxyport');

        $data = self::encode_params(
            array(
                "regex" => $regexp,
                "status" => $status_code
            )
        );
        $url = $proxyurl . "/proxy/" . $proxyport . "/blacklist";
        $response = Requests::put(
            $url,
            array(),
            $data
        );
        return $response;
    }

    /**
     * Add regex pattern to the proxy whitelist
     *
     * @param string  $regexp      regular expression
     * @param integer $status_code HTTP status code
     *
     * @return string
     */
    public static function white_list($regexp, $status_code) {
        $proxyurl = util::get_option('proxyurl');
        $proxyport = util::get_option('proxyport');
        $data = self::encode_params(
            array(
                "regex" => $regexp,
                "status" => $status_code
            )
        );
        $url = $proxyurl . "/proxy/" . $proxyport . "/whitelist";
        $response = Requests::put(
            $url,
            array(),
            $data
        );
        return $response;
    }

    /**
     * Method for setting how long proxy should wait for traffic to stop
     *
     * @param integer $quiet_period time in milliseconds
     * @param integer $timeout      time in milliseconds
     *
     * @return string
     */
    public static function wait_for_traffic_to_stop($quiet_period, $timeout) {
        $proxyurl = util::get_option('proxyurl');
        $proxyport = util::get_option('proxyport');

        $data = self::encode_params(
            array(
                'quietPeriodInMs' => (string)($quiet_period * 1000),
                'timeoutInMs' => (string)($timeout * 1000)
            )
        );
        $url = $proxyurl . "/proxy/" . $proxyport . "/wait";
        $response = Requests::put(
            $url,
            array(),
            $data
        );
        return $response;
    }
}