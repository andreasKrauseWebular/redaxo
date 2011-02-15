<?php

/**
 * Klasse zur Verbindung und Interatkion mit der Datenbank
 * @version svn:$Id$
 */
// see http://net.tutsplus.com/tutorials/php/why-you-should-be-using-phps-pdo-for-database-access/
class rex_sql
{
  public
    $debugsql, // debug schalter
    $counter; // pointer

  private
    $values, // Werte von setValue
    $fieldnames, // Spalten im ResultSet
    $tablenames, // Tabelle im ResultSet
    $lastRow, // Wert der zuletzt gefetchten zeile
    $table, // Tabelle setzen
    $wherevar, // WHERE Bediengung
    $rows, // anzahl der treffer
    $stmt, // ResultSet
    $DBID; // ID der Verbindung
    
  private static
    $pdo = array(); // array von datenbankverbindungen

  protected function __construct($DBID = 1)
  {
    global $REX;

    $this->debugsql = false;
    $this->selectDB($DBID);
  }

  /**
   * Stellt die Verbindung zur Datenbank her
   */
  protected function selectDB($DBID)
  {
    global $REX;

    $this->DBID = $DBID;


    try {
      if(!isset(self::$pdo[$this->DBID]))
      {
        $conn = self::createConnection(
          $REX['DB'][$DBID]['HOST'],
          $REX['DB'][$DBID]['NAME'],
          $REX['DB'][$DBID]['LOGIN'],
          $REX['DB'][$DBID]['PSW']
        );
        self::$pdo[$this->DBID] = $conn;
        // ggf. Strict Mode abschalten
        $this->setQuery('SET SQL_MODE=""');
        // set encoding
        $this->setQuery('SET NAMES utf8');
        $this->setQuery('SET CHARACTER SET utf8');
      }
    }
    catch(PDOException $e)
    {
      echo "<font style='color:red; font-family:verdana,arial; font-size:11px;'>Class SQL 1.1 | Database down. | Please contact <a href=mailto:" . $REX['ERROR_EMAIL'] . ">" . $REX['ERROR_EMAIL'] . "</a>\n | Thank you!\n</font>";
      exit;
    }
  }
  
  static protected function createConnection($host, $database, $login, $password, $persistent = false)
  {
    $dsn = 'mysql:host='. $host .';dbname='. $database;
    $options = array(
      PDO::ATTR_PERSISTENT => (boolean) $persistent,
      PDO::ATTR_FETCH_TABLE_NAMES => true,
//      PDO::ATTR_CURSOR => PDO::CURSOR_SCROLL,
//      PDO::ATTR_EMULATE_PREPARES => true,
    );
    
    return new PDO($dsn, $login, $password, $options);
  }

  /**
   * Gibt die DatenbankId der Abfrage (SQL) zurueck,
   * oder false wenn die Abfrage keine DBID enthaelt
   *
   * @param $query Abfrage
   */
  static protected function getQueryDBID($qry)
  {
    $qry = trim($qry);

    if(preg_match('/\(DB([1-9]){1}\)/i', $qry, $matches))
      return $matches[1];

    return false;
  }

  /**
   * Entfernt die DBID aus einer Abfrage (SQL) und gibt die DBID zurueck falls
   * vorhanden, sonst false
   *
   * @param $query Abfrage
   */
  static protected function stripQueryDBID(&$qry)
  {
    $qry = trim($qry);

    if(($qryDBID = self::getQueryDBID($qry)) !== false)
      $qry = substr($qry, 6);

    return $qryDBID;
  }

  /**
   * Gibt den Typ der Abfrage (SQL) zurueck,
   * oder false wenn die Abfrage keinen Typ enthaelt
   *
   * Moegliche Typen:
   * - SELECT
   * - SHOW
   * - UPDATE
   * - INSERT
   * - DELETE
   * - REPLACE
   *
   * @param $query Abfrage
   */
  static public function getQueryType($qry)
  {
    $qry = trim($qry);
    // DBID aus dem Query herausschneiden, falls vorhanden
    self::stripQueryDBID($qry);

    if(preg_match('/^(SELECT|SHOW|UPDATE|INSERT|DELETE|REPLACE)/i', $qry, $matches))
      return strtoupper($matches[1]);

    return false;
  }

  /**
   * Setzt eine Abfrage (SQL) ab, wechselt die DBID falls vorhanden
   *
   * @param $query Abfrage
   * @return boolean True wenn die Abfrage erfolgreich war (keine DB-Errors
   * auftreten), sonst false
   */
  public function setDBQuery($qry)
  {
    if(($qryDBID = self::stripQueryDBID($qry)) !== false)
      $this->selectDB($qryDBID);

    return $this->setQuery($qry);
  }

