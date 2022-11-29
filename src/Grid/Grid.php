<?php

namespace TheMoiza\Crud\Grid;

use TheMoiza\Crud\Util\Strings;

class Grid{

	protected $_config;

	protected $_connection;

	protected $_sql = '';

	protected $_fetch = [];

	protected $_page = 1;

	protected $_byPage = 100;

	protected $_total = 0;

	protected $_totalPages = 1;

	protected $_errorInfo = [];

	protected $_gmt = 'gmt-03:00';

	protected $_gmt_time_string = '';

    function __construct($config, $connection){

		$this->_config = $config;

		$this->_connection = $connection;

		if(isset($_GET['page']) and is_numeric($_GET['page']) and $_GET['page'] > 0){

			$this->_page = (int) $_GET['page'];
		}

		if(isset($this->_config->restrict, $this->_config->restrict['byPage']) and is_numeric($this->_config->restrict['byPage'])){

			$this->_byPage = $this->_config->restrict['byPage'];
		}

		if(isset($_GET['allpages'])){

			$this->_byPage = 999999999;
			$this->_config->restrict['byPage'] = 999999999;
		}
	}
	
	private function _getPrimaryKey() :string{

		$table = $this->_getTable();

		return key($table);
	}

    private function _getGrid(){

        $name = $this->_getTableName();

        if(isset($this->_config->grid, $this->_config->grid[$name]) and is_array($this->_config->grid[$name])){

            return $this->_config->grid[$name];
        }

        return false;
    }

    private function _getTable(){

        $name = $this->_getTableName();

        if(isset($this->_config->table, $this->_config->table[$name]) and is_array($this->_config->table[$name])){

            return $this->_config->table[$name];

        }

        return false;
    }

    private function _getTableName(){

        if(isset($this->_config->table) and is_array($this->_config->table)){

            $table = $this->_config->table;

            return key($table);

        }

        return false;
	}

	private function _makeIlike(){

		$grid = $this->_getGrid();

		$name = $this->_getTableName();

		$dbType = $this->_connection->getAttribute(\PDO::ATTR_DRIVER_NAME);

		if(isset($_GET) and is_array($_GET)){

			if($this->_config->restrict['where'] == false){
				$this->_config->restrict['where'] = [];
			}

			foreach($_GET as $col => $search){

				$search = trim($search, ' \t\r\n');

				$search = Strings::unaccent($search);

				if(isset($grid[$col], $grid[$col]['show']) and $grid[$col]['show'] === true and !isset($grid[$col]['concat']['extraSearch'])){

					if($dbType == 'pgsql'){

						$this->_config->restrict['where'][] = 'unaccent('.$name.'.'.$col."::text) ILIKE '%%".$search."%%'";
					}
				}
			}

			// ADICIONAL SEARCH WITH EXTRA COLUMNS
			foreach($grid as $col => $paramns){

				if(isset($paramns['concat'], $paramns['concat']['columns'])){

					foreach($paramns['concat']['columns'] as $col => $alias){

						if(isset($_GET[$alias])){

							$search = trim($_GET[$alias], ' \t\r\n');

							$search = Strings::unaccent($search);

							if($dbType == 'pgsql'){

								$this->_config->restrict['where'][] = 'unaccent('.$col."::text) ILIKE '%%".$search."%%'";
							}
						}
					}
				}
			}

			// ADICIONAL SEARCH
			$orSearch = [];
			foreach($grid as $col => $paramns){

				if(isset($paramns['concat'], $paramns['concat']['extraSearch']) and is_array($paramns['concat']['extraSearch'])){

					foreach($paramns['concat']['extraSearch'] as $col => $alias){

						if(isset($_GET[$alias])){

							$search = trim($_GET[$alias], ' \t\r\n');

							$search = Strings::unaccent($search);

							if($dbType == 'pgsql'){

								$orSearch[$alias][] = 'unaccent('.$col."::text) ILIKE '%%".$search."%%'";
							}
						}
					}
				}
			}

			if(count($orSearch) > 0){

				foreach($orSearch as $line){
					$this->_config->restrict['where'][] = '('.implode(' or ', $line).')';
				}
			}
		}
	}

