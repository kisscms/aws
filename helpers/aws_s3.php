<?php
/*
	AWS ORM for KISSCMS
	Provides a seamless interface to connect KISSCMS with a Simple DB backend
	Homepage: http://kisscms.com/plugins
	Created by Makis Tracend (@tracend)
*/

class AWS_S3 extends KISS_Model {

//===============================================
// Database Connection
//===============================================
	protected function getdbh() {
		// generate the name prefix
		$db_name = "aws_sdb";
		if (!isset($GLOBALS[ $db_name ])) {
			try {
				// Instantiate the AmazonSDB class
				$GLOBALS[ $db_name ] = new AmazonSDB();
			} catch (Exception $e) {
				die('Connection failed: '.$e );
			}
		}
		return $GLOBALS[ $db_name ];
		//return call_user_func($this->dbhfnname, $this->db);
	}


	//Inserts record into database with a new auto-incremented primary key
	//If the primary key is empty, then the PK column should have been set to auto increment
	function create() {
		$dbh=$this->getdbh();
		//$pkname=$this->pkname;
		
		$response = $dbh->put_attributes( $this->tablename, $this->rs['id'], $this->rs );
		// Success?
		if($response->isOK()){ 
			//$this->set($pkname, strtotime("now") );
			return $response;
		} else { 
			echo "Error creating your entry";
		}
	}

	function retrieve( $key ) {
		$dbh=$this->getdbh();
		//$sql = 'SELECT * FROM '.$this->enquote($this->tablename).' WHERE '.$this->enquote($this->pkname).'=?';
		//$results = $dbh->select( $sql );
		$select = $dbh->get_attributes($this->tablename, $key );
		// Get all of the <Item> nodes in the response
		$result = $this->attrToArray( $select );
		$rs = array_shift( $result ); // fix to just get one entry
		//var_dump($rs);
		if ($rs)
			foreach ($rs as $key => $val)
				if (isset($this->rs[$key]))
					$this->rs[$key] = is_scalar($this->rs[$key]) ? $val : unserialize($this->COMPRESS_ARRAY ? gzinflate($val) : $val);
					//$this->rs[$key] = $val;
		return $this;
	}

	function update() {
		$dbh=$this->getdbh();
		
		$response = $dbh->put_attributes( $this->tablename, $this->rs['id'], $this->rs, true);
		// Success?
		if($response->isOK()){ 
			return $this;
		} else { 
			echo "Error updating your entry";
		}
	}

	function delete() {
		$dbh=$this->getdbh();
		$response = $dbh->delete_attributes( $this->tablename, $this->rs['id'] );
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
		$dbh=$this->getdbh();
		$sql = 'SELECT 1 FROM '.$this->enquote($this->tablename).' WHERE '.$this->enquote($this->pkname)."='".$this->rs[$this->pkname]."'";
		$result = $dbh->query($sql)->fetchAll();
		return count($result);
	}

	function merge($arr) {
		if (!is_array($arr))
			return false;
		foreach ($arr as $key => $val)
			if (isset($this->rs[$key]))
				$this->rs[$key] = $val;
		return $this;
	}

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
	
	
	function check_bucket() {
	
		$tables = $this->get_tables();

		/*if( !in_array($this->tablename, $tables) ){ 
			// create the domain
			$table = $this->create_table();
			if( !$table ){ 
				die("Could not connect to the data");
			}
		}*/
		
	}
	

	function create_bucket(){
	
		$dbh= $this->getdbh();
		
		/*$response = $dbh->create_domain($this->tablename); 
		if ($response->isOK()){
			return true;
		} else {
			return false;
		}*/
	
	}
	
	function get_buckets(){
	  
		$dbh= $this->getdbh();
		/*$response = $dbh->list_domains();
		if ($response->isOK()){
			$domains = (array)$response->body->ListDomainsResult;
			return $domains["DomainName"];
		} else {
			return false;		
		}*/
	
	}
	

}