  /**
   * Setzt Debugmodus an/aus
   *
   * @param $debug Debug TRUE/FALSE
   */
  public function setDebug($debug = TRUE)
  {
	  $this->debugsql = $debug;
  }

  /**
   * Prepares a PDOStatement
   *
   * @param string $qry A query string with placeholders
   *
   * @return PDOStatement The prepared statement
   */
  public function prepareQuery($qry)
  {
    return ($this->stmt = self::$pdo[$this->DBID]->prepare($qry));
  }

  /**
   * Executes the prepared statement with the given input parameters
   * @param array $params Array of input parameters
   */
  public function execute(array $params)
  {
    $this->stmt->execute($params);
  }

  /**
   * Executes the given sql-query (un-prepared)
   *
   * @param $query The sql-query
   * @return boolean true on success, otherwise false
   */
  public function setQuery($qry)
  {
    // Alle Werte zuruecksetzen
    $this->flush();

    $this->stmt = self::$pdo[$this->DBID]->query(trim($qry));

    if($this->stmt !== false)
    {
      $this->rows = $this->stmt->rowCount();
    }
    else {
      debug_print_backtrace();
      throw new rexException($qry);
    }

    $hasError = $this->hasError();
    if ($this->debugsql || $hasError)
    {
      $this->printError($qry);
    }

    return !$hasError;
  }

  /**
   * Setzt den Tabellennamen
   *
   * @param $table Tabellenname
   */
  public function setTable($table)
  {
    $this->table = $table;
  }

  /**
   * Setzt den Wert eine Spalte
   *
   * @param $feldname Spaltenname
   * @param $wert Wert
   */
  public function setValue($feldname, $wert)
  {
    $this->values[$feldname] = $wert;
  }

  /**
   * Setzt ein Array von Werten zugleich
   *
   * @param $valueArray Ein Array von Werten
   * @param $wert Wert
   */
  public function setValues($valueArray)
  {
    if(is_array($valueArray))
    {
      foreach($valueArray as $name => $value)
      {
        $this->setValue($name, $value);
      }
      return true;
    }
    return false;
  }

  /**
   * Prueft den Wert einer Spalte der aktuellen Zeile ob ein Wert enthalten ist
   * @param $feld Spaltenname des zu pruefenden Feldes
   * @param $prop Wert, der enthalten sein soll
   */
  protected function isValueOf($feld, $prop)
  {
    if ($prop == "")
    {
      return TRUE;
    }
    else
    {
      return strpos($this->getValue($feld), $prop) !== false;
    }
  }

  /**
   * Setzt die WHERE Bedienung der Abfrage
   */
  public function setWhere($where)
  {
    $this->wherevar = "WHERE $where";
  }

  /**
   * Gibt den Wert einer Spalte im ResultSet zurueck
   * @param $value Name der Spalte
   * @param [$row] Zeile aus dem ResultSet
   */
  public function getValue($feldname)
  {
    if(empty($feldname))
    {
      throw new rexException('parameter fieldname must not be empty!');
    }
    
    // check if there is an table alias defined
    // if not, try to guess the tablename
    if(strpos($feldname, '.') === false)
    {
      $tables = $this->getTablenames();
      foreach($tables as $table)
      {
        if($this->hasValue($table .'.'. $feldname))
        {
          return $this->fetchValue($table .'.'. $feldname);
        }
      }
    }
    
    return $this->fetchValue($feldname);
  }
  
  protected function fetchValue($feldname)
  {
  	if(isset($this->values[$feldname]))
  		return $this->values[$feldname];

    if(empty($this->lastRow))
    {
      $this->lastRow = $this->stmt->fetch(PDO::FETCH_ASSOC);
    }

    $res = false;
    // isset doesn't work here, because values may also be null
    if(array_key_exists($feldname, $this->lastRow))
    {
      $res = $this->lastRow[$feldname];
    }
    else
    {
      $sendWarnings = (error_reporting() & E_WARNING) == E_WARNING;

      if($sendWarnings && function_exists('debug_backtrace'))
      {
        $trace = debug_backtrace();
        $loc = $trace[1];
        echo '<b>Warning</b>:  rex_sql->getValue('. $feldname .'): Initial error found in file <b>'. $loc['file'] .'</b> on line <b>'. $loc['line'] .'</b><br />';
      }
    }

    return $res;
  }