	private function _makeFilters(){

		if(isset($this->_config->filters)){

			foreach($this->_config->filters as $name => $filter){

				if(isset($_GET[$name])){

					$ex = explode(',', $_GET[$name]);

					if($filter['type'] == 'list'){

						$or = [];

						// CAST = varchar
						if(isset($filter['cast']) and $filter['cast'] == 'varchar'){

							foreach($ex as $val){
	
								$val = str_replace("'", "\'", $val);
								$or[] = $filter['column'].' = \''.$val.'\'';
							}

						// whereType = ilike
						} else if(isset($filter['whereType']) and $filter['whereType'] == 'ilike'){

							foreach($ex as $val){
	
								$val = str_replace("'", "\'", $val);
								$or[] = $filter['column'].' ILIKE \'%'.$val.'%\'';
							}

						// NORMAL
						}else{

							foreach($ex as $val){
	
								$val = str_replace("'", "\'", $val);
								$or[] = $filter['column'].' = '.$val;
							}
						}

						$this->_config->restrict['where'][] = '('.implode(' or ', $or).')';
					}

					if($filter['type'] == 'dateinterval'){

						$start = false;
						$end = false;

						// VALID START DATE
						if(isset($ex[0]) and !empty($ex[0]) and strtotime($ex[0]) !== false){

							$start = $ex[0];
						}

						// VALID END DATE
						if(isset($ex[1]) and !empty($ex[1]) and strtotime($ex[1]) !== false){

							$end = $ex[1];
						}

						// START AND END
						if($start !== false and $end !== false){

							$this->_config->restrict['where'][] = $filter['column'].' >= \''.$start.'\' and '.$filter['column'].' <= \''.$end.'\'';

						// START ONLY
						}else if($start !== false and $end === false){

							$this->_config->restrict['where'][] = $filter['column'].' >= \''.$start.'\'';

						// END ONLY
						}else if($start === false and $end !== false){

							$this->_config->restrict['where'][] = $filter['column'].' <= \''.$end.'\'';
						}
					}

					if($filter['type'] == 'competenceinterval'){

						$start = false;
						$end = false;

						// VALID START DATE
						if(isset($ex[0]) and !empty($ex[0]) and strtotime($ex[0]) !== false){

							$start = str_replace('-', '', $ex[0]);
						}

						// VALID END DATE
						if(isset($ex[1]) and !empty($ex[1]) and strtotime($ex[1]) !== false){

							$end = str_replace('-', '', $ex[1]);
						}

						// START AND END
						if($start !== false and $end !== false){

							$this->_config->restrict['where'][] = $filter['column'].' >= \''.$start.'\' and '.$filter['column'].' <= \''.$end.'\'';

						// START ONLY
						}else if($start !== false and $end === false){

							$this->_config->restrict['where'][] = $filter['column'].' >= \''.$start.'\'';

						// END ONLY
						}else if($start === false and $end !== false){

							$this->_config->restrict['where'][] = $filter['column'].' <= \''.$end.'\'';
						}
					}

					if($filter['type'] == 'isnull'){

						if(isset($_GET[$name])){

							if($_GET[$name] == 1){

								$this->_config->restrict['where'][] = $filter['column'].' IS NOT NULL';
							}

							if($_GET[$name] == 2){

								$this->_config->restrict['where'][] = $filter['column'].' IS NULL';
							}
						}
					}
				}
			}
		}
	}

	private function _makeWhere(){

		$this->_makeIlike();

		$this->_makeFilters();

		if(isset($this->_config->restrict, $this->_config->restrict['where']) and is_array($this->_config->restrict['where']) and count($this->_config->restrict['where']) > 0){

			$where = implode(' AND ', $this->_config->restrict['where']);

			// NOT SQL INJECTION
			/*$where = str_replace("'", "\'", $where);*/
			$where = str_replace("\'%%", "'%", $where);
			$where = str_replace("%%\'", "%'", $where);

			return 'WHERE '.$where;
		}

		return '';
	}

