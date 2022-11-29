<?php

namespace TheMoiza\Crud\Connection;

trait Transaction{

	protected $_commit = true;

	protected $_localCommit = true;

	protected $_sqlErrors = [];

	// ACTIVE COMMIT FROM EXTERNAL
	public function allowLocalCommit(){

		$this->_localCommit = true;
	}

	// INACTIVE COMMIT FROM EXTERNAL
	public function disableLocalCommit(){

		$this->_localCommit = false;
	}

	// START TRANSACTION
	public function startTransaction() :void{

		if($this->pdo->inTransaction() === false){
			$this->pdo->beginTransaction();
		}
	}

	// VERIFY IF COMMIT IS POSSIBLE AND MAKE IT
	public function makeCommit() :void{

		if($this->_localCommit === true and $this->_commit === true and $this->pdo->inTransaction() === true){

			$this->pdo->commit();

			$this->_commit = true;

			$this->_localCommit = true;

			$this->_sqlErrors = [];
		}
	}

	// VERIFY IF COMMIT IS POSSIBLE AND MAKE IT
	public function finalCommit() :void{

		if($this->_commit === true and $this->pdo->inTransaction() === true){

			$this->pdo->commit();

			$this->_commit = true;

			$this->_localCommit = true;

			$this->_sqlErrors = [];
		}
	}

	// VERIFY IF ROLLBACK IS POSSIBLE AND MAKE IT
	public function makeRollback() :void{

		if($this->pdo->inTransaction() === true){

			$this->pdo->rollBack();
		}
	}

	// BLOCK COMMIT AND ADD AN ERROR
	public function blockCommit(array $error) :object{

		$this->_commit = false;

		$this->addError($error);
		return $this;
	}

	public function addError(array $error) :object{

		$this->_sqlErrors = array_merge($this->_sqlErrors, $error);

		return $this;
	}

	public function getErrors() :array{

		return $this->_sqlErrors;
	}

	public function canCommit() :bool{

		return $this->_commit;
	}
}