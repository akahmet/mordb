<?php
/**
* mordb class
*AK
*/
namespace dun;

class mordb{
	private $VPdo;
	private $sql;
	private $bind = [];
	private $f_all;
	private $table;
	public $messages;
	

	function __construct($username,$password,$host,$dbname,$options = array()) {
		try {
			$this->VPdo = new \PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password, $options);
			
		} catch (PDOException $e) {
			print "Error!: " . $e->getMessage() . "<br/>";
			die();
		}
	}
	

	function table($table){
		$this->table = $table;
		return $this;
	}
	

	function orderBy($code,$param = null){ 
		if(empty($this->sql)){
			$this->sql = "Select * from $this->table";
		}
		$this->sql .= " ORDER BY '$code'";
		if(!empty($param))
			$this->sql .= " $param";
		return $this;
	}

	function add($code){ 
		if(empty($this->sql)){
			$this->sql = "Select * from $this->table";
		}
		$this->sql .= " $code";
		return $this;
	}
	

	function onlyRun(){
		if(empty($this->sql)){
			$this->sql = "Select * from $this->table";
		}
		$deyim = $this->run();
		return $deyim;
	}
	
	function limit($Start,$limit = null){
		if(empty($this->sql)){
			$this->sql = "Select * from $this->table";
		}
		if($limit == null){
			$this->sql .= " Limit $Start";
		}else{
			$this->sql .= " Limit $Start,$limit";
		}
		
		return $this;
	}
	
	/**
    * Add Where your sql code
	* Examples: Where("x","y") => "x = y"
	*			Where("x","=","y") => "x = y"
	*			Where(array('x' => 'y')) => "x = y"
    */
	function where($where, $conditionals = "", $data = ""){
		if(empty($this->sql)){
			$this->sql = "Select * from $this->table";
		}
		if(is_array($where)){
			$this->sql .= " WHERE";
			foreach($where as $w => $c){
				$this->sql .= " `$w` = ? AND";
				$this->bind[] = $c;
			}
			$this->sql = $this->str_lreplace("AND","",$this->sql);
		}elseif(!empty($data)){
			$this->sql .= " WHERE `$where` $conditionals ?";
			$this->bind[] = $data;
		}else{
			$this->sql .= " WHERE `$where` = ?";
			$this->bind[] = $conditionals;
		}
		return $this;
	}
	
	/**
    * Return first data
    */
	function first(){
		if(empty($this->sql)){
			$this->sql = "Select * from $this->table";
		}
		$deyim = $this->run();
		$r = (object)$deyim->fetch();
		if(isset($r->scalar)){
			if(!$r->scalar){
				return false;
			}
		}
		return $r;
	}
	
	/**
    * Return all data
    */
	function all(){
		if(empty($this->sql)){
			$this->sql = "Select * from $this->table";
		}
		$deyim = $this->run();
		$r = (array)$deyim->fetchAll();
		if(isset($r->scalar)){
			if(!$r->scalar){
				return false;
			}
		}
		return $r;
	}
	
	/**
    * Run sql codes
    */
	private function run(){ 
			$this->showSql();
			$deyim = $this->VPdo->prepare($this->sql);
			if($deyim->execute($this->bind) !== false) {
				$this->sql = null;
				$this->bind = null;
				return $deyim;
			}
			$this->sql = null;
			$this->bind = null;

	}
	
	/**
    * Save or Update data
    */
	function save($data){
		if(!empty($this->table)){
			$columns = $this->query("SHOW COLUMNS FROM $this->table");
			if(empty($columns)){ die("Table name not found"); }

			$r = [];
			$ir = [];
			$auto = null;
			$lastField = null;
			foreach($columns as $column){
				if($column["Extra"] == "auto_increment"){
					if(!isset($data->{$column["Field"]})){
						$data->{$column["Field"]} = "null";
					}
					$auto = $column["Field"];
				}
				if(!isset($data->{$column["Field"]})){
					$this->messages[] = $column["Field"] . " is required";
					return false;
				}
				$r[$column["Field"]] = $data->{$column["Field"]};
				$ir[] = $column["Field"];
				$lastField = $column["Field"];
			}
			if($auto != null){
				$Control = $this->table($this->table)->where($auto,$r[$auto])->first();

				if(isset($Control->scalar)){
					if(!$Control->scalar){
						if($this->insert($data,$ir) > 0){
							return true;
						}else{
							return false;
						}
					}
				}else{
					if($this->update($data,$ir,$auto,$r[$auto]) > 0){
						return true;
					}else{
						return false;
					}
				}
			}else{
				$this->messages[] = "At least one auto_increment field is required for update";
				if($this->insert($data,$ir) > 0){
					return true;
				}else{
					return false;
				}
			}
		}
	}
	
	/**
    * Clear Tables
    */
	
	function clear(){
		if(!empty($this->table)){

			$rd = $this->Query("TRUNCATE $this->table");
		
			 if(isset($rd) and !empty($rd)){
				return true;
			}else{
				return false;
			} 
		}
	}
	
	/**
    * Delete Tables
    */
	function delete($info,$id = ""){
		if(!empty($this->table) and !empty($info)){
			if($id != ""){
				$rd = $this->Query("DELETE FROM `$this->table` WHERE `id` = $id");
			}else{
				if(isset($info->id)){
					$rd = $this->Query("DELETE FROM `$this->table` WHERE `id` = $info->id");
				}
			}
			if(isset($rd) and !empty($rd)){
				return true;
			}else{
				return false;
			}
		}
	}
	
	/**
    * Update Function 
    */
	
	private function update($info,$fields,$ff,$v){
		if(!empty($this->table)){
			$fieldSize = sizeof($fields);

			$sql = "UPDATE " . $this->table . " SET ";
			for($f = 0; $f < $fieldSize; ++$f) {
				if($f > 0)
					$sql .= ", ";
					$sql .= $fields[$f] . " = :update_" . $fields[$f]; 
			}
			$sql .= " WHERE `$ff` = '" . $v . "';";

			$this->sql = $sql;
	
			$bind = [];
			foreach($fields as $field)
				$bind[":update_$field"] = $info->$field;
			
			$this->bind = $bind;	
			$deyim = $this->run();
			return $deyim->rowCount();
		}
	}
	
	/**
    * Insert Function 
    */
	private function insert($info,$fields){
		if(!empty($this->table)){

			$this->sql = "INSERT INTO " . $this->table . " (" . implode($fields, ", ") . ") VALUES (:" . implode($fields, ", :") . ");";
			$bind = array();
			foreach($fields as $fieldd){

					$bind[":$fieldd"] = $info->$fieldd;
				
			}
				
			
			$this->bind = $bind;
			
			$deyim = $this->run();
			return $deyim->rowCount();
		} 
	}
	
	/**
    * Sql Query Function
    */
	
	function query($sql){
		return $this->VPdo->query($sql);
	}
	
	
	/**
    * Show Sql Queries
    */
	private function showSql(){
		echo "<hr>";
		echo $this->sql . "<br />";
		print_r($this->bind);
		echo "<hr>"; 
	}
	
	/**
    * Replace last info
    */
	private function str_lreplace($search, $replace, $subject){
		$pos = strrpos($subject, $search);

		if($pos !== false)
		{
			$subject = substr_replace($subject, $replace, $pos, strlen($search));
		}

		return $subject;
	}
	
	
}
?>
