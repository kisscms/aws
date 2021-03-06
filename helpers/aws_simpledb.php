<?php
/*
	AWS ORM for KISSCMS
	Provides a seamless interface to connect KISSCMS with a SimpleDB backend
	Source: http://github.com/kisscms/aws
	Created by Makis Tracend (@tracend)
*/
if(!class_exists('AWS_SimpleDB') ){

trait SimpleDB {

	function initSDB() {
		// optionally set timestamp fields
		if( !empty( $GLOBALS['config']['aws']['simpleDB_timestamps'] ) ){
			$this->rs['updated'] = '';
			$this->rs['created'] = '';
		}
		if( !empty( $GLOBALS['config']['aws']['simpleDB_soft_delete'] ) ){
			$this->rs['_archive'] = 0;
		}
		// generate the name prefix
		$name = 'sdb_'. $this->tablename; //$this->tablename
		//precaution...
		if( !array_key_exists('db', $GLOBALS) ) $GLOBALS['db'] = array();
		//
		if ( !isset($GLOBALS['db'][$name]) && isset($GLOBALS['api']['aws']) ) {
			try {
				// LEGACY: Instantiate the AmazonSDB class
				//$GLOBALS['db'][$name] = new AmazonSDB();
				//$GLOBALS['db'][$name]->set_hostname( $GLOBALS['config']["aws"]["simpleDB_host"] );
				$GLOBALS['db'][$name] = $GLOBALS['api']['aws']->get('sdb');
			} catch (Exception $e) {
				die('Connection failed: '.$e );
			}
		}

		$this->db = ( isset($GLOBALS['db'][$name]) ) ? $GLOBALS['db'][$name] : null;
	}
/*
//===============================================
// Database Connection
//===============================================
	protected function getdbh( $tablename="" ) {
		// generate the name prefix
		$name = 'sdb_'. $tablename; //$this->tablename
		//precaution...
		if( !array_key_exists('db', $GLOBALS) ) $GLOBALS['db'] = array();
		//
		if ( !isset($GLOBALS['db'][$name]) && isset($GLOBALS['api']['aws']) ) {
			try {
				// LEGACY: Instantiate the AmazonSDB class
				//$GLOBALS['db'][$name] = new AmazonSDB();
				//$GLOBALS['db'][$name]->set_hostname( $GLOBALS['config']["aws"]["simpleDB_host"] );
				$GLOBALS['db'][$name] = $GLOBALS['api']['aws']->get('sdb');
			} catch (Exception $e) {
				die('Connection failed: '.$e );
			}
		}

		return ( isset($GLOBALS['db'][$name]) ) ? $GLOBALS['db'][$name] : null;
	}
*/

//===============================================
// Data methods
//===============================================
	function set($key, $val) {
		if (isset($this->rs[$key]))
			// SimpleDB doesn't support nested arrays (using JSON instead)
			$this->rs[$key] = (is_array($val) || is_object($val)) ? json_encode($val) : $val;
		return $this;
	}

	// try to re-instate the array variables
	function get($key) {
		if( isset( $this->rs[$key] ) ){
			$scalar = $this->rs[$key];
			// try "decoding" the data
			$array = ( is_scalar( $scalar ) ) ? json_decode( $scalar ) : $scalar;
			// #1 FIX : better decoding of db value
			return ( is_object( $array ) || is_array( $array ) ) ? $array : $scalar;
		} else {
			return false;
		}
	}


//===============================================
// CRUD Requests
//===============================================

	// Inserts record into database using the primary key
	// If the primary key is empty, then the PK column should have been set to auto increment
	function create( $key='', $params=array() ) {
		// update timestamps
		if( !empty( $GLOBALS['config']['aws']['simpleDB_timestamps'] ) ){
			// timestamp() global available at KISSCMS > 2.0
			$timestamp = timestamp();
			$this->rs['created'] = $timestamp;
			$this->rs['updated'] = $timestamp;
		}
		// creating the archive flag regardless, in case the "soft delete" option changes in the future
		//if( !empty( $GLOBALS['config']['aws']['simpleDB_soft_delete'] ) ){
			$this->rs['_archive'] = 0;
		//}
		try {
			//$response = $this->db->put_attributes( $this->tablename, $this->rs[$this->pkname], $this->rs );
			 $response = $this->db->putAttributes(array(
				// DomainName is required
				'DomainName' => $this->tablename,
				// ItemName is required
				'ItemName' => $this->rs[$this->pkname],
				// Attributes is required
				'Attributes' => $this->getAttributes()
			));
		} catch (Exception $e) {
			die('Caught exception: '. $e->getMessage() );
		}
		// Success?
		return ( isset($response) ) ? true : false;
	}

	function read( $key ) {
		$query = "SELECT * FROM ". $this->tablename ." WHERE ". $this->pkname ." like '%". $key . "%'";
		if( !empty( $GLOBALS['config']['aws']['simpleDB_soft_delete'] ) ){
			$query .= " AND _archive='0'";
		}
		$results = $this->select( $query );
		// exit if there're no results
		if ( empty($results) ) return false;
		// the result is expected in a one item array
		$rs = array_shift( $results );

		$this->merge($rs);

		return $this->getAll();
	}

	function update() {
		// update timestamps
		if( !empty( $GLOBALS['config']['aws']['simpleDB_timestamps'] ) ){
			// timestamp() global available at KISSCMS > 2.0
			$this->rs['updated'] = timestamp();
		}
		try {
			//$response = $this->db->put_attributes( $this->tablename, $this->rs[$this->pkname], $this->rs, true);
			$response = $this->db->putAttributes(array(
				// DomainName is required
				'DomainName' => $this->tablename,
				// ItemName is required
				'ItemName' => $this->rs[$this->pkname],
				// Attributes is required
				'Attributes' => $this->getAttributes()
			));
		} catch (Exception $e) {
			die('Caught exception: '. $e->getMessage() );
		}
		// Success?
		return ( isset($response) ) ? $this->getAll() : false;
	}

	function delete( $key=false ) {
		if( !empty( $GLOBALS['config']['aws']['simpleDB_soft_delete'] ) ){
			$this->rs['_archive'] = 1;
			return $this->update();
		} else {
			$id = (!$key) ? $this->rs[$this->pkname] : $key;
			try{
				// Legacy request
				//$response = $this->db->delete_attributes( $this->tablename, $id );
				$response = $this->db->deleteAttributes(array(
					// DomainName is required
					'DomainName' => $this->tablename,
					// ItemName is required
					'ItemName' => $id,
					'Attributes' => $this->getAttributes()
				));
			} catch (Exception $e) {
					die('{ "error": '. json_encode($e->getMessage()) .'}');
			}
			// Success?
			return ( isset($response) ) ?  true : false;
		}
	}


//===============================================
// Query methods
//===============================================

	// run a lookup query based on a field
	function findOne($key= false, $value=false){
		//prerequisites
		if(!$key || !$value) return null;
		// variables
		$filter = "";
		if( is_scalar( $value ) ){
			$filter = ( strpos($value, '%') !== FALSE ) ? $key ." LIKE '". $value ."'" : $key ."='". $value ."'";
		} else {
			// assume array?
			$filters = array();
			foreach( $value as $v ){
				$filters[] = $key ." LIKE '%". $v ."%'";
			}
			$filter = implode(" AND ", $filters);
		}
		// execute query
		$results = $this->query( array(
			"filters" => $filter
		));
		// exit if there're no results
		if ( empty($results) ) return false;
		// the result is expected in a one item array
		// Why not use LIMIT 1 in the query instead?
		$rs = array_shift( $results );

		$this->merge($rs);

		return $this->getAll();
	}

	// compine a series of parameters to one query
	function find($a=false, $b=false){
		//prerequisites
		if(!$a) return null;
		// variables
		$filter = "";
		if( is_scalar( $a ) ){
			$filter = $a;
		} else {
			// assume array?
			$params = $a; // $b isn't currently in use....
			$filters = array();
			foreach($params as $key => $value ){
				$filters[] = ( strpos($value, '%') !== FALSE ) ? $key ." LIKE '". $value ."'" : $key ."='". $value ."'";
			}
			$filter = implode(" AND ", $filters);
		}
		// execute query
		$results = $this->query( array(
			"filters" => $filter
		));
		// exit if there're no results
		if ( empty($results) ) return false;
		// the result is expected in a one item array
		// Why not use LIMIT 1 in the query instead?
		$rs = array_shift( $results );

		$this->merge($rs);

		return $this->getAll();
	}

//===============================================
// Table functions
//===============================================

	function check_table() {

		$tables = $this->get_tables();

		if( !in_array($this->tablename, $tables) ){
			// create the domain
			$table = $this->create_table();
			if( !$table ){
				die("Could not connect to the data");
			}
		}

	}


	function create_table($name, $fields, $db=false){

		$response = $this->db->create_domain($this->tablename);
		if ($response->isOK()){
			return true;
		} else {
			return false;
		}

	}

	function get_tables(){

		$response = $this->db->list_domains();
		if ($response->isOK()){
			$domains = (array)$response->body->ListDomainsResult;
			return $domains["DomainName"];
		} else {
			return false;
		}

	}


//===============================================
// Helpers methods
//===============================================

	// General query method
	function select( $selectwhat = '*', $wherewhat = '', $bindings = '' ){

		try {
			$select = $this->db->select( array(
				"SelectExpression" => $selectwhat
				//'NextToken' => '',
				//'ConsistentRead' => true || false,
			));
			// Get all of the <Item> nodes in the response
			$results = $this->normalArray( $select->toArray() );
		} catch (Exception $e) {
			die('Caught exception: '.  $e->getMessage() );
		}
		return (isset($results)) ? $results : NULL;
	}

	// Construct a query from the params
	function query( $params ) {

		// get fields
		if( !empty($params['fields']) ){
			$fields = ( is_scalar($params['fields']) ) ? (string) $params['fields'] : implode(",", $params['fields'] );
		} else {
			$fields = "*";
		}

		// get filters
		$filters = array();
		if( array_key_exists('filters', $params) ){
			if( is_scalar($params['filters']) ){
				$filters[] = (string) $params['filters'];
			} else {
				foreach( $params['filters'] as $k=>$v){
					$filters[]="$k='$v'";
				}
			}
		}

		// escape archived items
		if( !empty( $GLOBALS['config']['aws']['simpleDB_soft_delete'] ) ){
			$filters[] = "_archive='0'";
		}

		// create one string from all the filters
		$filters =  implode(" AND ", $filters);

		$query = 'SELECT '. $fields .' FROM '.$this->tablename;
		if ( !empty($filters) )
			$query .= ' WHERE '.$filters;

		// add order
		if( !empty($params['order']) ){
			if( is_scalar($params['order']) ){
				$order = (string) $params['order'];
			} else {
				$order = $params['order']['field'] .' '. $params['order']['direction'];
			}
			$query .= ' ORDER BY '. $order;
		}

		//add limits
		if( !empty($params['limit']) )
			$query .= ' LIMIT '. $params['limit'];

		$results = $this->select( $query );

		return $results;
	}

	function normalArray($select) {
		$results = array();

		// stop processing if there are no results
		//if( !empty($select->body->SelectResult) ) {
		//	foreach($select->body->SelectResult->Item as $item) {
		if( isset($select["Items"]) ) {
			foreach($select["Items"] as $k => $item) {
				$result = array();
				foreach ($item['Attributes'] as $k => $field) {

					$name = $field["Name"];
					// preserve boolean
					if( $field["Value"] === false || $field["Value"] === 0 || $field["Value"] === "0" ) {
						$value = 0; // use false instead?
					} else {
						$value = ( empty($field["Value"]) ) ? "" : $field["Value"]; // empty arrays are replaced with empty strings
					}

					if( !empty($name) ) $result[$name] = $value;

				}
				$results[]=$result;
			}
		}
		return $results;
	}

	function getAttributes(){
		$attr = array();
		//
		foreach($this->rs as $k=>$v){
			$attr[] = array(
				// Name is required
				'Name' => $k,
				// Value is required
				'Value' => $this->stringify( $v ), // better conversion to string (conditions)
				'Replace' => true, // make this a config option?
			);
		}
		return $attr;
	}

	function stringify( $value="" ){
		// if the value is false save as an integer
		if( $value === false || $value === 0 ){
			return 0;
		} else if( is_array($value) ) {
			return json_encode($value);
		} else {
			// for all other cases simple conversion seems to work
			return (string) $value;
		}

	}

}

class AWS_SimpleDB extends Model {
	use SimpleDB;

	function __construct($db='', $pkname='', $tablename='') {
		$this->initSDB();
		parent::__construct( $this->db, $pkname, $tablename);

	}
}

}

?>