	private function _makeOrderBy(){

		if(isset($this->_config->restrict, $this->_config->restrict['orderBy']) and is_array($this->_config->restrict['orderBy']) and count($this->_config->restrict['orderBy']) > 0){

			$orderBy = implode(', ', $this->_config->restrict['orderBy']);

			// NOT SQL INJECTION
			$orderBy = str_replace("'", "\'", $orderBy);

			return 'ORDER BY '.$orderBy;
		}

		return '';
	}

	private function _makeJoins(){

		$name = $this->_getTableName();

		$table = $this->_getTable();

		$grid = $this->_getGrid();

		$joins = [];
		foreach($grid as $col => $params){

			if(isset($params['show']) and $params['show'] === true and isset($params['concat'])){

				if(isset($table[$col], $table[$col]['joins'])){

					$leftJoins = $table[$col]['joins'];

					if(count($leftJoins) > 0){

						foreach ($leftJoins as $join => $ons) {
							$joins[] = 'LEFT JOIN '.$join.' ON '.$ons;
						}
					}
				}
			}

			if(isset($params['invisible']) and $params['invisible'] === true and isset($params['concat'])){

				if(isset($table[$col], $table[$col]['joins'])){

					$leftJoins = $table[$col]['joins'];

					if(count($leftJoins) > 0){

						foreach ($leftJoins as $join => $ons) {
							$joins[] = 'LEFT JOIN '.$join.' ON '.$ons;
						}
					}
				}
			}
		}

		if(count($joins) > 0){
			return implode(PHP_EOL, $joins);
		}

		return '';
	}

	private function _makeCols(){

		$name = $this->_getTableName();

		$table = $this->_getTable();

		$grid = $this->_getGrid();

		$sql = false;

		if($name and $table and $grid){

			$cols = [];
			foreach($grid as $col => $params){

				if($name == 'order'){
					$name = '"'.$name.'"';
				}

				$alias = $name;

				if(isset($params['show']) and $params['show'] === true and !isset($params['subquery'])){

					if(isset($params['concat'], $params['concat']['concat'])){

						$separator = $params['concat']['separator'] ?? ' - ';

						$concats = $params['concat']['concat'];

						$cols[] = 'concat('.implode(',\''.$separator.'\',', $concats).') AS '.$col;

					}else{

						if(isset($params['alias'])){

							$alias = $params['alias'];
						}

						$cols[] = $alias.'.'.$col.' AS '.$col;
					}

					if(isset($params['concat'], $params['concat']['columns'])){

						if(isset($params['concat'], $params['concat']['columns'])){

							$columns = $params['concat']['columns'];

							if(count($columns) > 0){

								foreach($columns as $a => $b){

									$cols[] = $a.' AS '.$b;
								}
							}
						}
					}
				}

				if(isset($params['invisible']) and $params['invisible'] === true and !isset($params['subquery'])){

					if(isset($params['concat'], $params['concat']['concat'])){

						$separator = $params['concat']['separator'] ?? ' - ';

						$concats = $params['concat']['concat'];

						$cols[] = 'concat('.implode(',\''.$separator.'\',', $concats).') AS '.$col;

					}else{

						if(isset($params['alias'])){

							$alias = $params['alias'];
						}

						$cols[] = $alias.'.'.$col.' AS '.$col;
					}

					if(isset($params['concat'], $params['concat']['columns'])){

						if(isset($params['concat'], $params['concat']['columns'])){

							$columns = $params['concat']['columns'];

							if(count($columns) > 0){

								foreach($columns as $a => $b){

									$cols[] = $a.' AS '.$b;
								}
							}
						}
					}
				}

				if(isset($params['subquery']) and !empty($params['subquery'])){
					$cols[] = $params['subquery'];
				}
			}

			if(count($cols) > 0){

				$cols = implode(','.PHP_EOL."\t", $cols);

				// CT LANG PARAM
				$cols = str_replace(':ctLang', $this->_getCtLang(), $cols);

				return $cols;
			}
		}

		$primaryKey = $this->_getPrimaryKey();
		return $this->_getTableName().'.'.$primaryKey.' AS '.$primaryKey;
	}

