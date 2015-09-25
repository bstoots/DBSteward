<?php
/**
 * DBSteward unit test for mysql5 tableOption diffing
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Austin Hyde <austin109@gmail.com>
 */

require_once __DIR__ . '/../dbstewardUnitTestBase.php';

/**
 * @group mysql5
 * @group nodb
 */
class Mysql5TableOptionsDiffSQLTest extends PHPUnit_Framework_TestCase {

  public function setUp() {
    dbsteward::set_sql_format('mysql5');
    dbsteward::$quote_schema_names = TRUE;
    dbsteward::$quote_table_names = TRUE;
    dbsteward::$quote_column_names = TRUE;
    dbsteward::$quote_function_names = TRUE;
    dbsteward::$quote_object_names = TRUE;
    mysql5::$use_auto_increment_table_options = FALSE;
    mysql5::$use_schema_name_prefix = FALSE;
  }

  public function testNoChange() {
    $old = <<<XML
<schema name="public" owner="NOBODY">
  <table name="test" primaryKey="a" owner="NOBODY">
    <tableOption sqlFormat="mysql5" name="engine" value="InnoDB"/>
    <column name="a" type="int"/>
  </table>
</schema>
XML;

    $new = <<<XML
<schema name="public" owner="NOBODY">
  <table name="test" primaryKey="a" owner="NOBODY">
    <tableOption sqlFormat="mysql5" name="engine" value="InnoDB"/>
    <column name="a" type="int"/>
  </table>
</schema>
XML;
    
    $this->common($old, $new, "");
  }


  public function testAdd() {
    $old = <<<XML
<schema name="public" owner="NOBODY">
  <table name="test" primaryKey="a" owner="NOBODY">
    <column name="a" type="int"/>
  </table>
</schema>
XML;

    $new = <<<XML
<schema name="public" owner="NOBODY">
  <table name="test" primaryKey="a" owner="NOBODY">
    <tableOption sqlFormat="mysql5" name="engine" value="InnoDB"/>
    <column name="a" type="int"/>
  </table>
</schema>
XML;
    
    $this->common($old, $new, "ALTER TABLE `test` ENGINE=InnoDB;");
  }

  public function testAlter() {
    $old = <<<XML
<schema name="public" owner="NOBODY">
  <table name="test" primaryKey="a" owner="NOBODY">
    <tableOption sqlFormat="mysql5" name="engine" value="InnoDB"/>
    <column name="a" type="int"/>
  </table>
</schema>
XML;

    $new = <<<XML
<schema name="public" owner="NOBODY">
  <table name="test" primaryKey="a" owner="NOBODY">
    <tableOption sqlFormat="mysql5" name="engine" value="MyISAM"/>
    <column name="a" type="int"/>
  </table>
</schema>
XML;
    
    $this->common($old, $new, "ALTER TABLE `test` ENGINE=MyISAM;");
  }

  public function testAlterAutoIncrement() {
    $old = <<<XML
<schema name="public" owner="NOBODY">
  <table name="test" primaryKey="a" owner="NOBODY">
    <tableOption sqlFormat="mysql5" name="auto_increment" value="5"/>
    <column name="a" type="int"/>
  </table>
</schema>
XML;

    $new = <<<XML
<schema name="public" owner="NOBODY">
  <table name="test" primaryKey="a" owner="NOBODY">
    <tableOption sqlFormat="mysql5" name="auto_increment" value="42"/>
    <column name="a" type="int"/>
  </table>
</schema>
XML;
    
    mysql5::$use_auto_increment_table_options = TRUE;
    $this->common($old, $new, "ALTER TABLE `test` AUTO_INCREMENT=42;");


    mysql5::$use_auto_increment_table_options = FALSE;
    $this->common($old, $new, '');
  }

  public function testAddAndAlter() {
    $old = <<<XML
<schema name="public" owner="NOBODY">
  <table name="test" primaryKey="a" owner="NOBODY">
    <tableOption sqlFormat="mysql5" name="engine" value="InnoDB"/>
    <column name="a" type="int"/>
  </table>
</schema>
XML;

    $new = <<<XML
<schema name="public" owner="NOBODY">
  <table name="test" primaryKey="a" owner="NOBODY">
    <tableOption sqlFormat="mysql5" name="engine" value="MyISAM"/>
    <tableOption sqlFormat="mysql5" name="auto_increment" value="5"/>
    <column name="a" type="int"/>
  </table>
</schema>
XML;
    
    mysql5::$use_auto_increment_table_options = TRUE;
    $this->common($old, $new, "ALTER TABLE `test` ENGINE=MyISAM\nAUTO_INCREMENT=5;");

    mysql5::$use_auto_increment_table_options = FALSE;
    $this->common($old, $new, "ALTER TABLE `test` ENGINE=MyISAM;");
  }