  /**
   * Gibt den Wert der aktuellen Zeile im ResultSet zurueck und
   * bewegt den internen Zeiger auf die naechste Zeile
   */
  public function getRow($fetch_type = PDO::FETCH_ASSOC)
  {
    $this->lastRow = $this->stmt->fetch($fetch_type);
    return $this->lastRow;
  }

  /**
   * Prueft, ob eine Spalte im Resultset vorhanden ist
   * @param $value Name der Spalte
   */
  public function hasValue($feldname)
  {
    return in_array($feldname, $this->getFieldnames());
  }

  /**
   * Prueft, ob das Feld mit dem Namen $feldname Null ist.
   *
   * Falls das Feld nicht vorhanden ist,
   * wird Null zurueckgegeben, sonst True/False
   */
  public function isNull($feldname)
  {
    if($this->hasValue($feldname))
      return $this->getValue($feldname) === null;

    return null;
  }

  /**
   * Gibt die Anzahl der Zeilen zurueck
   */
  public function getRows()
  {
    return $this->rows;
  }

  /**
   * Gibt die Zeilennummer zurueck, auf der sich gerade der
   * interne Zaehler befindet
   *
   * @deprecated since version 4.3.0
   */
  public function getCounter()
  {
    return $this->counter;
  }

  /**
   * Gibt die Anzahl der Felder/Spalten zurueck
   */
  public function getFields()
  {
    return $this->stmt->columnCount();
  }

  /**
   * Baut den SET bestandteil mit der
   * verfuegbaren values zusammen und gibt diesen zurueck
   *
   * @see setValue
   */
  protected function buildSetQuery()
  {
    $qry = '';
    if (is_array($this->values))
    {
      foreach ($this->values as $fld_name => $value)
      {
        if ($qry != '')
        {
          $qry .= ',';
        }

        // Bei <tabelle>.<feld> Notation '.' ersetzen, da sonst `<tabelle>.<feld>` entsteht
        if(strpos($fld_name, '.') !== false)
          $fld_name = str_replace('.', '`.`', $fld_name);

        if($value === null)
          $qry .= '`' . $fld_name . '`= NULL';
        else
          $qry .= '`' . $fld_name . '`=\'' . $value .'\'';

// Da Werte via POST/GET schon mit magic_quotes escaped werden,
// brauchen wir hier nicht mehr escapen
//        $qry .= '`' . $fld_name . '`=' . $this->escape($value);
      }
    }

    return $qry;
  }

  /**
   * Setzt eine Select-Anweisung auf die angegebene Tabelle
   * mit den WHERE Parametern ab
   *
   * @see #setTable()
   * @see #setWhere()
   */
  public function select($fields)
  {
    // TODO use prepared-statement
    return $this->setQuery('SELECT '. $fields .' FROM `' . $this->table . '` '. $this->wherevar);
  }

  /**
   * Setzt eine Update-Anweisung auf die angegebene Tabelle
   * mit den angegebenen Werten und WHERE Parametern ab
   *
   * @see #setTable()
   * @see #setValue()
   * @see #setWhere()
   */
  public function update($successMessage = null)
  {
    // TODO use prepared-statement
    return $this->statusQuery('UPDATE `' . $this->table . '` SET ' . $this->buildSetQuery() .' '. $this->wherevar, $successMessage);
  }

  /**
   * Setzt eine Insert-Anweisung auf die angegebene Tabelle
   * mit den angegebenen Werten ab
   *
   * @see #setTable()
   * @see #setValue()
   */
  public function insert($successMessage = null)
  {
    // TODO use prepared-statement
    return $this->statusQuery('INSERT INTO `' . $this->table . '` SET ' . $this->buildSetQuery(), $successMessage);
  }

  /**
   * Setzt eine Replace-Anweisung auf die angegebene Tabelle
   * mit den angegebenen Werten ab
   *
   * @see #setTable()
   * @see #setValue()
   * @see #setWhere()
   */
  public function replace($successMessage = null)
  {
    // TODO use prepared-statement
    return $this->statusQuery('REPLACE INTO `' . $this->table . '` SET ' . $this->buildSetQuery() .' '. $this->wherevar, $successMessage);
  }

  /**
   * Setzt eine Delete-Anweisung auf die angegebene Tabelle
   * mit den angegebenen WHERE Parametern ab
   *
   * @see #setTable()
   * @see #setWhere()
   */
  public function delete($successMessage = null)
  {
    // TODO use prepared-statement
    return $this->statusQuery('DELETE FROM `' . $this->table . '` ' . $this->wherevar, $successMessage);
  }

