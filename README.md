# functional-dependency-generator
Generates & auto-grades functional dependency problems.

## mostly, the pseudocode for lib.php
```php
// Since array_rand($arr, 1) returns a single value,
// we wrap it in ensure_array to always get an array.
function ensure_array($x) {
// This class encapsulates a set of attributes (ex. ABCD)
// and adds some behavior.
// I have not tested with attributes that aren't single-character strings,
// but theoretically that should also work.
class AttributeSet {
    // Contents are public so iteration is easier
    // Construct by passing in the contents directly
    function __construct($contents) {
    // When coercing to a string, just give the contents as a string
    function __toString() {
    // Construct from a range of letters
    static function range($start, $end) {
    // Construct from a string (ex. 'ACE')
    static function from($str) {
    // Render a given attribute. I don't think I ever use this, plus it's not useful.
    function renderAttr($i) {
    // Render the attribute set as a tuple (A, B, C).
    function renderTuple() {
    // Render the attribute set as a set {A, B, C}.
    function renderSet() {
    // Render the attribute set as a list ABC.
    function renderList() {
    // Check if this set contains all the attributes in another set.
    function containsAll($other) {
    // Add all the attributes from another set into this set.
    function addAll($other) {
    // Check if this set equals another set.
    function equals($other) {
    // Get a subset by number: 0b1101 means include attributes 1, 2, and 4
    function getSubset($n) {
        $arr = array_filter($arr, function($v) {
    // Get a random subset that isn't the empty set (but can be the entire set)
    function randSubset() {
    // Get all of the subsets, including the empty set and the entire set
    function allSubsets() {
        $arr = array_map(function($n) {return $this->getSubset($n);}, $arr);
// Checks if two sets of closures are equal.
// Assumes they are in the same order.
function closuresEqual($one, $two) {
    return array_reduce($pairs, function ($wasGood, $pair) {
// Encapsulates a relation, including attributes and dependencies.
class Relation {
    // $attrs is an AttributeSet
    // $deps is an array of arrays of AttrSets (AB->C, C->A is [[AB, C], [C, A]])
    // Build the relation by passing in attributes and dependencies
    // Deps are good iff they only contain the attributes included in the relation
    function __construct($attrs, $deps, $depsGood = true) {
        // If the deps aren't known to be good, clean them up
            // For each dependency...
                // Grab the left-hand and right-hand sides
                // If we're missing things from the left...
                    // Erase the dependency entirely and move on
                // Filter the RHS down to things we care about
                // If that was nothing...
                    // Skip the dependency
                    // Otherwise, use that RHS instead
        // Since we may have used unset() on $deps, rekey it
    // Make a random Relation
    static function random() {
            // This winds up generating a lot of trivial dependencies
    // Render an individual dependency, optionally in TeX
    function renderDep($dep, $tex = false) {
    // Render all the dependencies, optionally in TeX
    function renderDeps($tex = false) {
    // Render the entire relation
    function render() {
    // Find the closure of a given set of attributes
    function closure($attrs) {
        // Start with the given attributes
        // While you just added new things to the closure...
            // For every dependency in the relation...
                // If you satisfy the LHS...
                    // If you don't already have the entire RHS...
                        // Add the RHS to the closure
    // Get the closure of every possible set of attributes
    // (which is equivalent to the closure of the dependencies)
    function allClosures() {
    // Get the list of superkeys of the relation
    function superkeys() {
        // Get all the closures
        // Find the ones that contain all the attributes in the relation
        $superkeys = array_filter($allClosures, function ($c) {
        return array_map(function ($x) {return AttributeSet::from($x);}, $superkeys);
    // Get the list of candidate keys of the relation
    function candidateKeys() {
        // Get the superkeys
        // To check if some superkey is a candidate key...
        $isCandidate = function($key) use ($superkeys) {
            // Look at all the other superkeys
                // If this superkey is a proper superset of the other superkey...
                    // ...this can't be a candidate key
            // If it isn't a superset of a superkey, no superkey is a subset of it, so it's a candidate key
    // Get the canonical cover of the dependencies in the relation (F)
    function canonicalCover($verbose = true) {
        // Make a copy of F to work on
        // Grab the original F+ (by grabbing the closure of every possible set of attributes)
        // Don't loop infinitely (this doesn't still happen but it's good to be safe)
        // While we just changed something and haven't been running for too long...
            // Map LHS => index of first dependency with given LHS
            // For all dependencies...
                // If another dependency exists with the same LHS...
                    // Merge this RHS into the other's RHS
                    // Remove this dependency
                // If LHS or RHS is entirely empty...
                    // Remove this dependency
                    // Since indices shifted, quit removing things
                    // If not removing, save index in map
            // For every dependency in the relation...
                // For every side of that dependency...
                    // For every attribute on that side of that dependency...
                        // Try removing that attribute from F
                        // Find F+ without the attribute
                        // If we didn't create or remove any information...
                            // Remove that attribute
                            // PHP magic: break out of all three loops at once
                            // some languages have labeled breaks, some wouldn't let you do this at all
                            // this is probably less intuitive than a labeled break
        // Erase any leftover dependencies with empty sides
        $deps = array_filter($deps, function ($dep) {
    // Check if this relation is in BCNF
    function isBCNF($verbose = false) {
        // Grab the superkeys
        // For each dependency...
            // If it's trivial...
                // we're good
                // If the LHS is a superkey, we're good
                // If neither of those holds, this is not BCNF
    // Check if this relation is in 3NF
    function is3NF($verbose = false) {
        // Grab the superkeys
        // Grab the candidate keys
        // Union all the candidate keys together
        // For each dependency...
            // If it's trivial...
                // we're good
                // If LHS is a superkey, we're good
                // Attrs in RHS can be in a candidate key or in LHS as well
                // If some attr in RHS is neither...
                    // this is not 3NF
    // Check if this relation is in 4NF
    function is4NF($verbose = false) {
        // Check if it's in BCNF first
        // Look for a simple key
    // Check if this relation is in 5NF
    function is5NF($verbose = false) {
        // Check if it's in 3NF first
        // Check that all candidate keys are simple
    // Make [R-$beta, $alpha$beta] from this relation R and an $alpha and $beta, given all the closures
    function fracture($alpha, $beta, $closures) {
        // make a new Relation with only those attributes
        // find out the dependencies that matter
        // make a new Relation without beta
        // find its dependencies too
        // give both back
    // Decompose this relation into BCNF
    // Algorithm from Silberschatz, Korth, Sudarshan "Database System Concepts" 6th ed. fig. 8.11
    function decomposeBCNF($verbose = false) {
        // Start with only R
        // Find F+
        // Until we're done...
            // For each relation in the result...
                // If it's not in BCNF...
                    // Find some a->b where a is not a superkey of ri and a and b share nothing
                    // We want only alphas that determine a nontrivial beta
                    $riAlphas = array_filter($riAlphas, function ($alpha) use ($ri) {
                    // Break up $ri into [$ri-$beta, $alpha$beta]
                    // Replace $ri with those fragments in the result
                    // Don't keep looking through the result
    // Decompose this relation into 3NF
    // Algorithm from Silberschatz, Korth, Sudarshan "Database System Concepts" 6th ed. fig. 8.12
    function decompose3NF($verbose = false) {
        // Grab the canonical cover
        // For each dependency in the canonical cover...
            // Find all the attributes involved
            // Add a new relation with only those attributes
        // Grab the candidate keys
        // For each relation we already have...
            // For each candidate key...
                // If this relation contains this candidate key...
                    // We found a candidate key!
                    // Don't look for any others
        // If we never found a candidate key...
            // Throw one in
        // For every relation in the result...
            // For every other relation in the result...
                // If they aren't the same but this one is a subset of that one...
                    // Remove this one
    // Check if a decomposition of this relation is dependency preserving
    function isDepPres($decomp, $verbose = false) {
        // For each dependency...
            // Build up the closure within the decomposition
                // For each relation in the composition...
                    // Merge in the closure of what we already have
            // If the closure in the decomposition doesn't contain the RHS of the dependency...
                // The decomposition can't be dependency preserving
    // Check if a decomposition of this relation is lossless
    function isLossless($decomp) {
        // If there's only one relation in the decomposition...
            // It's lossless iff it has all the same attributes
            // If it's a binary decomposition...
            // Find the attributes both relations have in common
            // Find attribute sets that are superkeys of either relation
            // It's lossless iff the common attributes are a superkey of either relation
            // If it's more than a binary decomposition...
            // For every pair of decompositions...
                    // Make a copy of the decomposition
                    // Extract the pair
                    // Merge the pair
                    // If the pair can be combined losslessly...
                        // Replace the split pair with the merged pair
                        // Promise we got rid of a relation and aren't recursing infinitely
                        // If merging that pair resulted in a lossless decomposition...
                            // The original decomposition was also lossless
    // Print out a whole bunch of stuff
    function debug() {
        usort($subsets, function ($a, $b) { return strlen($a) - strlen($b); });
```
