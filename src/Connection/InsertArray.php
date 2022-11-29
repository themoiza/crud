<?php

namespace TheMoiza\Crud\Connection;

class InsertArray{

	public function insertArrayPrepare(object $conn, string $schema, string $table, array $arrData) :bool|array{

		$sql = '';

		$conn->pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);

		$conn->startTransaction();

		foreach($arrData as $arr){

			if(count($arr) > 0){

				try{

					// PROCESS DATA
					foreach($arr as $col => &$val){

						// BOOL CASE, CONVERT true => 't' and false => 'f'
						if(is_bool($val)){

							if($val){
								$val = 't';
							}

							if(!$val){
								$val = 'f';
							}
						}
					}

					$cols = array_keys($arr);

					$params = trim(str_repeat('?,', count($cols)), ',');

					$cols = implode(', ', array_keys($arr));

					$createSQL = "INSERT INTO $schema.$table ($cols) VALUES ($params)";

					// PERFORMANCE OPTIMIZATION
					if($sql != $createSQL){

						$sql = $createSQL;
						$query = $conn->pdo->prepare($sql);
					}

					$query->execute(array_values($arr));

				} catch (\PDOException $e){

					$conn->blockCommit([]);

					$conn->makeRollback();

					return [
						'error' => $e->getMessage(),
						'data' => $arr
					];
				}
			}
		}

		$conn->makeCommit();
		return true;
	}

	public function insertArrayStatement(object $conn, string $schema, string $table, array $arrData) :bool|array{

		$sql = [];

		$conn->pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES, true);

		foreach($arrData as $arr){

			if(count($arr) > 0){

				// PROCESS DATA
				foreach($arr as $col => &$val){

					// BOOL CASE, CONVERT true => 't' and false => 'f'
					if(is_bool($val)){

						if($val){
							$val = 't';
						}

						if(!$val){
							$val = 'f';
						}
					}
				}

				$cols = implode(', ', array_keys($arr));

				$params = implode("', '", array_values($arr));

				$sql[] = "INSERT INTO $schema.$table ($cols) VALUES ('$params');";
			}
		}

		if(count($sql) > 0){

			$conn->startTransaction();

			try{

				$sql = implode('', $sql);
				
				$query = $conn->pdo->prepare($sql);
				$query->execute();

			} catch (\PDOException $e){

				$conn->blockCommit([]);

				$conn->makeRollback();

				return [
					'error' => $e->getMessage(),
					'data' => $arr
				];
			}

			$conn->makeCommit();
		}
		return true;
	}
}