  /**
   * Setzt den Query $query ab.
   *
   * Wenn die Variable $successMessage gefuellt ist, dann wird diese bei
   * erfolgreichem absetzen von $query zurueckgegeben, sonst die MySQL
   * Fehlermeldung
   *
   * Wenn die Variable $successMessage nicht gefuellt ist, verhaelt sich diese
   * Methode genauso wie setQuery()
   *
   * Beispiel:
   *
   * <code>
   * $sql = rex_sql::factory();
   * $message = $sql->statusQuery(
   *    'INSERT  INTO abc SET a="ab"',
   *    'Datensatz  erfolgreich eingefuegt');
   * </code>
   *
   *  anstatt von
   *
   * <code>
   * $sql = rex_sql::factory();
   * if($sql->setQuery('INSERT INTO abc SET a="ab"'))
   *   $message  = 'Datensatz erfolgreich eingefuegt');
   * else
   *   $message  = $sql- >getError();
   * </code>
   */
  public function statusQuery($query, $successMessage = null)
  {
    $res = $this->setQuery($query);
    if($successMessage)
    {
      if($res)
        return $successMessage;
      else
        return $this->getError();
    }
    return $res;
  }

  /**
   * Stellt alle Werte auf den Ursprungszustand zurueck
   */
  public function flush()
  {
    $this->flushValues();
    $this->lastRow = array();
    $this->fieldnames = array ();

    $this->table = '';
    $this->wherevar = '';
    $this->counter = 0;
    $this->rows = 0;
  }

  /**
   * Stellt alle Values, die mit setValue() gesetzt wurden, zurueck
   *
   * @see #setValue(), #getValue()
   */
  public function flushValues()
  {
    $this->values = array ();
  }


  /**
   * Setzt den Cursor des Resultsets auf die naechst niedrigere Stelle
   */
  /*
  public function previous()
  {
    $this->counter--;
  }
  */

  /**
   * Setzt den Cursor des Resultsets auf die naechst hoehere Stelle
   */
  public function next()
  {
    $this->counter++;
    $this->lastRow = $this->stmt->fetch($fetch_type);
  }

  /*
   * Prueft ob das Resultset weitere Datensaetze enthaelt
   */
  public function hasNext()
  {
    return $this->counter != $this->rows;
  }

  /**
   * Setzt den Cursor des Resultsets zurueck zum Anfang
   */
  public function reset()
  {
    // re-execute the statement
    $this->stmt->execute();
    $this->counter = 0;
  }

  /**
   * Setzt den Cursor des Resultsets aufs Ende
   */
  /*
  public function last()
  {
    $this->counter = ($this->rows - 1);
  }
  */

  /**
   * Gibt die letzte InsertId zurueck
   */
  public function getLastId()
  {
    return self::$pdo[$this->DBID]->lastInsertId();
  }

  /**
   * Laedt das komplette Resultset in ein Array und gibt dieses zurueck und
   * wechselt die DBID falls vorhanden
   *
   * @param string $sql Abfrage
   * @param string $fetch_type Default: PDO::FETCH_ASSOC
   * @return array
   */
  public function getDBArray($sql = '', $fetch_type = PDO::FETCH_ASSOC)
  {
    return $this->_getArray($sql, $fetch_type, 'DBQuery');
  }

  /**
   * Laedt das komplette Resultset in ein Array und gibt dieses zurueck
   *
   * @param string $sql Abfrage
   * @param string $fetch_type Default: PDO::FETCH_ASSOC
   * @return array
   */
  public function getArray($sql = '', $fetch_type = PDO::FETCH_ASSOC)
  {
    return $this->_getArray($sql, $fetch_type);
  }

  /**
   * Hilfsfunktion
   *
   * @see getArray()
   * @see getDBArray()
   * @param string $sql Abfrage
   * @param string $fetch_type PDO::FETCH_ASSOC
   * @param string $qryType void oder DBQuery
   * @return array
   */
  private function _getArray($sql, $fetch_type, $qryType = 'default')
  {
    if ($sql != '')
    {
      switch($qryType)
      {
        case 'DBQuery': $this->setDBQuery($sql); break;
        default       : $this->setQuery($sql);
      }
    }

    return $this->stmt->fetchAll($fetch_type);
  }

