<?php
/**
 * Manipulate table and column constraints
 *
 * @package DBSteward
 * @subpackage sql99
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 * @author Austin Hyde <austin109@gmail.com>
 */

class sql99_constraint {

  public static function foreign_key_lookup($db_doc, $node_schema, $node_table, $column) {
    $foreign = array();
    $foreign['schema'] = dbx::get_schema($db_doc, $column['foreignSchema']);
    if ( ! $foreign['schema'] ) {
      throw new Exception("Failed to find foreign schema '{$column['foreignSchema']}' for {$node_schema['name']}.{$node_table['name']}.{$column['name']}");
    }

    $foreign['table'] = dbx::get_table($foreign['schema'], $column['foreignTable']);
    if ( ! $foreign['table'] ) {
      throw new Exception("Failed to find foreign table '{$column['foreignTable']}' for {$node_schema['name']}.{$node_table['name']}.{$column['name']}");
    }

    // if foreignColumn is not set
    // the column is assumed to be the same name as the referring column
    if ( ! empty($column['foreignColumn']) ) {
      $foreignColumn = $column['foreignColumn'];
    }
    else {
      $foreignColumn = $column['name'];
    }

    $foreign['column'] = dbx::get_table_column($foreign['table'], $foreignColumn);
    if ( ! $foreign['column'] ) {
      var_dump($foreign['column']);
      throw new Exception("Failed to find foreign column '{$foreignColumn}' for {$node_schema['name']}.{$node_table['name']}.{$column['name']}");
    }

    // column type is missing, and resolved foreign is also a foreign key?
    // recurse and find the cascading foreign key
    if ( empty($foreign['column']['type']) && !empty($foreign['column']['foreignColumn']) ) {
      //dbsteward::console_line(4, "Seeking nested foreign key for " . dbsteward::string_cast($foreign['schema']['name']) . "." . dbsteward::string_cast($foreign['table']['name']) . "." . $foreign['column']['name']);
      $nested_fkey = array();
      self::foreign_key($db_doc, $foreign['schema'], $foreign['table'], $foreign['column'], $nested_fkey);
      //var_dump($nested_fkey['column']);
      // make a separate clone of the column element because we are specifying the type only for foreign key type referencing
      $foreign['column'] = new SimpleXMLElement($foreign['column']->asXML());
      $foreign['column']['type'] = $nested_fkey['column']['type'];
    }

    $foreign['name'] = pgsql8::index_name($node_table['name'], $column['name'], 'fkey');
    $foreign['references'] = static::get_foreign_key_reference_sql($foreign);

    return $foreign;
  }

  public static function get_foreign_key_reference_sql($foreign) {
    return sql99::get_fully_qualified_table_name($foreign['schema']['name'], $foreign['table']['name']) . ' (' . sql99::get_quoted_column_name($foreign['column']) . ')';
  }

