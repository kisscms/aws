<?php
/*
	AWS ORM for KISSCMS
	Provides a seamless interface to connect KISSCMS with a Simple DB backend
	Homepage: http://kisscms.com/plugins
	Created by Makis Tracend (@tracend)
*/

class AWS_S3 extends Model {

//===============================================
// Database Connection
//===============================================
	protected function getdbh() {
		$name = 's3_'. $this->tablename;
		// generate the name prefix
		if (!isset($GLOBALS['db'][$name])) {
			try {
				// Instantiate the AmazonSDB class
				$GLOBALS['db'][$name] = new AmazonS3();
			 	$GLOBALS['db'][$name]->set_region( $GLOBALS['config']["aws"]["s3_region"] );
			} catch (Exception $e) {
				die('Connection failed: '.$e );
			}
		}
		return $GLOBALS['db'][$name];
	}


	function create($key, $params=array()) {
		// trigger the AWS service
		$response = $this->db->create_object( $this->tablename, $key, $params);
		// Success?
		return ($response->isOK()) ? true : false;
	}

	function read( $key ) {
		
	}

	function update() {
		
	}

	function delete( $key=false ) {
		$id = (!$key) ? $this->rs[$this->pkname] : $key;
		// trigger the AWS service
		$response = $this->db->delete_object( $this->tablename, $id );
		// Success?
		return ($response->isOK()) ?  true : false;
	}


}