  /**
   * Gibt die zuletzt aufgetretene Fehlernummer zurueck
   */
  public function getErrno()
  {
    return (int) self::$pdo[$this->DBID]->errorCode();
  }

  /**
   * Gibt den zuletzt aufgetretene Fehler zurueck
   */
  public function getError()
  {
    $errorInfos = self::$pdo[$this->DBID]->errorInfo();
    // idx0 	SQLSTATE error code (a five characters alphanumeric identifier defined in the ANSI SQL standard).
    // idx1 	Driver-specific error code.
    // idx2 	Driver-specific error message.
    return $errorInfos[2];
  }

  /**
   * Prueft, ob ein Fehler aufgetreten ist
   */
  public function hasError()
  {
    return $this->getErrno() != 0;
  }

  /**
   * Gibt die letzte Fehlermeldung aus
   */
  protected function printError()
  {
    if ($this->debugsql == true)
    {
      if(!is_object($this->stmt))
      {
        throw new rexException('you have to execute a statement before printing an corresponding error-message!');
      }

      echo '<hr />' . "\n";
      echo 'Query: ' . nl2br(htmlspecialchars($this->stmt->queryString)) . "<br />\n";

      if (strlen($this->getRows()) > 0)
      {
        echo 'Affected Rows: ' . $this->getRows() . "<br />\n";
      }
      if (strlen($this->getError()) > 0)
      {
        echo 'Error Message: ' . htmlspecialchars($this->getError()) . "<br />\n";
        echo 'Error Code: ' . $this->getErrno() . "<br />\n";
      }
    }
  }

  /**
   * Setzt eine Spalte auf den naechst moeglich auto_increment Wert
   * @param $field Name der Spalte
   */
  public function setNewId($field)
  {
    // setNewId muss neues sql Objekt verwenden, da sonst bestehende informationen im Objekt ueberschrieben werden
    $sql = rex_sql::factory();
    // TODO use prepared statement
    if($sql->setQuery('SELECT `' . $field . '` FROM `' . $this->table . '` ORDER BY `' . $field . '` DESC LIMIT 1'))
    {
      if ($sql->getRows() == 0)
        $id = 0;
      else
        $id = $sql->getValue($field);

      $id++;
      $this->setValue($field, $id);

      return $id;
    }

    return false;
  }

  /**
   * Gibt die Spaltennamen des ResultSets zurueck
   */
  public function getFieldnames()
  {
    $this->fetchMeta();
    return $this->fieldnames;
  }
  
  public function getTablenames()
  {
    $this->fetchMeta();
    return $this->tablenames;
  }
  
  private function fetchMeta()
  {
    if($this->fieldnames === NULL || $this->tablenames === NULL)
    {
      $this->fieldnames = array();
      $this->tablenames = array();
      
      for ($i = 0; $i < $this->getFields(); $i++)
      {
        $metadata = $this->stmt->getColumnMeta($i);
        $this->fieldnames[] = $metadata['name'];
        $this->tablenames[] = $metadata['table'];
      }
    }
  }

  /**
   * Escaped den uebergeben Wert fuer den DB Query
   *
   * @param $value den zu escapenden Wert
   */
  public function escape($value)
  {
    return self::$pdo[$this->DBID]->quote($value);
  }

  /**
   * Erstellt das CREATE TABLE Statement um die Tabelle $table
   * der Datenbankverbindung $DBID zu erstellen.
   *
   * @param $table string Name der Tabelle
   * @param $DBID int Id der Datenbankverbindung
   * @return string CREATE TABLE Sql-Statement zu erstsellung der Tabelle
   */
  static public function showCreateTable($table, $DBID=1)
  {
    $sql = self::factory($DBID);
    $array = $sql->getArray("SHOW CREATE TABLE `$table`");
    $create = reset($array);
    $create = $create['Create Table'];
    return $create;
  }

  /**
   * Sucht alle Tabellen der Datenbankverbindung $DBID.
   * Falls $tablePrefix gesetzt ist, werden nur dem Prefix entsprechende Tabellen gesucht.
   *
   * @param $DBID int Id der Datenbankverbindung
   * @param $tablePrefix string Zu suchender Tabellennamen-Prefix
   * @return array Ein Array von Tabellennamen
   */
  static public function showTables($DBID=1, $tablePrefix=null)
  {
    $qry = 'SHOW TABLES';
    if($tablePrefix != null)
    {
		  // replace LIKE wildcards
      $tablePrefix = str_replace(array('_', '%'), array('\_', '\%'), $tablePrefix);
    	$qry .= ' LIKE "'.$tablePrefix.'%"';
    }

    $sql = self::factory($DBID);
    $tables = $sql->getArray($qry);
    $tables = array_map('reset', $tables);

    return $tables;
  }

