<?php
/*LIBRARY INSTRUCTIONS BELOW*/
class BD{
	private static $query;
	private static $tables;
	private static $values;
	private static $bindings;
	private static $where;
	private static $set;
	private static $conditions;
	private static $conn;

	public function __construct(){}
	
	public static function conn(){
		if(is_null(self::$conn)){		
			self::$conn = new PDO('mysql:host='.HOST.';dbname='.BD.';charset=utf8', ''.USER.'');
			self::$conn->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
			self::$conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, PDO::ERRMODE_WARNING);
			//self::$conn->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
		}
		return self::$conn;
	}
	
	/*  USAGE GENERIC
	$table = 'tablename';
	$values = 'a,b as mybvalue';
	$where = 'a = :a AND b = :b';
	$bindings = array();
	$bindings['a'] = array(
		'type' => 'STR',
		'value' => 'myvalue'
	);
	$bindings['b'] = array(
		'type' => 'INT',
		'value' => 'myvalue'
	);
	X -> $callback = function(){};
	*/

	public static function insert($table, $data){
		self::$tables = $table;
		self::$bindings = $data;
		$out = self::buildQuery("insert");
		return $out;
	}
	public static function delete($table, $where){
		self::$tables = $table;
		self::$bindings = $where;
		$out = self::buildQuery("delete");
		return $out;
	}
	public static function update($table, $setdata, $wheredata, $conditions = ''){

		self::$tables = $table;
		self::$conditions = $conditions;
		self::$bindings = $wheredata;
		self::$set = $setdata;
		$out = self::buildQuery("update");
		return $out;
	}
	public static function select($table, $vals, $data_where = [], $conditions = "") {
		self::$tables = $table;
		self::$conditions = $conditions;
		self::$bindings = $data_where;
		self::$values = $vals;
		$out = self::buildQuery("select");
		
		return $out;
	}

	private static function buildQuery($sql = "select"){
		$selectValues = self::$values;
		$where = '1';
		$values = '';
		$table = self::$tables;
		$arrayValuesString = array();
		$arrayOr = array();
		$bindings = array();
		$increment = 0;
		$likeData = '';
		$insertBind = '';
		$insertValues = '';
		$set = '';
		$counter = 0;

		if(sizeof(self::$set) > 0){
			foreach (self::$set as $key => $value) {
				$counter++;
				$bindkey = ':'.$key.'_'.$counter;
				$bindings[$bindkey] = array(
					'like' => 0,
					'type' => 'STR',
					'value' => $value
				);
				
				if($set == ''){
					$set .= $key.' = '.$bindkey;
				}else{
					$set .= ', '.$key.' = '.$bindkey;
				}
			}
		}
		$dataLength = sizeof(self::$bindings);
		//print_r(self::$bindings);die;
		foreach (self::$bindings as $key => $value) {
			$vartype = gettype($value);
			$_like = (strpos($key, 'LIKE'));
			//echo $_like;die;

			//$arrayValuesString[$key] = $value;
			/*if($_like){
				//$e = explode(' ', $key);
				//$val = trim($e[0]);
				//$key = $val;

				$likeData = 'LIKE `:'.$key.'_1%`';
				$arrayValuesString[$key] = $likeData;
			}else{*/
				$arrayValuesString[$key] = $value;
			/*}*/
			if($_like){
				$e = explode(' ', $key);
				$val = trim($e[0]);
				$_key = $val;
				$bind = ':'.$_key.'_1'; 
			}else{
				$bind = ':'.$key.'_1'; 
			}

			if($vartype == 'string'){
				$bindings[$bind] = array(
					'like' => ($_like?1:0),
					'type' => 'STR',
					'value' => $value
				);
				
				
			/*if(strlen($where) == 0){

					$where .= (self::$_like?$key:$key.' = '.$bind);
				}else{*/
					$concat = ($_like?$key.' '.$bind:$key.' = '.$bind);
					$where .= ' AND '.$concat;
				//}
				if($insertValues == ''){
					$insertValues = $key;
					$insertBind .= $bind;
				}else{
					$insertValues .= ', '.$key;
					$insertBind .= ','.$bind;
				}

				
			}else if($vartype == 'array'){
				$arrayOr[$key] = $value;
			}else{
				$concat = ($_like?$key.' '.$bind:$key.' = '.$bind);
				$where .= ' AND '.$concat;
				$bindings[$bind] = array(
					'like' => ($_like?1:0),
					'type' => 'INT',
					'value' => $value
				);
			}
			//echo $where;die;
			//print_r($arrayOr);die;
			if($dataLength == ($increment+1)){
				$values .= $key;

				//End string values binding, lets bind arrays with OR sql.
				
				
				$flag = 0;

				foreach ($arrayOr as $orkey => $orvalue){
					//echo sizeof($orvalue);die;
					foreach ($orvalue as $childkey => $childvalue) {
						$uniqueid = ($childkey + 1);
						$uniquebinding = $orkey.'_'.$uniqueid;
						$whereclone = '';
						foreach ($arrayValuesString as $strkeys => $strvalue) {

							$struniquebinding = $strkeys.'_'.$uniqueid;
			
							$whereclone .= $strkeys.' = '.$struniquebinding.' AND ';
							$bindings[$struniquebinding] = array(
								'like' => 0,
								'type' => 'INT',
								'value' => $strvalue
							);

						}
						
						if($flag == 0){
							$where .= ' AND '.$orkey.' = '.$uniquebinding;
							$bindings[$uniquebinding] = array(
								'like' => 0,
								'type' => 'INT',
								'value' => $childvalue
							);
						}else{
							//$where .= ' OR '.$whereclone.' '.$orkey.' = '.$uniquebinding;
							if(sizeof($orkey) == $uniqueid){
								$where .= ' OR '.$whereclone;
							}else{
								$where .= ' OR '.$whereclone.' 1 ';
							}
							
							$bindings[$uniquebinding] = array(
								'like' => 0,
								'type' => 'INT',
								'value' => $childvalue
							);	
						}
						

						$flag = 1;
					}
				}
			}else{
				$values .= $key.',';
			}
			$increment++;
		}
		//echo $where;die;
		switch ($sql) {
			case 'select':
				self::$query = "SELECT $selectValues FROM $table WHERE $where".self::$conditions;
				break;
			case 'update':
				self::$query = "UPDATE $table SET $set WHERE $where".self::$conditions;
				//echo self::$query;die;
				break;
			case 'delete':
				self::$query = "DELETE FROM $table WHERE $where";
				break;
			case 'insert':
				self::$query = "INSERT INTO $table ($insertValues) VALUES ($insertBind)";
				break;
			default:
				# code...
				break;
		}
		

		//$r = self::$conn->prepare($q);
		//print_r($bindings);die;
		//echo self::$query.' ';die;
		foreach ($bindings as $bindrefer => &$bindvalue) {
			//print_r($bindvalue);die;
			$type = $bindvalue['type'];
			$_value = $bindvalue['value'];
			$_like = $bindvalue['like'];
			
			//echo $bindrefer.' ';
		
			self::$query = self::bindParams($bindrefer, $_value, $_like);
			//echo self::$query;die;
			/*switch ($type){
				case 'STR':
					$r->bindParam(':'.$bindrefer.'', $_value, PDO::PARAM_STR);
					break;
				case 'INT':
					$valor = (int)$_value;
					$r->bindParam(':'.trim($bindrefer), $valor, PDO::PARAM_INT);
					break;
				default:
					# code...
					break;
			}*/
			
		}
		
		/*$URL = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";*/
		$_SESSION['CURRENT_URL'] = explode('?', $_SERVER['REQUEST_URI'])[0];
		//echo self::$query.' ';die;
		//echo $q;die;
		$r = self::$conn->prepare(self::$query);
		//echo self::$query;die;
		/*try {*/
		try {
			if($r->execute()){
				switch ($sql) {
					case 'select':
						$rows = $r->rowCount();
						//echo $rows;die;
						if($rows == 0){
							return array();
						}else if($rows == 1){
							//$resultados = $r->fetch(PDO::FETCH_ASSOC);
						}else{
							//$resultados = $r->fetchAll(PDO::FETCH_ASSOC);
						}
						$resultados = $r->fetchAll(PDO::FETCH_ASSOC);
						return $resultados;
						# code...
						break;
					default:
						return true;
						break;
				}
				
				
				//print_r($resultados);die;
				
			}
		} catch (PDOException $e) {
			print_r($e);die;
			
		}
	}
	private static function bindParams($search, $replace, $_like){
		try {
			
			//echo $replace;die;
			if($_like == 1){
				$replacing = ' \''.$replace.'%\'';
			}else{
				$check = explode('+', $replace);
				if($check[0] == "fn"){
					$replacing = $check[1];
				}else{
					$replacing = (!is_numeric($replace)?'"'.$replace.'"':$replace);
				}
				
			}
			
			return str_replace($search, $replacing, self::$query);
		} catch (PDOException $e) {
			echo $search.' '.$replace;die;
		}
		
	}
	/* REMOVES ALL EMPTY FIELDS FROM ARRAY */
	public static function sanitizeFormData($array){
		$output = array();
		foreach ($array as $key => $value) {
			$type = gettype($value);
			switch ($type) {
				case 'array':
					$output[$key] = [];
					if(sizeof($value) == 0){
						continue;
					}
					foreach ($value as $k => $val) {
						$output[$key][$k] = trim($val);
					}
					
					break;
				
				default:
					if(strlen($value) == 0){
						continue;
					}
					$output[$key] = trim($value);
					break;
			}
			
		}
		return $output;
	}
}
?>