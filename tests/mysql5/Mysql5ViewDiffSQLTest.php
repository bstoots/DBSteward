<?php
/**
 * DBSteward unit test for mysql5 view diffing
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
class Mysql5ViewDiffSQLTest extends PHPUnit_Framework_TestCase {

  public function setUp() {
    dbsteward::set_sql_format('mysql5');
    dbsteward::$quote_schema_names = TRUE;
    dbsteward::$quote_table_names = TRUE;
    dbsteward::$quote_column_names = TRUE;
    dbsteward::$quote_function_names = TRUE;
    dbsteward::$quote_object_names = TRUE;

    dbsteward::$always_recreate_views = FALSE;
    mysql5::$use_auto_increment_table_options = FALSE;
    mysql5::$use_schema_name_prefix = FALSE;

    $db_doc_xml = <<<XML
<dbsteward>
  <database>
    <role>
      <owner>the_owner</owner>
      <customRole>SOMEBODY</customRole>
    </role>
  </database>
</dbsteward>
XML;
    
    dbsteward::$old_database = new SimpleXMLElement($db_doc_xml);
    dbsteward::$new_database = new SimpleXMLElement($db_doc_xml);
  }

  public function testDrop() {
    $one = <<<XML
<schema name="public" owner="ROLE_OWNER">
  <view name="test_view" owner="ROLE_OWNER">
    <viewQuery sqlFormat="mysql5">SELECT * FROM foo;</viewQuery>
  </view>
</schema>
XML;
    $none = <<<XML
<schema name="public" owner="ROLE_OWNER">
</schema>
XML;

    // no change, no drops
    $this->common_drop($one, $one, '', "one to one");

    // no change, no drops
    $this->common_drop($none, $none, '', "none to none");

    // only adding, no drops
    $this->common_drop($none, $one, '', "none to one");

    // only removing, expect single drop
    $this->common_drop($one, $none, "DROP VIEW IF EXISTS `test_view`;", "one to none");

    // change the query
    $alt_one = <<<XML
<schema name="public" owner="ROLE_OWNER">
  <view name="test_view" owner="ROLE_OWNER">
    <viewQuery sqlFormat="mysql5">SELECT * FROM bar;</viewQuery>
  </view>
</schema>
XML;

    // query changed, expect single drop
    $this->common_drop($one, $alt_one, "DROP VIEW IF EXISTS `test_view`;", "redefined");

    // change the owner
    $alt_one = <<<XML
<schema name="public" owner="ROLE_OWNER">
  <view name="test_view" owner="SOMEBODY">
    <viewQuery sqlFormat="mysql5">SELECT * FROM foo;</viewQuery>
  </view>
</schema>
XML;

    // owner changed, expect single drop
    $this->common_drop($one, $alt_one, "DROP VIEW IF EXISTS `test_view`;", "different owner");
  }

  public function testCreate() {
    $one = <<<XML
<schema name="public" owner="ROLE_OWNER">
  <view name="test_view" owner="ROLE_OWNER">
    <viewQuery sqlFormat="mysql5">SELECT * FROM foo;</viewQuery>
  </view>
</schema>
XML;
    $none = <<<XML
<schema name="public" owner="ROLE_OWNER">
</schema>
XML;

    // no change, no creates
    $this->common_create($one, $one, '', "one to one");

    // no change, no creates
    $this->common_create($none, $none, '', "none to none");

    // only adding, expect single create
    $this->common_create($none, $one, "CREATE OR REPLACE DEFINER = the_owner SQL SECURITY DEFINER VIEW `test_view`\n  AS SELECT * FROM foo;", "none to one");

    // only removing, no creates
    $this->common_create($one, $none, '', "one to none");

    // change the query
    $alt_one = <<<XML
<schema name="public" owner="ROLE_OWNER">
  <view name="test_view" owner="ROLE_OWNER">
    <viewQuery sqlFormat="mysql5">SELECT * FROM bar;</viewQuery>
  </view>
</schema>
XML;

    // query changed, expect single create
    $this->common_create($one, $alt_one, "CREATE OR REPLACE DEFINER = the_owner SQL SECURITY DEFINER VIEW `test_view`\n  AS SELECT * FROM bar;", "redefined");

    // change the owner
    $alt_one = <<<XML
<schema name="public" owner="ROLE_OWNER">
  <view name="test_view" owner="SOMEBODY">
    <viewQuery sqlFormat="mysql5">SELECT * FROM foo;</viewQuery>
  </view>
</schema>
XML;

    // owner changed, expect single drop
    $this->common_create($one, $alt_one, "CREATE OR REPLACE DEFINER = SOMEBODY SQL SECURITY DEFINER VIEW `test_view`\n  AS SELECT * FROM foo;", "different owner");
  }

  public function testAlwaysRecreate() {
    $view = <<<XML
<schema name="public" owner="ROLE_OWNER">
  <view name="test_view" owner="ROLE_OWNER">
    <viewQuery sqlFormat="mysql5">SELECT * FROM foo;</viewQuery>
  </view>
</schema>
XML;
    dbsteward::$always_recreate_views = FALSE;
    $this->common_drop($view, $view, '');
    $this->common_create($view, $view, '');

    dbsteward::$always_recreate_views = TRUE;
    $this->common_drop($view, $view, "DROP VIEW IF EXISTS `test_view`;");
    $this->common_create($view, $view, "CREATE OR REPLACE DEFINER = the_owner SQL SECURITY DEFINER VIEW `test_view`\n  AS SELECT * FROM foo;");
  }



  private function common_drop($xml_a, $xml_b, $expected, $message='') {
    $ofs = new mock_output_file_segmenter();

    list($old_doc, $old_schema) = $this->append(dbsteward::$old_database, new SimpleXMLElement($xml_a));
    list($new_doc, $new_schema) = $this->append(dbsteward::$new_database, new SimpleXMLElement($xml_b));

    mysql5_diff_views::drop_views_ordered($ofs, dbsteward::$old_database, dbsteward::$new_database);
    
    $old_doc->removeChild($old_schema);
    $new_doc->removeChild($new_schema);

    $actual = trim($ofs->_get_output());

    $this->assertEquals($expected, $actual, "in drop: $message");
  }

  private function common_create($xml_a, $xml_b, $expected, $message='') {
    $ofs = new mock_output_file_segmenter();

    list($old_doc, $old_schema) = $this->append(dbsteward::$old_database, new SimpleXMLElement($xml_a));
    list($new_doc, $new_schema) = $this->append(dbsteward::$new_database, new SimpleXMLElement($xml_b));

    mysql5_diff_views::create_views_ordered($ofs, dbsteward::$old_database, dbsteward::$new_database);

    $old_doc->removeChild($old_schema);
    $new_doc->removeChild($new_schema);

    $actual = trim($ofs->_get_output());

    $this->assertEquals($expected, $actual, "in create: $message");
  }

  private function append($parent, $child) {
    $parent_node = dom_import_simplexml($parent);
    $child_node = dom_import_simplexml($child);

    $parent_node->appendChild($child_node = $parent_node->ownerDocument->importNode($child_node, true));
    return array($parent_node, $child_node);
  }
}
