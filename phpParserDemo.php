
<?php
require("phpParser.php");

$testSQL = array(

// simple select
 "Select A, B, C from myTable where D = 7122 and E = 9487 and F=G;"

/*
// select distinct
 "SELECT DISTINCT Country FROM Customers;",
// select with star
 "Select * from myTable where D = 7122 and E = 9487 and F=G;",
// select where string exist in condition
 "Select A, B, C from myTable where D = 7122 and E = 9487 and F=G or H='str2';",
// select with 'is'
 "SELECT * FROM Customers WHERE Country='Mexico' or City is 'City';",
// dealing with function
 "Select Avg(C) from mytable;",

// [Not Yet] JOIN
"SELECT Orders.OrderID, Customers.CustomerName, Orders.OrderDate FROM Orders INNER JOIN Customers ON Orders.CustomerID=Customers.CustomerID;",

// [Not Yet] JOIN
"SELECT Orders.OrderID, Customers.CustomerName FROM Orders INNER JOIN Customers ON Orders.CustomerID = Customers.CustomerID and Orders.CustomerID = Customers.CustomerName;"

// [FAILED] Mulitple query
// $sqlQuery = "SELECT * FROM Customers WHERE Country IN (SELECT Country FROM Suppliers);";

// [Not Yet] Simple Insert
 "INSERT INTO Customers (CustomerName, City, Country) VALUES ('Cardinal', 'Stavanger', 'Norway');"

// [Not Yet] Simple Update
// $sqlQuery = "UPDATE Customers SET ContactName = 'Alfred Schmidt', City= 'Frankfurt' WHERE CustomerID = 1";

// [Not Yet] simple delete
// $sqlQuery = "DELETE FROM Customers WHERE CustomerName='Alfreds Futterkiste'";

*/
);
$showParserStruct = $_GET['debug'];
?>

<head>
    <title>PHP Parser Demo</title>
</head>
<body>
    <h1>Demo of php parser</h1>
    <p> add sql query to <code>testSQL</code> array in this php file<p>
    
<?php
foreach ($testSQL as $sqlQuery) {
    try {
        $builder = new SymbolTableBuilder($sqlQuery);
        //apis
        $query = $builder->showQuery();
        $type = $builder->showQueryType();
        $usedTable = $builder->showTables();
        // print sqlQuery
        echo '<h2>Query</h2>';
        echo '<pre>' . $query . '</pre>';
        // print query type
        echo '<h2>Query Type</h2>';
        echo '<pre>' . $type . '</pre>';
        // print used table
        echo '<h2>Used Table</h2>';
        echo '<ul>';
        foreach ($usedTable as $tab) {
            echo '<li>' . $tab . '</li>';
        }
        echo '</ul>';
        // print used columns
        echo '<h2>Used Columns</h2>';
        echo '<ul>';
        foreach ($usedTable as $tab) {
            echo '<li>' . $tab . '</li>';
            echo '<ul>'; 
            $usedColumn = $builder->showColumns($tab);
            if ($usedColumn === true) { echo '<li>All columns selected</li>'; }
            else {
                foreach ($usedColumn as $col) {
                    echo '<li>' . $col . '</li>';
                }
            }
            echo '</ul>';
        }
        echo '</ul>';
        // print debug messages
        if ($showParserStruct === "true") {
            // parser structure
            echo '<h2>SymbolTableBuilder</h2>';
            echo '<pre>';
            var_dump($builder);
            echo '</pre>';
            // token list
            echo '<h2>Token List by phpmyadmin</h2>';
            echo '<pre>';
            var_dump($builder->showTokenList());
            echo '</pre>';
            // statements
            echo '<h2>Statements by phpmyadimin</h2>';
            echo '<pre>';
            var_dump($builder->showStatements());
            echo '</pre>';
        }
    } catch (Exception $err) {
        echo '<pre>Exception Caught: ' . $err->getMessage() . '</pre>';
    } finally {
        echo '<br><hr><br>';
    }
}
?>
</body>
