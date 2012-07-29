<?php
/*
	AWS ORM for KISSCMS
	Provides a seamless interface to connect KISSCMS with a SimpleDB backend
	Source: http://github.com/kisscms/aws
	Created by Makis Tracend (@tracend)
*/

class AWS_SimpleDB extends Model {

//===============================================
// Database Connection
//===============================================
	protected function getdbh() {
		// generate the name prefix
		if (!isset($GLOBALS['db'][ $this->tablename ])) {
			try {
				// Instantiate the AmazonSDB class
				$GLOBALS['db'][ $this->tablename ] = new AmazonSDB();
			 	$GLOBALS['db'][ $this->tablename ]->set_hostname( $GLOBALS['config']["aws"]["simpleDB_host"] );
			} catch (Exception $e) {
				die('Connection failed: '.$e );
			}
		}
		return $GLOBALS['db'][ $this->tablename ];
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
			return ( is_null($array) ) ? $scalar :  $array;
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
		try {
			$response = $this->db->put_attributes( $this->tablename, $this->rs[$this->pkname], $this->rs );
		} catch (Exception $e) {
			die('Caught exception: '.  $e->getMessage() );
		}
		// Success?
		if($response->isOK()){ 
			return $response;
		} else { 
			echo "Error creating your entry";
		}
	}

	function read( $key ) {
		$query = "SELECT * FROM ". $this->tablename ." WHERE ". $this->pkname ." like '%". $key . "%'";
		
		$results = $this->select( $query );
		// exit if there're no results
		if ( empty($results) ) return false;
		// the result is expected in a one item array
		$rs = array_shift( $results );
		
		$this->merge($rs);
		
		return $this;	
		
	}

	function update() {
		
		$response = $this->db->put_attributes( $this->tablename, $this->rs[$this->pkname], $this->rs, true);
		// Success?
		if($response->isOK()){ 
			return $this;
		} else { 
			echo "Error updating your entry";
		}
	}

	function delete() {
		$response = $this->db->delete_attributes( $this->tablename, $this->rs[$this->pkname] );
		// Success?
		if($response->isOK()){ 
			return $this;
		} else { 
			echo "Error deleting your entry";
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
			$select = $this->db->select( $query );
			// Get all of the <Item> nodes in the response
			$results = $this->attrToArray( $select );
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
				$filters =  (string) $params['filters'];
			} else {
				foreach( $params['filters'] as $k=>$v){
					$filters[]="$k='$v'";
				}
				$filters =  implode(" AND ", $filters);
			}
		}
		
		$query = 'SELECT '. $fields .' FROM '.$this->tablename;
		if ( isset($filters) )
			$query .= ' WHERE '.$filters;
			
		// ...add limits?
		
		
		$results = $this->select( $query );
		
		return $results;
	}
	
	
	function attrToArray($select) { 
		$results = array(); 
		
		// stop processing if there are no results
		if( !empty($select->body->SelectResult) ) { 
			foreach($select->body->SelectResult->Item as $item) { 
				$result = array();
				foreach ($item as $field) { 
					$name = (string) $field->Name;
					$value = (string) $field->Value;
					if( !empty($name) ) $result[$name] = $value;
				} 
				$results[]=$result;
			} 
		}
		
		return $results; 
	}

}
?>