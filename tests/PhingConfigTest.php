<?php
/**
 * Tests that phing .config.properties is sane
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author <jettea46@yahoo.com>
 */

require_once __DIR__ . '/dbstewardUnitTestBase.php';
require_once __DIR__ . '/../lib/DBSteward/sql_format/pgsql8/pgsql8.php';
require_once __DIR__ . '/../lib/DBSteward/sql_format/pgsql8/pgsql8_diff.php';

/**
 * @group pgsql8
 * @group mysql5
 */
class PhingConfigTest extends PHPUnit_Framework_TestCase {

  public function testSaneConfig() {
    // not exactly an ini file, oh well
    $props = file(__DIR__ . '/../.config.properties');
    $config = array();
    foreach ($props as $prop) {
      $dynamite = explode('=', $prop);
      if (count($dynamite) > 1) {
        $config[trim($dynamite[0])] = trim($dynamite[1]);
      }
    }

    $this->assertEquals("dbsteward", $config['package.name']);
    $this->assertEquals(dbsteward::VERSION, $config['package.version']);
    $this->assertEquals(dbsteward::API_VERSION, $config['package.api_version']);
  }
}
?>
