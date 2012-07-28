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


/*
	// Create the domain
	$new_domain = $sdb->create_domain($domain);

	// Was the domain created successfully?
	if ($new_domain->isOK())
	{
		// Add a batch of item-key-values to your domain
		$add_attributes = $sdb->batch_put_attributes($domain, array(
			'Item_01' => array(
				'Category'    => 'Clothes',
				'Subcategory' => 'Sweater',
				'Name'        => 'Cathair Sweater',
				'Color'       => 'Siamese',
				'Size'        => array('Small', 'Medium', 'Large')
			),
			'Item_02' => array(
				'Category'    => 'Clothes',
				'Subcategory' => 'Pants',
				'Name'        => 'Designer Jeans',
				'Color'       => 'Paisley Acid Wash',
				'Size'        => array('30x32', '32x32', '32x34')
			),
			'Item_03' => array(
				'Category'    => 'Clothes',
				'Subcategory' => 'Pants',
				'Name'        => 'Sweatpants',
				'Color'       => array('Blue', 'Yellow', 'Pink'),
				'Size'        => 'Large',
				'Year'        => array('2006', '2007')
			),
			'Item_04' => array(
				'Category'    => 'Car Parts',
				'Subcategory' => 'Engine',
				'Name'        => 'Turbos',
				'Make'        => 'Audi',
				'Model'       => 'S4',
				'Year'        => array('2000', '2001', '2002')
			),
			'Item_05' => array(
				'Category'    => 'Car Parts',
				'Subcategory' => 'Emissions',
				'Name'        => 'O2 Sensor',
				'Make'        => 'Audi',
				'Model'       => 'S4',
				'Year'        => array('2000', '2001', '2002')
			),
		));

		// Were the attributes added successfully?
		if ($add_attributes->isOK())
		{
			// Add an additional size to Item_01
			$append_attributes = $sdb->put_attributes($domain, 'Item_01', array(
				'Size' => 'Extra Large'
			));

			// Were the new attributes appended successfully?
			if ($append_attributes->isOK())
			{
			 	// Use a SELECT expression to query the data.
				// Notice the use of backticks around the domain name.
				$results = $sdb->select("SELECT * FROM `{$domain}` WHERE Category = 'Clothes'");

				// Get all of the <Item> nodes in the response
				$items = $results->body->Item();

				// Re-structure the data so access is easier (see helper function below)
				$data = reorganize_data($items);

				// Generate <table> HTML from the data (see helper function below)
				$html = generate_html_table($data);
			}
		}
	}

*/

}

/*%******************************************************************************************%*/
// HELPER FUNCTIONS
/*
	function reorganize_data($items)
	{
		// Collect rows and columns
		$rows = array();
		$columns = array();

		// Loop through each of the items
		foreach ($items as $item)
		{
			// Let's append to a new row
			$row = array();
			$row['id'] = (string) $item->Name;

			// Loop through the item's attributes
			foreach ($item->Attribute as $attribute)
			{
				// Store the column name
				$column_name = (string) $attribute->Name;

				// If it doesn't exist yet, create it.
				if (!isset($row[$column_name]))
				{
					$row[$column_name] = array();
				}

				// Append the new value to any existing values
				// (Remember: Entries can have multiple values)
				$row[$column_name][] = (string) $attribute->Value;
				natcasesort($row[$column_name]);

				// If we've not yet collected this column name, add it.
				if (!in_array($column_name, $columns, true))
				{
					$columns[] = $column_name;
				}
			}

			// Append the row we created to the list of rows
			$rows[] = $row;
		}

		// Return both
		return array(
			'columns' => $columns,
			'rows' => $rows,
		);
	}

	function generate_html_table($data)
	{
		// Retrieve row/column data
		$columns = $data['columns'];
		$rows = $data['rows'];

		// Generate shell of HTML table
		$output = '<table cellpadding="0" cellspacing="0" border="0">' . PHP_EOL;
		$output .= '<thead>';
		$output .= '<tr>';
		$output .= '<th></th>'; // Corner of the table headers

		// Add the table headers
		foreach ($columns as $column)
		{
			$output .= '<th>' . $column . '</th>';
		}

		// Finish the <thead> tag
		$output .= '</tr>';
		$output .= '</thead>' . PHP_EOL;
		$output .= '<tbody>';

		// Loop through the rows
		foreach ($rows as $row)
		{
			// Display the item name as a header
			$output .= '<tr>' . PHP_EOL;
			$output .= '<th>' . $row['id'] . '</th>';

			// Pull out the data, in column order
			foreach ($columns as $column)
			{
				// If we have a value, concatenate the values into a string. Otherwise, nothing.
				$output .= '<td>' . (isset($row[$column]) ? implode(', ', $row[$column]) : '') . '</td>';
			}

			$output .= '</tr>' . PHP_EOL;
		}

		// Close out our table
		$output .= '</tbody>';
		$output .= '</table>';

		return $output;
	}


?><!DOCTYPE html>
<html>
	<head>
		<meta http-equiv="Content-type" content="text/html; charset=utf-8">
		<title>sdb_create_domain_data</title>
		<style type="text/css" media="screen">
		body {
			margin: 0;
			padding: 0;
			font: 14px/1.5em "Helvetica Neue", "Lucida Grande", Verdana, Arial, sans;
			background-color: #fff;
			color: #333;
		}
		table {
			margin: 50px auto 0 auto;
			padding: 0;
			border-collapse: collapse;
		}
		table th {
			background-color: #eee;
		}
		table td,
		table th {
			padding: 5px 10px;
			border: 1px solid #eee;
		}
		table td {
			border: 1px solid #ccc;
		}
		</style>
	</head>
	<body>

		<!-- Display HTML table -->
		<?php echo $html; ?>

	</body>
</html>
*/