	private function _getCtLang(){

		$ctLang = 'pt';
		
		if(isset($this->_config->ctLang) and in_array($this->_config->ctLang, ['en', 'pt', 'es', 'it', 'fr'])){

			$ctLang = $this->_config->ctLang;
		}

		return $ctLang;
	}

	private function _getOffset(){

		if($this->_page <= 1){

			return 0;

		}

		return ($this->_page * $this->_byPage) - $this->_byPage;
	}

	private function _createSqlCount(){

		$name = $this->_getTableName();

		$primaryKey = $this->_getPrimaryKey();

		if($name == 'order'){
			$name = '"'.$name.'"';
		}

		return '
			SELECT 
				count('.$name.'.'.$primaryKey.') AS total
			FROM '.$name.' AS '.$name.'
			'.$this->_makeJoins().'
			'.$this->_makeWhere();
	}

	private function _createSql(){

		$name = $this->_getTableName();

		$nameScape = $name;

		if($name == 'order'){
			$nameScape = '"'.$name.'"';
		}

		$limit = 'LIMIT '.$this->_byPage.' offset '.$this->_getOffset();

		return '
			SELECT 
				'.$this->_makeCols().'
			FROM '.$nameScape.' AS '.$nameScape.'
			'.$this->_makeJoins().'
			'.$this->_makeWhere().'
			'.$this->_makeOrderBy().'
			'.$limit;
	}

	private function _processActions($row, $actions){

		$return = '';
		foreach($actions as $fn){

			$return .= $fn($row);
		}

		return $return;
	}

	private function _processEditRemove(){

		$actions = false;
		$edit = false;
		$remove = false;

		if(isset($this->_config->restrict, $this->_config->restrict['actions']) and $this->_config->restrict['actions'] !== false){
			$actions = $this->_config->restrict['actions'];
		}

		if(isset($this->_config->restrict, $this->_config->restrict['edit']) and $this->_config->restrict['edit'] === true){
			$edit = true;
		}
		if(isset($this->_config->restrict, $this->_config->restrict['remove']) and $this->_config->restrict['remove'] === true){
			$remove = true;
		}

		foreach($this->_fetch as $key => $arr){

			$this->_fetch[$key]->actions = false;
			$this->_fetch[$key]->edit = false;
			$this->_fetch[$key]->remove = false;

			if($actions !== false){
				$this->_fetch[$key]->actions = $this->_processActions($arr, $actions);
			}

			if($edit === true){
				$this->_fetch[$key]->edit = true;
			}

			if($remove === true){
				$this->_fetch[$key]->remove = true;
			}
		}

		return $this;
	}

	public function setGmt($gmt){

		$this->_gmt = $gmt;

		return $this;
	}

	public function makeThead(){

		$cols = [];
		$search = [];

		$name = $this->_getTableName();

		$grid = $this->_getGrid();

		foreach($grid as $col => $params){

			if(isset($params['show']) and $params['show'] === true){

				$thsize = '';
				if(isset($params['thsize']) and !empty($params['thsize'])){
					$thsize = 'style="width: '.$params['thsize'].'px"';
				}

				$thclass = false;
				if(isset($params['thclass']) and !empty($params['thclass'])){
					$thclass = $params['thclass'];
				}

				$tooltip = false;
				if(isset($props['tooltip'])and $props['tooltip'] === true){
					$tooltip = true;
				}
				$search = false;

				if(isset($params['search']) and $params['search'] === true){

					//$search[$col] = '<th class="no-select" '.$thsize.'><input class="input is-small" data-grid="search-column" data-column="'.$col.'" placeholder="pesquisar '.$title.'" type="text" /></th>';
					$search = true;
				}

				$cols[] = [
					'col' => $col,
					'tooltip' => $tooltip,
					'thclass' => $thclass,
					'search' => $search,
					'title' => str_replace(' ', '&nbsp;', $params['title'])
				];
			}
		}

		return $cols;
	}