  /**
   * Sucht Spalteninformationen der Tabelle $table der Datenbankverbindung $DBID.
   *
   * @param $table string Name der Tabelle
   * @param $DBID int Id der Datenbankverbindung
   * @return array Ein Array das die Metadaten enthaelt
   */
  static public function showColumns($table, $DBID=1)
  {
    $sql = self::factory($DBID);
    $sql->setQuery('SHOW COLUMNS FROM '.$table);

    $columns = array();
    while($sql->hasNext())
    {
      $columns [] = array(
        'name' => $sql->getValue('Field'),
        'type' => $sql->getValue('Type'),
        'null' => $sql->getValue('Null'),
        'key' => $sql->getValue('Key'),
        'default' => $sql->getValue('Default'),
        'extra' => $sql->getValue('Extra')
      );
      $sql->next();
    }

    return $columns;
  }

  /**
   * Gibt die Serverversion zurueck.
   *
   * Die Versionsinformation ist erst bekannt,
   * nachdem der rex_sql Konstruktor einmalig erfolgreich durchlaufen wurde.
   */
  static public function getServerVersion($DBID = 1)
  {
    if(!isset(self::$pdo[$DBID]))
    {
      // create connection if necessary
      $dummy = new rex_sql($DBID);
    }
    return self::$pdo[$DBID]->getAttribute(PDO::ATTR_SERVER_VERSION);
  }

  static public function factory($DBID=1, $class=null)
  {
    // keine spezielle klasse angegeben -> default klasse verwenden?
    if(!$class)
    {
      // ----- EXTENSION POINT
      $class = rex_register_extension_point('REX_SQL_CLASSNAME', 'rex_sql',
        array(
          'DBID'      => $DBID
        )
      );
    }

    $obj = new $class($DBID);

    return $obj;
  }

  /**
   * Gibt ein SQL Singelton Objekt zurueck
   *
   * @deprecated since 4.3.0
   */
  public function getInstance($DBID=1, $deprecatedSecondParam = null)
  {
  	return rex_sql::factory($DBID);
  }

  /**
   * Gibt den Speicher wieder frei
   */
  public function freeResult()
  {
    if($this->stmt)
      $this->stmt->closeCursor();
  }

  /**
   * Prueft die uebergebenen Zugangsdaten auf gueltigkeit und legt ggf. die
   * Datenbank an
   */
  static public function checkDbConnection($host, $login, $pw, $dbname, $createDb = false)
  {
    global $REX;
    
    $err_msg = true;
    
    try 
    {
      $conn = self::createConnection(
        $host,
        $dbname,
        $login,
        $pw
      );
    }
    catch (PDOException $e)
    {
      if(strpos($e->getMessage(), 'SQLSTATE[42000]') !== false)
      {
        if($createDb)
        {
          try {
            // use the "mysql" db for the connection
            $conn = self::createConnection(
              $host,
              'mysql',
              $login,
              $pw
            );
            if($conn->exec('CREATE DATABASE '. $dbname) !== 1)
            {
              // unable to create db
              $err_msg = $REX['I18N']->msg('setup_021');
            }
          }
          catch (PDOException $e)
          {
            // unable to find database
            $err_msg = $REX['I18N']->msg('setup_022');
          }
        }
        else
        {
          // unable to find database
          $err_msg = $REX['I18N']->msg('setup_022');
        }
      }
      else if(strpos($e->getMessage(), 'SQLSTATE[28000]') !== false)
      {
        // unable to connect
        $err_msg = $REX['I18N']->msg('setup_021');
      }
      else
      {
        // we didn't expected this error, so rethrow it to show it to the admin/end-user
        throw $e;
      }
    }
    
    // close the connection
    $conn = null;
    
    return  $err_msg;
  }

  public function addGlobalUpdateFields($user = null)
  {
    global $REX;

    if(!$user) $user = $REX['USER']->getValue('login');

    $this->setValue('updatedate', time());
    $this->setValue('updateuser', $user);
  }

  public function addGlobalCreateFields($user = null)
  {
    global $REX;

    if(!$user) $user = $REX['USER']->getValue('login');

    $this->setValue('createdate', time());
    $this->setValue('createuser', $user);
  }

  static public function isValid($object)
  {
    return is_object($object) && is_a($object, 'rex_sql');
  }
}