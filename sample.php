<html>
<head>
<title>Functional Dependency Library Testing</title>
<meta name="viewport", content="width=device-width, initial-scale=1">
</head>
<body>
<h3>This just randomly generates a batch of functional dependencies and figures out all the things, so reload and see a new batch!</h3>
<?php
error_reporting(-1);
ini_set('display_errors', 'On');
require('lib.php');

function f($s) {
    return AttributeSet::from($s);
}

function d($a, $b) {
    return [f($a), f($b)];
}

$rel = Relation::random();
// $rel = new Relation(f('ABCD'), [d('BC', 'D')]);
$rel->debug();
?>
</body>
</html>