	public function exists($id = 0){

		$name = $this->_getTableName();

		$primaryKey = $this->_getPrimaryKey();

		$sql = "SELECT * FROM $name WHERE $primaryKey = :id";
		$query = $this->_connection->prepare($sql);
		$query->bindParam(':id', $id);
		$query->execute();

		return $query->fetch(\PDO::FETCH_OBJ);
	}

	public function get(){

		$name = $this->_getTableName();

		$this->_sql = $this->_createSql();

		$query = $this->_connection->prepare($this->_sql);
		$query->execute();

		$this->_fetch = $query->fetchAll(\PDO::FETCH_OBJ);

		$errorInfo = $query->errorInfo();

		//new \de($this->_sql);
		//new \de($errorInfo);

		$query = null;

		if($errorInfo[2] != ''){

			$this->setErrorInfo($errorInfo);

			$this->_fetch = [];
		}

		// EDIT AND/OR REMOVE
		$this->_processEditRemove();

		// CLOSURE FROM RESTRICT
		if(isset($this->_config->restrict, $this->_config->restrict['closure']) and $this->_config->restrict['closure'] !== false){

			$fn = $this->_config->restrict['closure'];

			foreach($this->_fetch as $line => $array){

				$this->_fetch[$line] = $fn($array);
			}
		}

		$sqlCount = $this->_createSqlCount();

		$query = $this->_connection->prepare($sqlCount);
		$query->execute();

		$fetch = $query->fetch(\PDO::FETCH_OBJ);

		$errorInfo = $query->errorInfo();

		//new \de($this->_createSqlCount());

		$query = null;

		if($errorInfo[2] != ''){

			$this->setErrorInfo($errorInfo);

			$fetch = [];
		}

		if(isset($fetch, $fetch->total)){
			$this->_total = $fetch->total;
		}

		$this->_totalPages = floor($this->_total / $this->_byPage);

		if($this->_total % $this->_byPage > 0){
			$this->_totalPages = $this->_totalPages + 1;
		}

		if($this->_totalPages < 1){
			$this->_totalPages = 1;
		}

		foreach($this->_fetch as $key => $arr){

			foreach($arr as $col => $val){

				if(is_null($val)){
					$this->_fetch[$key]->$col = '';
				}

				// OPTIONS
				if(isset($this->_config->table[$name][$col], $this->_config->table[$name][$col]['options'], $this->_config->table[$name][$col]['options'][$val])){
					$this->_fetch[$key]->$col = $this->_config->table[$name][$col]['options'][$val];
				}
			}
		}

		foreach($this->_fetch as $key => $arr){

			// FORMATS
			foreach($arr as $col => $val){

				// AUTO GMT FROM $this->_config->table timestamp COLS
				if(!empty($val) and !empty($this->_gmt_time_string) and isset($this->_config->table[$name][$col], $this->_config->table[$name][$col]['type'])){

					if(preg_match('/timestamp/', $this->_config->table[$name][$col]['type'])){

						$val = date('Y-m-d H:i:s', strtotime($val.' '.$this->_gmt_time_string));
						$this->_fetch[$key]->$col = $val;
					}
				}
	
				// OPTIONS
				if(isset($this->_config->grid[$name][$col], $this->_config->grid[$name][$col]['format']) and is_array($this->_config->grid[$name][$col]['format'])){

					$params = $this->_config->grid[$name][$col]['format'];

					$format = key($params) ?? false;
					$param = $params[$format] ?? 0;

					if($format == 'tokm' and !empty($val)){

						$val = $val / 1000;

						$this->_fetch[$key]->$col = number_format($val, $param, ',', '.');
					}

					if($format == 'decimal' and !empty($val)){

						$this->_fetch[$key]->$col = number_format($val, $param, ',', '.');
					}

					if($format == 'date' and !empty($val)){

						$this->_fetch[$key]->$col = date($param, strtotime($val));
					}

					if($format == 'custom'){

						$fn = $param;

						$this->_fetch[$key]->$col = $fn($val);
					}
				}
			}
		}

		return $this->_fetch;
	}

