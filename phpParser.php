
<?php

// $MYPATH : path to /vendor/autoload.php
$MYPATH = "/var/www/html";

require $MYPATH."/vendor/autoload.php";

////////////////////////////////////////////
// global functions with arrays
////////////////////////////////////////////

// arr[key] = {val}

function insert_key_if_not_exsist(&$arr, $key) {
    // insert an key into assosiative array if key not exsist
    if (!array_key_exists($key, $arr)) {
        $arr[$key] = [];
    }
}
function insert_val_if_not_exsist(&$arr, $val) {
    // insert value into array if not exsist
    if (!in_array($val, $arr, true)) {
        array_push($arr, $val);
    }
}
function get_first_key_of_array($arr) {
    reset($arr);
    return key($arr);
}

/////////////////////////////////////////////
// base class of parser
/////////////////////////////////////////////

class BaseParser{
    // data from phpmyadmin parser
    protected $statements;
    
    function __construct($parserStatements) {
//        echo 'BaseParser constructor called'.'<br>';
        $this->statements = $parserStatements;
    }
    
    // self defined tools
    protected function functionCutter($funStr) {
        $tokenArr = [];
        $left = strpos($funStr, '(');
        $right = strpos($funStr, ')');
        $parms = substr($funStr, $left+1, $right-$left-1);
        $tok = strtok($parms, " ,");
        while ($tok !== false) {
            $tokenArr[] = $tok;
            $tok = strtok(" ,");
        }
        return $tokenArr;
    }
    protected function notString($identifier, $expr) {
        $lastPos = strpos($expr, $identifier, 0);
        $len = strlen($identifier);
        $posArr = array();

        while ($lastPos !== false) {
            $posArr[] = $lastPos;
            $lastPos = strpos($expr, $identifier, $lastPos+$len);
        }

        foreach ($posArr as $pos) {
            if($pos === 0 || ($expr[$pos-1] !== '"' && $expr[$pos-1] !== "'")) {
                return true;
            }
        }

        return false;
    }
}

/////////////////////////////////////////////
// Parser: Insert
/////////////////////////////////////////////

class InsertParser extends BaseParser {
    private $selected;

    function __construct($parserStatements) {
        parent::__construct($parserStatements);
        $this->inserted= [];
        $this->run();
    }

    private function intoTable() {
        $tableName = $this->statements->into->dest->table;
        insert_key_if_not_exsist($this->inserted, $tableName);
    }

    private function intoColumn() {
        $tableName = $this->statements->into->dest->table;
        $columns = $this->statements->into->columns;
        foreach ($columns as $colName) {
            insert_val_if_not_exsist($this->inserted[$tableName], $colName);
        }
    }

    // run all
    private function run() {
        $this->intoTable();
        $this->intoColumn();
    }
    // public apis
    function showTables() { return array_keys($this->inserted); }
    function showColumns() { return $this->inserted; }
}

/////////////////////////////////////////////
// Parser: Select
/////////////////////////////////////////////

// selected[ table ] = { columns }

class SelectParser extends BaseParser {
    private $hasStar;
    private $selected;
    
    function __construct($parserStatements) {
        parent::__construct($parserStatements);
//        echo 'SelectParser constructor called'.'<br>';
        $this->hasStar = false;
        $this->selected = [];
        $this->run();
    }
    
