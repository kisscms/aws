<?php
/*
	AWS ORM for KISSCMS
	Provides a seamless interface to connect KISSCMS with a SimpleDB backend
	Homepage: http://kisscms.com/plugins
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

	// DATA
	
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
	
	// REQUESTS
	//Inserts record into database with a new auto-incremented primary key
	//If the primary key is empty, then the PK column should have been set to auto increment
	function create() {
		try {
			$response = $this->db->put_attributes( $this->tablename, $this->rs['id'], $this->rs );
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
		try {
			$select = $this->db->select( $query );
		} catch (Exception $e) {
			die('Caught exception: '.  $e->getMessage() );
		}
		// Get all of the <Item> nodes in the response
		$results = $this->attrToArray( $select );
		// exit if there's a false request
		if (!$results) return false;
		// the result is expected in a one item array
		$rs = array_shift( $results );
		
		$this->merge($rs);
		/*foreach ($result as $key => $val){ 
			if (isset($this->rs[$key])){ 
				//$this->rs[$key] = $val;
				$this->rs[$key] = is_scalar($this->rs[$key]) ? $val : unserialize($this->COMPRESS_ARRAY ? gzinflate($val) : $val);
			}
		}*/
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


	//returns true if primary key is a positive integer
	//if checkdb is set to true, this function will return true if there exists such a record in the database
	function exists($checkdb=false) {
		if ((int)$this->rs[$this->pkname] < 1)
			return false;
		if (!$checkdb)
			return true;
		$sql = 'SELECT 1 FROM '.$this->enquote($this->tablename).' WHERE '.$this->enquote($this->pkname)."='".$this->rs[$this->pkname]."'";
		$result = $this->db->query($sql)->fetchAll();
		return count($result);
	}
	
	/*
	function retrieve_one($wherewhat,$bindings) {
		$dbh=$this->getdbh();
		if (is_scalar($bindings))
			$bindings=$bindings ? array($bindings) : array();
		$sql = 'SELECT * FROM '.$this->enquote($this->tablename);
		if (isset($wherewhat) && isset($bindings))
			$sql .= ' WHERE '.$wherewhat;
		$sql .= ' LIMIT 1';
		$stmt = $dbh->prepare($sql);
		$stmt->execute($bindings);
		$rs = $stmt->fetch(PDO::FETCH_ASSOC);
		if (!$rs)
			return false;
		foreach ($rs as $key => $val)
			if (isset($this->rs[$key]))
				$this->rs[$key] = is_scalar($this->rs[$key]) ? $val : unserialize($this->COMPRESS_ARRAY ? gzinflate($val) : $val);
		return $this;
	}


	function retrieve_many($wherewhat='',$bindings='') {
		$dbh=$this->getdbh();

		if (is_scalar($bindings))
			$bindings=$bindings ? array($bindings) : array();
		$sql = 'SELECT * FROM '.$this->tablename;
		if ($wherewhat)
			$sql .= ' WHERE '.$wherewhat;
		
		$select = $dbh->select( $sql );
		// Get all of the <Item> nodes in the response
		//$arr = $results->body->SelectResult->to_array()->getArrayCopy();
		$results = $this->convertSelectToArray($select); // convert AWS object to array 

		return $results;
	}

	function select($selectwhat='*',$wherewhat='',$bindings='',$pdo_fetch_mode=PDO::FETCH_ASSOC) {
		$dbh=$this->getdbh();
		if (is_scalar($bindings))
			$bindings=$bindings ? array($bindings) : array();
		$sql = 'SELECT '.$selectwhat.' FROM '.$this->tablename;
		if ($wherewhat)
			$sql .= ' WHERE '.$wherewhat;
		$stmt = $dbh->prepare($sql);
		$stmt->execute($bindings);
		return $stmt->fetchAll($pdo_fetch_mode);
	}
	*/
	
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
	
	/* Helper functions */
	function convertSelectToArray($select) { 
		$results = array(); 
		$x = 0; 
		if( !empty($select->body->SelectResult) ) {
			foreach($select->body->SelectResult->Item as $result) { 
				foreach ($result as $field) { 
					$results[$x][ (string) $field->Name ] = (string) 
					$field->Value; 
				} 
				$x++; 
			} 
		}
		return $results; 
	} 
	
	function attrToArray($select) { 
		$results = array(); 
		$x = 0;
		
		// stop processing if there are no results
		if( empty( $select->body->SelectResult ) ) return false;
		 
		foreach($select->body->SelectResult->Item as $result) { 
			
			//$rs = array_shift( $result ); // fix to remove the id from the array
		
			foreach ($result as $field) { 
				$key = (string) $field->Name;
				$val = (string) $field->Value;
				if( !empty($key) ) $results[$x][ $key ] = $val; 
			} 
			$x++; 
		} 
		return $results; 
	}

}