	public function getPdf(){

		$name = $this->_getTableName();

		// REMOVE INVISIBLE
		$remove = [];
		foreach($this->_config->grid[$name] as $col => $param){

			if(isset($param['invisible']) and $param['invisible'] === true){
				$remove[$col] = $col;
			}
		}

		// REMOVE COLUMN EXTRA
		$grid = $this->_getGrid();
		foreach($grid as $param){

			if(isset($param['concat'], $param['concat']['columns'])){

				foreach($param['concat']['columns'] as $col){

					$remove[$col] = $col;
				}
			}
		}

		foreach($this->_fetch as $key => $lines){

			foreach($lines as $col => $null){

				if(array_key_exists($col, $remove)){
					unset($this->_fetch[$key]->$col);
				}
			}
		}

		$GridPdf = new GridPdf($this->_config->grid[$name], $this->_fetch, $this->_config->print);
	}

	public function getCsv(){

		$name = $this->_getTableName();

		// REMOVE INVISIBLE
		$remove = [];
		foreach($this->_config->grid[$name] as $col => $param){

			if(isset($param['invisible']) and $param['invisible'] === true){
				$remove[$col] = $col;
			}
		}

		// REMOVE COLUMN EXTRA
		$grid = $this->_getGrid();
		foreach($grid as $param){

			if(isset($param['concat'], $param['concat']['columns'])){

				foreach($param['concat']['columns'] as $col){

					$remove[$col] = $col;
				}
			}
		}

		$GridCsv = new GridCsv;
		$GridCsv->csv($this->_config->grid[$name], $this->_fetch, $this->_config->print);
	}

	public function setErrorInfo($errorInfo){

		$this->_errorInfo = array_merge($errorInfo, $this->_errorInfo);

		return $this;
	}

	public function getErrorInfo(){

		return $this->_errorInfo;
	}

	public function getArray(){

		return [
			'fetch' => $this->_fetch,
			'primary' => $this->_getPrimaryKey(),
			'total' => $this->_total,
			'totalPages' => $this->_totalPages,
			'_sql' => $this->_sql,
			'_errorInfo' => $this->getErrorInfo()
		];
	}

	public function getSql(){

		return $this->_sql;
	}

	public function getTitles(){

		return $this->_config->titles;
	}

	public function getControllers(){

		if(isset($this->_config->controllers) and !empty($this->_config->controllers)){
			return $this->_config->controllers;
		}

		return false;
	}