    // get data from each part
    private function selectTable() {
        foreach ($this->statements->from as $from) {
            $tableName = $from->table;
            insert_key_if_not_exsist($this->selected, $tableName);
             
        }
    }
    private function joinTable() {
    	if(is_null($this->statements->join)) { return; }
    	foreach ($this->statements->join as $join) {
                // join -> expr -> table
    		$tableName = $join->expr->table;
    		insert_key_if_not_exsist($this->selected, $tableName);

                // join -> on
                $on = $join->on;
                foreach ($on as $expr) {
                    if ($expr->isOperator == true) { continue; }
                    //A.a=B.b
                    $pairArr = [];
                    $tok = strtok($expr, " =");
                    while ($tok !== false) {
                        $pairArr[] = $tok;
                        $tok = strtok(" =");
                    }
                    foreach ($pairArr as $pair) {
                        $tableName = strtok($pair, ".");
                        $colName = strtok(".");
                        insert_key_if_not_exsist($this->selected, $tableName);
                        insert_val_if_not_exsist($this->selected[$tableName], $colName);
                    }
                }
    	}
    }
    private function selectColumn() {
        if (is_null($this->statements->expr)) { return; }
        $firstKey = get_first_key_of_array($this->selected);
        foreach ($this->statements->expr as $expr) {
        	$table = ($expr->table !== NULL)? $expr->table : $firstKey;
            if ($expr->column !== NULL) {
                insert_val_if_not_exsist($this->selected[$table], $expr->column);
            } elseif ($expr->function !== NULL) {
                $arrayOfColumns = $this->functionCutter($expr->expr);
                foreach ($arrayOfColumns as $col) {
                    insert_val_if_not_exsist($this->selected[$table], $col);
                }
            } elseif ($expr->expr === '*') {
                $this->hasStar = true;
            } else {
                throw new Exception('Parse Error in SelectParser::selectColumn()');
            }
        }
    }
    private function whereColumn() {
        if (is_null($this->statements->where)) { return; }
        $firstKey = get_first_key_of_array($this->selected);
        foreach ($this->statements->where as $where) {
            foreach ($where->identifiers as $col) {
                if ($this->notString($col, $where->expr)) {
                    insert_val_if_not_exsist($this->selected[$firstKey], $col);
                }
            }
        }
    }
    private function groupColumn() {
        if (is_null($this->statements->group)) { return; }
        $firstKey = get_first_key_of_array($this->selected);
        foreach ($this->statements->group as $col) {
            insert_val_if_not_exsist($this->selected[$firstKey], $col);
        }
    }
    // run all
    private function run() {
    	$this->joinTable();
        $this->selectTable();
        $this->selectColumn();
        $this->whereColumn();
        $this->groupColumn();
        if ($this->hasStar === true) {
            $firstKey = get_first_key_of_array($this->selected);
            $this->selected[$firstKey] = true;
        }
    }
    // public apis
    function showTables() { return array_keys($this->selected); }
    function showColumns() { return $this->selected; }
}

/////////////////////////////////////////////
// main class
/////////////////////////////////////////////

class SymbolTableBuilder{
    // input sql query
    private $sqlQuery;
    // stored parse results
    private $queryType;
    private $selectedTables;
    private $selectedColumns;
    // by phpmyadmin sql parser
    private $parserTokenList;
    private $parserStatements;

    function __construct($inputQuery) {
//        echo 'SymbolTableBuilder constructor called'.'<br>';
        $this->sqlQuery = $inputQuery;
        
        $phpmyadminParser = new PhpMyAdmin\SqlParser\Parser($inputQuery);
        $phpmyadminParser->parse();
        $this->parserTokenList = $phpmyadminParser->list->tokens;
        $this->parserStatements = $phpmyadminParser->statements[0];
        
        $this->queryType = $this->getQueryType();
        
        if ($this->queryType === 'SELECT') {
            $parser = new SelectParser($this->parserStatements);
            $this->selectedTables = $parser->showTables();
            $this->selectedColumns = $parser->showColumns();
        } else if ($this->queryType == 'INSERT') {
            $parser = new InsertParser($this->parserStatements);
            $this->selectedTables = $parser->showTables();
            $this->selectedColumns = $parser->showColumns();
        } else {
            // throw new Exception($this->queryType . ' not implemented yet or invalid');
            echo ($this->queryType . ' not implemented yet or invalid');
        }
        
    }    

    // get information from phpmyadmin parser data
    private function getQueryType() {
        return $this->parserTokenList[0]->keyword;
    }
    // for debug
    function showQuery() { return $this->sqlQuery; }
    function showTokenList() { return $this->parserTokenList; }
    function showStatements() { return $this->parserStatements; }
    // public apis
    function showQueryType() { return $this->queryType; }
    function showTables() { return $this->selectedTables; }
    function showColumns($tableName) { return $this->selectedColumns[$tableName]; }
}

