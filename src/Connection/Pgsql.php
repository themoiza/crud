<?php

namespace TheMoiza\Crud\Connection;

use TheMoiza\Crud\Connection\Transaction;

use TheMoiza\Crud\Connection\InsertArray;

class Pgsql{

	use Transaction;

	private $_connection;

	public $pdo;

	public $query;

	public function __construct(){

		$this->_connection = new \stdClass;

		$this->query = new InsertArray;
	}

	public function connect(array $config, array $params = []) :object|array|null {

		try{

			$dsn = 'pgsql'.':host='.$config['DB_HOST'].';port='.$config['DB_PORT'].';dbname='.$config['DB_DATABASE'];

			$this->_connection->config = $config;
			$this->pdo = new \PDO($dsn, $config['DB_USERNAME'], $config['DB_PASSWORD'], $params);

			return $this;

		}catch(\PDOException $e){

			var_dump($e->getMessage());
			exit;
		}

		return null;
	}
}