  /**
   * return collection of arrays representing all of the constraints on a table
   * this is more than just the <constraint> discret children of a table element
   * this is also primary key, inline column foreign keys, and inline column unique constraints
   * everything comparing the constraints of a table should be calling this
   */
  public static function get_table_constraints($db_doc, $node_schema, $node_table, $type = 'all') {
    if ( !is_object($node_table) ) {
      var_dump($node_table);
      throw new Exception("node_table is not an object, check trace for bad table pointer");
    }
    switch ($type) {
      case 'all':
      case 'primaryKey':
      case 'constraint':
      case 'foreignKey':
      break;
      default:
        throw new Exception("unknown type " . $type . " encountered");
    }
    $constraints = array();

    if ($type == 'all' || $type == 'primaryKey') {
      if (isset($node_table['primaryKey'])) {
        $pk_name = static::get_primary_key_name($node_table);
        $pk_def = static::get_primary_key_definition($node_table);

        $constraints[] = array(
          'name' => $pk_name,
          'schema_name' => (string)$node_schema['name'],
          'table_name' => (string)$node_table['name'],
          'type' => 'PRIMARY KEY',
          'definition' => $pk_def
        );
      }
      else {
        throw new Exception("Every table must have a primaryKey!");
      }
    }

    if ( $type == 'all' || $type == 'constraint' || $type == 'foreignKey' ) {
      // look for constraints in <constraint> elements
      foreach ( $node_table->constraint AS $node_constraint ) {
        // further sanity check node definition constraint types
        switch ( strtoupper((string)$node_constraint['type']) ) {
          case 'PRIMARY KEY':
            throw new Exception("Primary keys are not allowed to be defined in a <constraint>");
            break;

          default:
            throw new Exception('unknown constraint type ' . $node_constraint['type'] . ' encountered');
            break;

          case 'CHECK':
          case 'UNIQUE':
            // if we're ONLY looking for foreign keys, ignore everything else
            if ( $type == 'foreignKey' ) {
              continue;
            }
            // fallthru
          case 'FOREIGN KEY':
            $constraints[] = array(
              'name' => (string)$node_constraint['name'],
              'schema_name' => (string)$node_schema['name'],
              'table_name' => (string)$node_table['name'],
              'type' => strtoupper((string)$node_constraint['type']),
              'definition' => (string)$node_constraint['definition']
            );
            break;
        }
      }

      // look for constraints in columns: foreign key and unique
      foreach ($node_table->column AS $column) {
        if ( isset($column['foreignSchema']) || isset($column['foreignTable']) ) {

          if ( empty($column['foreignSchema']) || empty($column['foreignTable']) ) {
            throw new Exception("Invalid foreignSchema|foreignTable pair for {$node_schema['name']}.{$node_table['name']}.{$column['name']}");
          }
          if ( ! empty($column['type']) ) {
            throw new exception("Foreign-Keyed columns should not specify a type for {$node_schema['name']}.{$node_table['name']}.{$column['name']}");
          }

          $foreign = static::foreign_key_lookup($db_doc, $node_schema, $node_table, $column);
          if ( ! empty($column['foreignKeyName']) > 0) {
            // explicitly name the foreign key if specified in the node
            $foreign['name'] = (string)$column['foreignKeyName'];
          }

          $column_fkey_constraint = array(
            'name' => (string)$foreign['name'],
            'schema_name' => (string)$node_schema['name'],
            'table_name' => (string)$node_table['name'],
            'type' => 'FOREIGN KEY',
            'definition' => '(' . dbsteward::quote_column_name($column['name']) . ') REFERENCES ' . $foreign['references'],
            'foreign_key_data' => $foreign
          );

          if ( ! empty($column['foreignOnDelete']) ) {
            $column_fkey_constraint['foreignOnDelete'] = strtoupper((string)$column['foreignOnDelete']);
          }
          if ( ! empty($column['foreignOnUpdate']) ) {
            $column_fkey_constraint['foreignOnUpdate'] = strtoupper((string)$column['foreignOnUpdate']);
          }

          $constraints[] = $column_fkey_constraint;
        }
      }
    }
    return $constraints;
  }

  /**
   * Split the primary key up into an array of columns
   *
   * @param string $primary_key_string The primary key string (e.g. "schema_name, table_name, column_name")
   * @return array The primary key(s) split into an array
   */
  public static function primary_key_split($primary_key_string) {
    return preg_split("/[\,\s]+/", $primary_key_string, -1, PREG_SPLIT_NO_EMPTY);
  }

  public static function get_primary_key_name($node_table) {
    if ( ! empty($node_table['primaryKeyName']) ) {
      return dbsteward::string_cast($node_table['primaryKeyName']);
    }
    else {
      return pgsql8::index_name($node_table['name'], NULL, 'pkey');
    }
  }

  public static function get_primary_key_definition($node_table, $column_quote_fn='sql99::get_quoted_column_name') {
    return '(' . implode(', ', array_map($column_quote_fn, static::primary_key_split($node_table['primaryKey']))) . ')';
  }

  /**
   * Converts referential integrity options (NO_ACTION, RESTRICT, CASCADE, SET_NULL, SET_DEFAULT) to syntax-dependent SQL
   */
  public static function get_reference_option_sql($ref_opt) {
    return strtoupper($ref_opt);
  }
}