<?php
/**
 * Tests that slonyId output is correct
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author <jettea46@yahoo.com>
 */

require_once __DIR__ . '/../dbstewardUnitTestBase.php';

/**
 * @group pgsql8
 */
class SlonyIdStreakTest extends dbstewardUnitTestBase {

  public function setUp() {
    $this->testHandler = new Monolog\Handler\TestHandler;
    dbsteward::get_logger()->pushHandler($this->testHandler);

    dbsteward::set_sql_format('pgsql8');
    pgsql8::$table_slony_ids = array();
    pgsql8::$sequence_slony_ids = array();
  }

  public function tearDown() {
    dbsteward::get_logger()->popHandler();
  }

  /**
   * There was a bug in streaker where it wasn't counting the entire first streak, output used to be for below: 1, 5-6, 98-98
   */
  public function testSlonikStreakerIsGood() {
  $xml = <<<SLONXML
<?xml version="1.0" encoding="UTF-8"?>
<dbsteward>
  <database>
    <sqlformat>pgsql8</sqlformat>
    <role>
      <application>application</application>
      <owner>dba</owner>
      <replication>slony</replication>
      <readonly>readonly</readonly>
    </role>
    <slony clusterName="aim">
      <slonyNode id="1" comment="Master" dbPassword="wonk" dbUser="aim_slony" dbHost="db00" dbName="mrh"/>
      <slonyNode id="2" comment="Replica" dbPassword="wonk" dbUser="aim_slony" dbHost="db01" dbName="mrh"/>
      <slonyReplicaSet id="1" comment="only set" originNodeId="1" upgradeSetId="2">
        <slonyReplicaSetNode id="2" providerNodeId="1"/>
      </slonyReplicaSet>
    </slony>
  </database>
  <schema name="public" owner="ROLE_OWNER">
    <table name="log_sl" owner="ROLE_OWNER" primaryKey="id" primaryKeyName="log_pkey" slonyId="1">
      <column name="id" type="bigserial" slonyId="1"/>
      <grant role="ROLE_APPLICATION" operation="INSERT, SELECT, UPDATE, DELETE"/>
    </table>
    <table name="log_sl2" owner="ROLE_OWNER" primaryKey="id" primaryKeyName="log_pkey" slonyId="2">
      <column name="id" type="bigserial" slonyId="2"/>
      <grant role="ROLE_APPLICATION" operation="INSERT, SELECT, UPDATE, DELETE"/>
    </table>
    <table name="log_sl3" owner="ROLE_OWNER" primaryKey="id" primaryKeyName="log_pkey" slonyId="5">
      <column name="id" type="bigserial" slonyId="5"/>
      <grant role="ROLE_APPLICATION" operation="INSERT, SELECT, UPDATE, DELETE"/>
    </table>
    <table name="log_sl4" owner="ROLE_OWNER" primaryKey="id" primaryKeyName="log_pkey" slonyId="6">
      <column name="id" type="bigserial" slonyId="6"/>
      <grant role="ROLE_APPLICATION" operation="INSERT, SELECT, UPDATE, DELETE"/>
    </table>
    <table name="log_sl5" owner="ROLE_OWNER" primaryKey="id" primaryKeyName="log_pkey" slonyId="98">
      <column name="id" type="bigserial" slonyId="98"/>
      <grant role="ROLE_APPLICATION" operation="INSERT, SELECT, UPDATE, DELETE"/>
    </table>
    <!-- here for additional changes -->
  </schema>
</dbsteward>
SLONXML;

      $old_db_doc = simplexml_load_string($xml);
      dbsteward::$generate_slonik = TRUE;

      $output_prefix_path = dirname(__FILE__) . '/../testdata/' . 'slony_id_streak';
      pgsql8::build($output_prefix_path, $old_db_doc);

      $this->assertLogged(Monolog\Logger::NOTICE, '/sequence ID segments.*:\s1-2, 5-6, 98/');
  }

  private function assertLogged($level, $regex, $message = null) {
    $this->assertTrue($this->testHandler->hasRecordThatMatches($regex, $level),
      "Expected to find a log matching $regex\n$message");
  }
}
?>