  public function testDrop() {
    $old = <<<XML
<schema name="public" owner="NOBODY">
  <table name="test" primaryKey="a" owner="NOBODY">
    <tableOption sqlFormat="mysql5" name="engine" value="InnoDB"/>
    <column name="a" type="int"/>
  </table>
</schema>
XML;

    $new = <<<XML
<schema name="public" owner="NOBODY">
  <table name="test" primaryKey="a" owner="NOBODY">
    <column name="a" type="int"/>
  </table>
</schema>
XML;

    $expected = <<<SQL
-- Table `test` must be recreated to drop options: engine
CREATE TABLE `test_DBSTEWARD_MIGRATION`
SELECT * FROM `test`;
DROP TABLE `test`;
RENAME TABLE `test_DBSTEWARD_MIGRATION` TO `test`;
SQL;
      
    $this->common($old, $new, $expected);
  }

  public function testDropAutoIncrement() {
    $old = <<<XML
<schema name="public" owner="NOBODY">
  <table name="test" primaryKey="a" owner="NOBODY">
    <tableOption sqlFormat="mysql5" name="auto_increment" value="42"/>
    <column name="a" type="int"/>
  </table>
</schema>
XML;

    $new = <<<XML
<schema name="public" owner="NOBODY">
  <table name="test" primaryKey="a" owner="NOBODY">
    <column name="a" type="int"/>
  </table>
</schema>
XML;

    mysql5::$use_auto_increment_table_options = TRUE;
    $expected = <<<SQL
-- Table `test` must be recreated to drop options: auto_increment
CREATE TABLE `test_DBSTEWARD_MIGRATION`
SELECT * FROM `test`;
DROP TABLE `test`;
RENAME TABLE `test_DBSTEWARD_MIGRATION` TO `test`;
SQL;
      
    $this->common($old, $new, $expected);

    // if we're ignoring auto_increment options, there's nothing to do
    mysql5::$use_auto_increment_table_options = FALSE;
    $this->common($old, $new, '');
  }

  public function testDropAddAlter() {
    $old = <<<XML
<schema name="public" owner="NOBODY">
  <table name="test" primaryKey="a" owner="NOBODY">
    <tableOption sqlFormat="mysql5" name="engine" value="InnoDB"/>
    <tableOption sqlFormat="mysql5" name="auto_increment" value="5"/>
    <column name="a" type="int"/>
  </table>
</schema>
XML;

    $new = <<<XML
<schema name="public" owner="NOBODY">
  <table name="test" primaryKey="a" owner="NOBODY">
    <tableOption sqlFormat="mysql5" name="auto_increment" value="10"/>
    <tableOption sqlFormat="mysql5" name="row_format" value="compressed"/>
    <column name="a" type="int"/>
  </table>
</schema>
XML;

    mysql5::$use_auto_increment_table_options = TRUE;
    $expected = <<<SQL
-- Table `test` must be recreated to drop options: engine
CREATE TABLE `test_DBSTEWARD_MIGRATION`
AUTO_INCREMENT=10
ROW_FORMAT=compressed
SELECT * FROM `test`;
DROP TABLE `test`;
RENAME TABLE `test_DBSTEWARD_MIGRATION` TO `test`;
SQL;
      
    $this->common($old, $new, $expected);


    mysql5::$use_auto_increment_table_options = FALSE;

    $expected = <<<SQL
-- Table `test` must be recreated to drop options: engine
CREATE TABLE `test_DBSTEWARD_MIGRATION`
ROW_FORMAT=compressed
SELECT * FROM `test`;
DROP TABLE `test`;
RENAME TABLE `test_DBSTEWARD_MIGRATION` TO `test`;
SQL;
      
    $this->common($old, $new, $expected);
  }

  private function common($old, $new, $expected) {
    $ofs = new mock_output_file_segmenter();

    $old_schema = new SimpleXMLElement($old);
    $old_table = $old_schema->table;

    $new_schema = new SimpleXMLElement($new);
    $new_table = $new_schema->table;

    mysql5_diff_tables::update_table_options($ofs, $ofs, $old_schema, $old_table, $new_schema, $new_table);

    $actual = trim($ofs->_get_output());
    $this->assertEquals($expected, $actual);
  }
}
