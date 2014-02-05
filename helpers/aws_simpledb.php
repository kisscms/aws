<?php
/*
	AWS ORM for KISSCMS
	Provides a seamless interface to connect KISSCMS with a SimpleDB backend
	Source: http://github.com/kisscms/aws
	Created by Makis Tracend (@tracend)
*/
if(!class_exists('AWS_SimpleDB') ){

class AWS_SimpleDB extends Model {

	function __construct($db='', $pkname='', $tablename='') {
		// optionally set timestamp fields
		if( !empty( $GLOBALS['config']['aws']['simpleDB_timestamps'] ) ){
			$this->rs['updated'] = '';
			$this->rs['created'] = '';
		}
		if( !empty( $GLOBALS['config']['aws']['simpleDB_soft_delete'] ) ){
			$this->rs['_archive'] = 0;
		}
		$db = $this->getdbh($tablename);
		parent::__construct( $db, $pkname, $tablename);

	}

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
		if( isset($this->rs[$key]) ){
			$scalar =  $this->rs[$key];
			$array = json_decode( $scalar );
			// #1 FIX : better decoding of db value
			return ( is_object( $array ) || is_array( $array ) ) ? $array : $scalar;
		} else {
			return false;
		}
	}


//===============================================
// Main Requests
//===============================================

	// Inserts record into database using the primary key
	// If the primary key is empty, then the PK column should have been set to auto increment
	function create() {
		// update timestamps
		if( !empty( $GLOBALS['config']['aws']['simpleDB_timestamps'] ) ){
			// timestamp() global available at KISSCMS > 2.0
			$timestamp = timestamp();
			$this->rs['created'] = $timestamp;
			$this->rs['updated'] = $timestamp;
		}
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


	function create_table(){

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
	function select( $query ){
		try {
			$select = $this->db->select( array(
				"SelectExpression" => $query
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
		if( !empty($params['filters']) ){
			if( is_scalar($params['filters']) ){
				$filters = (string) $params['filters'];
			} else {
				foreach( $params['filters'] as $k=>$v){
					$filters[]="$k='$v'";
				}
				$filters =  implode(" AND ", $filters);
			}
		}
		// escape archived items
		if( !empty( $GLOBALS['config']['aws']['simpleDB_soft_delete'] ) ){
			$filters .= " AND _archive='0'";
		}

		$query = 'SELECT '. $fields .' FROM '.$this->tablename;
		if ( isset($filters) )
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
					$value = ( empty($field["Value"]) ) ? "" : $field["Value"]; // empty arrays are replaced with empty strings
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
				'Value' => (string) $v, // better conversion to string (conditions)
				'Replace' => true, // make this a config option?
			);
		}
		return $attr;
	}

}
}
?>