	protected function _processFilters(){

		$name = $this->_getTableName();

		if(isset($this->_config->filters)){

			foreach($this->_config->filters as $key => $filter){

				// TYPE LIST
				if(isset($filter['type']) and $filter['type'] == 'list'){

					// SOURCE BY QUERY
					if(isset($filter['source'], $filter['source']['query']) and !empty($filter['source']['query'])){

						$sql = $filter['source']['query'];

						// CT LANG PARAM
						$sql = str_replace(':ctLang', $this->_getCtLang(), $sql);
						$query = $this->_connection->prepare($sql);
						$query->execute();
						$fetch = $query->fetchAll(\PDO::FETCH_ASSOC);

						$errorInfo = $query->errorInfo();
						$query = null;

						if($errorInfo[2] != ''){

							$this->setErrorInfo($errorInfo);
						}

						if(is_array($fetch)){

							foreach($fetch as $k => $line){

								foreach($line as $col => $val){

									// FOR NULL COLUMN
									if(is_null($val)){
										$fetch[$k][$col] = '';
									}
								}
							}

							$this->_config->filters[$key]['list'] = $fetch;
						}
					}

					// SOURCE BY OPTIONS
					if(isset($filter['source'], $filter['source']['options']) and !empty($filter['source']['options'])){

						if(isset($this->_config->table[$name], $this->_config->table[$name][$filter['source']['options']], $this->_config->table[$name][$filter['source']['options']]['options'])){

							$options = $this->_config->table[$name][$filter['source']['options']]['options'];

							$this->_config->filters[$key]['list'] = [];

							foreach($options as $id => $val){

								$this->_config->filters[$key]['list'][] = [
									'id' => $id,
									'val' => $val
								];
							}
						}
					}
				}
			}
		}
	}

	public function getFilters($use_id = 0){

		if(isset($this->_config->filters)){

			$this->_processFilters();

			$load = $this->loadFilters($use_id, $this->_config->controllers['controller']);

			// FILTERS EXISTS IN SAVE
			if(is_array($load)){

				// SET ACTIVE/INACTIVE FILTERS FROM SAVE
				foreach($this->_config->filters as $filter => $null){

					unset($this->_config->filters[$filter]['source']['query']);

					if(isset($load[$filter])){

						$this->_config->filters[$filter]['active'] = true;

					}else{

						$this->_config->filters[$filter]['active'] = false;
					}
				}
			}

			// DON'T SHOW QUERY TO FRONT-END
			foreach($this->_config->filters as $filter => $null){

				if(isset($this->_config->filters[$filter]['source'])){
					unset($this->_config->filters[$filter]['source']);
				}
			}

			// HIDE SOME PARAMS TO FRONT-END
			$return = [];
			foreach($this->_config->filters as $filter => $params){

				unset($params['column']);

				$return[$filter] = $params;
			}

			return $return;
		}

		return [];
	}

	public function loadFilters($use_id, $controller){

		$query = $this->_connection->prepare(
			'SELECT
				ufi_parameters
			FROM sys_user_filter
			WHERE
				use_id = :use_id and
				ufi_controller = :ufi_controller');

		$query->bindParam(':use_id', $use_id);
		$query->bindParam(':ufi_controller', $controller);
		$query->execute();

		$errorInfo = $query->errorInfo();

		$fetch = $query->fetch(\PDO::FETCH_ASSOC);
		$query = null;

		if($errorInfo[0] != '00000'){

			Log::error($errorInfo);
		}

		if(is_array($fetch) and isset($fetch['ufi_parameters'])){

			$fetch = json_decode($fetch['ufi_parameters'], true);

			$load = [];
			foreach($fetch as $filter){
				$load[$filter] = $filter;
			}

			return $load;
		}

		return false;
	}

	public function saveFilters($use_id, $controller, $savefilters = ''){

		$params = explode(',', $savefilters);

		$ufi_parameters = [];
		if(is_array($params)){
			$ufi_parameters = $params;
		}

		$ufi_parameters = json_encode($ufi_parameters);

		$query = $this->_connection->prepare(
			'INSERT INTO sys_user_filter (
				use_id,
				ufi_controller,
				ufi_parameters
			) VALUES (
				:use_id,
				:ufi_controller,
				:ufi_parameters
			) ON CONFLICT (use_id, ufi_controller) DO UPDATE SET
				ufi_parameters = :ufi_parameters
		');

		$query->bindParam(':use_id', $use_id);
		$query->bindParam(':ufi_controller', $controller);
		$query->bindParam(':ufi_parameters', $ufi_parameters);
		$query->execute();

		$errorInfo = $query->errorInfo();

		if($errorInfo[0] != '00000'){

			Log::error($errorInfo);
		}

		return [
			'status' => true
		];
	}
}