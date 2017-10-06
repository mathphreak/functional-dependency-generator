<?php
// Since array_rand($arr, 1) returns a single value,
// we wrap it in ensure_array to always get an array.
function ensure_array($x) {
    if (is_array($x)) {
        return $x;
    }
    return [$x];
}

// This class encapsulates a set of attributes (ex. ABCD)
// and adds some behavior.
// I have not tested with attributes that aren't single-character strings,
// but theoretically that should also work.
class AttributeSet {
    // Contents are public so iteration is easier
    public $contents;

    // Construct by passing in the contents directly
    function __construct($contents) {
        $this->contents = array_values($contents);
    }

    // When coercing to a string, just give the contents as a string
    function __toString() {
        return implode('', $this->contents);
    }

    // Construct from a range of letters
    static function range($start, $end) {
        return new AttributeSet(range($start, $end));
    }

    // Construct from a string (ex. 'ACE')
    static function from($str) {
        return new AttributeSet(str_split($str));
    }

    // Render a given attribute. I don't think I ever use this, plus it's not useful.
    function renderAttr($i) {
        echo $this->contents[$i];
    }

    // Render the attribute set as a tuple (A, B, C).
    function renderTuple() {
        echo '(';
        for ($i = 0; $i < count($this->contents); $i++) {
            $this->renderAttr($i);
            if ($i < count($this->contents) - 1) {
                echo ', ';
            }
        }
        echo ')';
    }

    // Render the attribute set as a set {A, B, C}.
    function renderSet() {
        echo '{';
        for ($i = 0; $i < count($this->contents); $i++) {
            $this->renderAttr($i);
            if ($i < count($this->contents) - 1) {
                echo ', ';
            }
        }
        echo '}';
    }

    // Render the attribute set as a list ABC.
    function renderList() {
        for ($i = 0; $i < count($this->contents); $i++) {
            $this->renderAttr($i);
        }
    }

    // Check if this set contains all the attributes in another set.
    function containsAll($other) {
        return count(array_diff($other->contents, $this->contents)) == 0;
    }

    // Add all the attributes from another set into this set.
    function addAll($other) {
        $this->contents = array_unique(array_merge($this->contents, $other->contents));
        sort($this->contents);
    }

    // Check if this set equals another set.
    function equals($other) {
        return $this->containsAll($other) && $other->containsAll($this);
    }

    // Get a subset by number: 0b1101 means include attributes 1, 2, and 4
    function getSubset($n) {
        $bin = str_pad(base_convert($n, 10, 2), count($this->contents), '0', STR_PAD_LEFT);
        $include = str_split($bin);
        $arr = array_combine($this->contents, $include);
        $arr = array_filter($arr, function($v) {
            return $v == '1';
        });
        $arr = array_keys($arr);
        return new AttributeSet($arr);
    }

    // Get a random subset that isn't the empty set (but can be the entire set)
    function randSubset() {
        $n = rand(1, pow(2, count($this->contents)) - 1);
        return $this->getSubset($n);
    }

    // Get all of the subsets, including the empty set and the entire set
    function allSubsets() {
        $arr = range(0, pow(2, count($this->contents)) - 1);
        $arr = array_map(function($n) {return $this->getSubset($n);}, $arr);
        return $arr;
    }
}

// Checks if two sets of closures are equal.
// Assumes they are in the same order.
function closuresEqual($one, $two) {
    $pairs = array_map(null, $one, $two);
    return array_reduce($pairs, function ($wasGood, $pair) {
        return $wasGood && $pair[0]->equals($pair[1]);
    }, true);
}

// Encapsulates a relation, including attributes and dependencies.
class Relation {
    // $attrs is an AttributeSet
    public $attrs;
    // $deps is an array of arrays of AttrSets (AB->C, C->A is [[AB, C], [C, A]])
    public $deps;

    // Build the relation by passing in attributes and dependencies
    // Deps are good iff they only contain the attributes included in the relation
    function __construct($attrs, $deps, $depsGood = true) {
        // If the deps aren't known to be good, clean them up
        if (!$depsGood) {
            // For each dependency...
            foreach (array_keys($deps) as $i) {
                // Grab the left-hand and right-hand sides
                list($lhs, $rhs) = $deps[$i];
                // If we're missing things from the left...
                if (!$attrs->containsAll($lhs)) {
                    // Erase the dependency entirely and move on
                    unset($deps[$i]);
                    continue;
                }
                // Filter the RHS down to things we care about
                $rhs = new AttributeSet(array_intersect($rhs->contents, $attrs->contents));
                // If that was nothing...
                if (count($rhs->contents) == 0) {
                    // Skip the dependency
                    unset($deps[$i]);
                    continue;
                } else {
                    // Otherwise, use that RHS instead
                    $deps[$i][1] = $rhs;
                }
            }
        }
        $this->attrs = $attrs;
        // Since we may have used unset() on $deps, rekey it
        $this->deps = array_values($deps);
    }

    // Make a random Relation
    static function random() {
        $attrs = AttributeSet::range('A', chr(ord('A') + rand(3, 5)));
        $deps = [];
        $minDeps = count($attrs->contents) / 2;
        $maxDeps = count($attrs->contents);
        $depCount = rand((int)$minDeps, (int)$maxDeps);
        for ($i = 0; $i < $depCount; $i++) {
            // This winds up generating a lot of trivial dependencies
            $deps[] = [$attrs->randSubset(), $attrs->randSubset()];
        }
        return new Relation($attrs, $deps);
    }

    // Render an individual dependency, optionally in TeX
    function renderDep($dep, $tex = false) {
        $dep[0]->renderList();
        if ($tex) {
            echo '\\rightarrow ';
        } else {
            echo '&rarr;';
        }
        $dep[1]->renderList();
    }

    // Render all the dependencies, optionally in TeX
    function renderDeps($tex = false) {
        if ($tex) {
            echo '\\';
        }
        echo '{';
        for ($i = 0; $i < count($this->deps); $i++) {
            $this->renderDep($this->deps[$i], $tex);
            if ($i < count($this->deps) - 1) {
                echo ', ';
            }
        }
        if ($tex) {
            echo '\\';
        }
        echo '}';
    }

    // Render the entire relation
    function render() {
        $this->attrs->renderTuple();
        echo ' ';
        $this->renderDeps();
    }

    // Find the closure of a given set of attributes
    function closure($attrs) {
        // Start with the given attributes
        $closure = clone $attrs;
        $found = true;
        // While you just added new things to the closure...
        while ($found) {
            $found = false;
            // For every dependency in the relation...
            foreach ($this->deps as $dep) {
                $lhs = $dep[0];
                $rhs = $dep[1];
                // If you satisfy the LHS...
                if ($closure->containsAll($lhs)) {
                    // If you don't already have the entire RHS...
                    if (!$closure->containsAll($rhs)) {
                        // Add the RHS to the closure
                        $found = true;
                        $closure->addAll($rhs);
                    }
                }
            }
        }
        return $closure;
    }

    // Get the closure of every possible set of attributes
    // (which is equivalent to the closure of the dependencies)
    function allClosures() {
        $subsets = $this->attrs->allSubsets();
        $result = [];
        foreach ($subsets as $subset) {
            $result[implode('', $subset->contents)] = $this->closure($subset);
        }
        return $result;
    }

    // Get the list of superkeys of the relation
    function superkeys() {
        // Get all the closures
        $allClosures = $this->allClosures();
        // Find the ones that contain all the attributes in the relation
        $superkeys = array_filter($allClosures, function ($c) {
            return $c->containsAll($this->attrs);
        });
        $superkeys = array_keys($superkeys);
        sort($superkeys);
        return array_map(function ($x) {return AttributeSet::from($x);}, $superkeys);
    }

    // Get the list of candidate keys of the relation
    function candidateKeys() {
        // Get the superkeys
        $superkeys = $this->superkeys();
        // To check if some superkey is a candidate key...
        $isCandidate = function($key) use ($superkeys) {
            // Look at all the other superkeys
            foreach ($superkeys as $sk) {
                // If this superkey is a proper superset of the other superkey...
                if ($key->containsAll($sk) && !$sk->containsAll($key)) {
                    // ...this can't be a candidate key
                    return false;
                }
            }
            // If it isn't a superset of a superkey, no superkey is a subset of it, so it's a candidate key
            return true;
        };
        $candKeys = array_filter($superkeys, $isCandidate);
        return array_values($candKeys);
    }

    // Get the canonical cover of the dependencies in the relation (F)
    function canonicalCover($verbose = true) {
        // Make a copy of F to work on
        $deps = array_values($this->deps);
        $shrunk = true;
        // Grab the original F+ (by grabbing the closure of every possible set of attributes)
        $closures = $this->allClosures();
        // Don't loop infinitely (this doesn't still happen but it's good to be safe)
        $iters = 0;
        // While we just changed something and haven't been running for too long...
        while ($shrunk && $iters < 1000) {
            if ($verbose) {
                echo '\(F_c\approx';
                (new Relation($this->attrs, $deps))->renderDeps(true);
                echo '\)<br>';
            }
            $shrunk = false;
            // Map LHS => index of first dependency with given LHS
            $firstDeps = [];
            // For all dependencies...
            for ($i = 0; $i < count($deps); $i++) {
                $dep = $deps[$i];
                $lhs = '' . $dep[0];
                $removing = false;
                // If another dependency exists with the same LHS...
                if (isset($firstDeps[$lhs])) {
                    // Merge this RHS into the other's RHS
                    $deps[$firstDeps[$lhs]][1] = clone $deps[$firstDeps[$lhs]][1];
                    $deps[$firstDeps[$lhs]][1]->addAll($dep[1]);
                    // Remove this dependency
                    $removing = true;
                }
                // If LHS or RHS is entirely empty...
                if (count($dep[0]->contents) == 0 || count($dep[1]->contents) == 0) {
                    // Remove this dependency
                    $removing = true;
                }
                if ($removing) {
                    $shrunk = true;
                    if ($verbose) {
                        echo 'Removed \(' . $dep[0] . '\rightarrow ' . $dep[1] . '\) entirely.<br>';
                    }
                    unset($deps[$i]);
                    $deps = array_values($deps);
                    // Since indices shifted, quit removing things
                    break;
                } else {
                    // If not removing, save index in map
                    $firstDeps[$lhs] = $i;
                }
            }
            // For every dependency in the relation...
            for ($i = 0; $i < count($deps); $i++) {
                // For every side of that dependency...
                for ($j = 0; $j < count($deps[$i]); $j++) {
                    // For every attribute on that side of that dependency...
                    for ($k = 0; $k < count($deps[$i][$j]->contents); $k++) {
                        $iters++;
                        // Try removing that attribute from F
                        $newDeps = $deps;
                        $newDeps[$i][$j] = clone $newDeps[$i][$j];
                        $removed = $newDeps[$i][$j]->contents[$k];
                        unset($newDeps[$i][$j]->contents[$k]);
                        $newDeps[$i][$j]->contents = array_values($newDeps[$i][$j]->contents);
                        // Find F+ without the attribute
                        $newThis = new Relation($this->attrs, $newDeps);
                        $newClosures = $newThis->allClosures();
                        // If we didn't create or remove any information...
                        if (closuresEqual($closures, $newClosures)) {
                            if ($verbose) {
                                echo 'Removed \(' . $removed;
                                echo '\) from \(' . $deps[$i][0] . '\rightarrow ' . $deps[$i][1];
                                echo '\) to get \(' . $newDeps[$i][0] . '\rightarrow ' . $newDeps[$i][1] . '\)<br>';
                            }
                            // Remove that attribute
                            $deps = $newDeps;
                            $shrunk = true;
                            // PHP magic: break out of all three loops at once
                            // some languages have labeled breaks, some wouldn't let you do this at all
                            // this is probably less intuitive than a labeled break
                            break 3;
                        }
                    }
                }
            }
        }
        if ($iters >= 1000) {
            echo 'WARNING: Bailed out of canonical cover finding early.';
            if ($verbose) {
                echo '<pre>';
                var_dump($deps);
                echo '</pre>';
            }
        }
        // Erase any leftover dependencies with empty sides
        $deps = array_filter($deps, function ($dep) {
            return count($dep[0]->contents) > 0 && count($dep[1]->contents) > 0;
        });
        return array_values($deps);
    }

    // Check if this relation is in BCNF
    function isBCNF($verbose = false) {
        // Grab the superkeys
        $keys = $this->superkeys();
        // For each dependency...
        foreach ($this->deps as $dep) {
            list($lhs, $rhs) = $dep;
            // If it's trivial...
            if ($lhs->containsAll($rhs)) {
                // we're good
                continue;
            } else if (in_array($lhs, $keys)) {
                // If the LHS is a superkey, we're good
                continue;
            } else {
                // If neither of those holds, this is not BCNF
                if ($verbose) {
                    echo '\(';
                    $this->renderDep($dep, true);
                    echo '\) is bad so ';
                }
                return false;
            }
        }
        return true;
    }

    // Check if this relation is in 3NF
    function is3NF($verbose = false) {
        // Grab the superkeys
        $superkeys = $this->superkeys();
        // Grab the candidate keys
        $candKeys = $this->candidateKeys();
        // Union all the candidate keys together
        $acceptableAttrs = clone $candKeys[0];
        foreach ($candKeys as $ck) {
            $acceptableAttrs->addAll($ck);
        }
        // For each dependency...
        foreach ($this->deps as $dep) {
            list($lhs, $rhs) = $dep;
            // If it's trivial...
            if ($lhs->containsAll($rhs)) {
                // we're good
                continue;
            } else if (in_array($lhs, $superkeys)) {
                // If LHS is a superkey, we're good
                continue;
            } else {
                // Attrs in RHS can be in a candidate key or in LHS as well
                $goodRHS = clone $acceptableAttrs;
                $goodRHS->addAll($lhs);
                // If some attr in RHS is neither...
                if (!$goodRHS->containsAll($rhs)) {
                    // this is not 3NF
                    if ($verbose) {
                        echo '\(';
                        $this->renderDep($dep, true);
                        echo '\) is bad so ';
                    }
                    return false;
                }
            }
        }
        return true;
    }

    // Check if this relation is in 4NF
    function is4NF($verbose = false) {
        // Check if it's in BCNF first
        if (!$this->isBCNF($verbose)) {
            if ($verbose) {
                echo 'not in BCNF so ';
            }
            return false;
        }
        if ($verbose) {
            echo 'in BCNF ';
        }
        // Look for a simple key
        foreach ($this->candidateKeys() as $key) {
            if (count($key->contents) == 1) {
                if ($verbose) {
                    echo 'and \(' . $key . '\) is a simple key so ';
                }
                return true;
            }
        }
        if ($verbose) {
            echo 'but no simple key so ';
        }
        return false;
    }

    // Check if this relation is in 5NF
    function is5NF($verbose = false) {
        // Check if it's in 3NF first
        if (!$this->is3NF($verbose)) {
            if ($verbose) {
                echo 'not in 3NF so ';
            }
            return false;
        }
        if ($verbose) {
            echo 'in 3NF ';
        }
        // Check that all candidate keys are simple
        foreach ($this->candidateKeys() as $key) {
            if (count($key->contents) > 1) {
                if ($verbose) {
                    echo 'but \(' . $key . '\) is a non-simple candidate key so ';
                }
                return false;
            }
        }
        if ($verbose) {
            echo 'and all candidate keys are simple so ';
        }
        return true;
    }

    // Make [R-$beta, $alpha$beta] from this relation R and an $alpha and $beta, given all the closures
    function fracture($alpha, $beta, $closures) {
        // make a new Relation with only those attributes
        $ab = clone $alpha;
        $ab->addAll($beta);
        // find out the dependencies that matter
        $otherDeps = [];
        foreach ($ab->allSubsets() as $sub) {
            if (count($sub->contents) > 0) {
                $rhs = $this->closure($sub);
                if (!$sub->containsAll($rhs)) {
                    $rhs = new AttributeSet(array_diff($rhs->contents, $sub->contents));
                    $otherDeps[] = [$sub, $rhs];
                }
            }
        }
        $other = new Relation($ab, $otherDeps, false);
        $other->deps = $other->canonicalCover(false);
        // make a new Relation without beta
        $mine = new AttributeSet(array_diff($this->attrs->contents, $beta->contents));
        // find its dependencies too
        $myDeps = [];
        foreach ($mine->allSubsets() as $sub) {
            if (count($sub->contents) > 0) {
                $rhs = $this->closure($sub);
                if (!$sub->containsAll($rhs)) {
                    $rhs = new AttributeSet(array_diff($rhs->contents, $sub->contents));
                    $myDeps[] = [$sub, $rhs];
                }
            }
        }
        $me = new Relation($mine, $myDeps, false);
        $me->deps = $me->canonicalCover(false);
        // give both back
        return [$other, $me];
    }

    // Decompose this relation into BCNF
    // Algorithm from Silberschatz, Korth, Sudarshan "Database System Concepts" 6th ed. fig. 8.11
    function decomposeBCNF($verbose = false) {
        // Start with only R
        $result = [$this];
        $done = false;
        // Find F+
        $allClosures = $this->allClosures();
        // Until we're done...
        while (!$done) {
            if ($verbose) {
                echo '\(R\approx ';
                foreach ($result as $ri) {
                    $ri->attrs->renderTuple();
                }
                echo '\) but ';
            }
            $done = true;
            // For each relation in the result...
            for ($i = 0; $i < count($result); $i++) {
                $ri = $result[$i];
                // If it's not in BCNF...
                if (!$ri->isBCNF($verbose)) {
                    $done = false;
                    if ($verbose) {
                        echo '\(';
                        $ri->attrs->renderTuple();
                        echo '\) is not BCNF.<br>';
                    }
                    // Find some a->b where a is not a superkey of ri and a and b share nothing
                    $riSuperkeys = $ri->superkeys();
                    $riSubsets = $ri->attrs->allSubsets();
                    $riAlphas = array_diff($riSubsets, $riSuperkeys);
                    // We want only alphas that determine a nontrivial beta
                    $riAlphas = array_filter($riAlphas, function ($alpha) use ($ri) {
                        return !$alpha->containsAll($ri->closure($alpha));
                    });
                    $riAlphas = array_values($riAlphas);
                    assert(count($riAlphas) > 0);
                    $alpha = $riAlphas[0];
                    $beta = $ri->closure($alpha);
                    $beta = new AttributeSet(array_diff($beta->contents, $alpha->contents));
                    // Break up $ri into [$ri-$beta, $alpha$beta]
                    $newBits = $ri->fracture($alpha, $beta, $allClosures);
                    // Replace $ri with those fragments in the result
                    array_splice($result, $i, 1, $newBits);
                    // Don't keep looking through the result
                    break;
                }
            }
        }
        if ($verbose) {
            echo "that's in BCNF so we're done<br>";
        }
        sort($result);
        return $result;
    }

    // Decompose this relation into 3NF
    // Algorithm from Silberschatz, Korth, Sudarshan "Database System Concepts" 6th ed. fig. 8.12
    function decompose3NF($verbose = false) {
        // Grab the canonical cover
        $fc = $this->canonicalCover(false);
        if ($verbose) {
            echo '\(F_c=';
            (new Relation($this->attrs, $fc))->renderDeps(true);
            echo '\)<br>';
        }
        $i = 0;
        $result = [];
        // For each dependency in the canonical cover...
        foreach ($fc as $dep) {
            // Find all the attributes involved
            $ab = clone $dep[0];
            $ab->addAll($dep[1]);
            // Add a new relation with only those attributes
            $result[$i] = new Relation($ab, $fc, false);
            $i++;
        }
        $candKeyFound = false;
        // Grab the candidate keys
        $candKeys = $this->candidateKeys();
        // For each relation we already have...
        foreach ($result as $ri) {
            // For each candidate key...
            foreach ($candKeys as $ck) {
                // If this relation contains this candidate key...
                if ($ri->attrs->containsAll($ck)) {
                    // We found a candidate key!
                    $candKeyFound = true;
                    // Don't look for any others
                    break 2;
                }
            }
        }
        // If we never found a candidate key...
        if (!$candKeyFound) {
            // Throw one in
            $result[$i] = new Relation($candKeys[0], $fc, false);
            $i++;
        }
        if ($verbose) {
            echo '\(R\approx ';
            foreach ($result as $ri) {
                $ri->attrs->renderTuple();
            }
            echo '\)<br>';
        }
        $resCount = $i;
        // For every relation in the result...
        for ($i = 0; $i < $resCount; $i++) {
            // For every other relation in the result...
            foreach (array_keys($result) as $j) {
                // If they aren't the same but this one is a subset of that one...
                if ($i != $j && $result[$j]->attrs->containsAll($result[$i]->attrs)) {
                    // Remove this one
                    unset($result[$i]);
                    break;
                }
            }
        }
        sort($result);
        return array_values($result);
    }

    // Check if a decomposition of this relation is dependency preserving
    function isDepPres($decomp, $verbose = false) {
        // For each dependency...
        foreach ($this->deps as $dep) {
            list($lhs, $rhs) = $dep;
            $changed = true;
            // Build up the closure within the decomposition
            $realClosure = clone $lhs;
            while ($changed) {
                $changed = false;
                // For each relation in the composition...
                foreach ($decomp as $ri) {
                    // Merge in the closure of what we already have
                    $newStuff = $ri->closure($realClosure);
                    if (!$realClosure->containsAll($newStuff)) {
                        $realClosure->addAll($newStuff);
                        $changed = true;
                    }
                }
            }
            // If the closure in the decomposition doesn't contain the RHS of the dependency...
            if (!$realClosure->containsAll($rhs)) {
                // The decomposition can't be dependency preserving
                if ($verbose) {
                    echo 'under this decomposition \(' . $lhs . '^+=' . $realClosure . '\) so \(';
                    $this->renderDep($dep, true);
                    echo '\) was lost so ';
                }
                return false;
            }
        }
        return true;
    }

    // Check if a decomposition of this relation is lossless
    function isLossless($decomp) {
        // If there's only one relation in the decomposition...
        if (count($decomp) == 1) {
            // It's lossless iff it has all the same attributes
            return $decomp[0]->attrs->equals($this->attrs);
        } else if (count($decomp) == 2) {
            // If it's a binary decomposition...
            list($d0, $d1) = $decomp;
            // Find the attributes both relations have in common
            $commonAttrs = array_intersect($d0->attrs->contents, $d1->attrs->contents);
            $commonAttrs = new AttributeSet($commonAttrs);
            // Find attribute sets that are superkeys of either relation
            $acceptable = array_merge($d0->superkeys(), $d1->superkeys());
            // It's lossless iff the common attributes are a superkey of either relation
            if (in_array($commonAttrs, $acceptable)) {
                return true;
            }
            return false;
        } else if (count($decomp) > 2) {
            // If it's more than a binary decomposition...
            // For every pair of decompositions...
            for ($i = 0; $i < count($decomp) - 1; $i++) {
                for ($j = $i + 1; $j < count($decomp); $j++) {
                    // Make a copy of the decomposition
                    $newDecomp = $decomp;
                    // Extract the pair
                    $a = $decomp[$i];
                    unset($newDecomp[$i]);
                    $b = $decomp[$j];
                    unset($newDecomp[$j]);
                    // Merge the pair
                    $abAttrs = clone $a->attrs;
                    $abAttrs->addAll($b->attrs);
                    $ab = new Relation($abAttrs, array_merge($a->deps, $b->deps));
                    // If the pair can be combined losslessly...
                    if ($ab->isLossless([$a, $b])) {
                        // Replace the split pair with the merged pair
                        $newDecomp = array_values($newDecomp);
                        $newDecomp[] = $ab;
                        // Promise we got rid of a relation and aren't recursing infinitely
                        assert(count($newDecomp) < count($decomp));
                        // If merging that pair resulted in a lossless decomposition...
                        if ($this->isLossless($newDecomp)) {
                            // The original decomposition was also lossless
                            return true;
                        }
                    }
                }
            }
            return false;
        }
    }

    // Print out a whole bunch of stuff
    function debug() {
        echo '\(R=';
        $this->attrs->renderTuple();
        echo '\), \(\mathcal{F}=';
        $this->renderDeps(true);
        echo '\)<br><br>';
        echo 'Superkeys:<ul>';
        $superkeys = $this->superkeys();
        foreach ($superkeys as $sk) {
            echo '<li>\(' . $sk . '\)</li>';
        }
        echo '</ul>Candidate Keys:<ul>';
        $candKeys = $this->candidateKeys();
        foreach ($candKeys as $ck) {
            echo '<li>\(' . $ck . '\)</li>';
        }
        echo '</ul>';
        $cc = $this->canonicalCover();
        $rel2 = new Relation($this->attrs, $cc);
        echo '\(F_c=';
        $rel2->renderDeps(true);
        echo '\)<br><br>';
        $closures = $this->allClosures();
        $subsets = array_keys($closures);
        sort($subsets);
        usort($subsets, function ($a, $b) { return strlen($a) - strlen($b); });
        foreach ($subsets as $k) {
            if (strlen($k) > 0) {
                echo '\(' . $k . '^+=';
                $closures[$k]->renderSet(true);
                echo '\)<br>';
            }
        }
        echo '<br>3NF? ';
        if ($this->is3NF(true)) {
            echo 'yes';
        } else {
            echo 'no';
        }
        echo '<br>BCNF? ';
        if ($this->isBCNF(true)) {
            echo 'yes';
        } else {
            echo 'no';
        }
        echo '<br>4NF? ';
        if ($this->is4NF(true)) {
            echo 'yes';
        } else {
            echo 'no';
        }
        echo '<br>5NF? ';
        if ($this->is5NF(true)) {
            echo 'yes';
        } else {
            echo 'no';
        }

        echo '<br>';

        echo '<br>BCNF Decomposition<br>';
        $bcnf = $this->decomposeBCNF(true);
        echo '\(R = ';
        foreach ($bcnf as $ri) {
            $ri->attrs->renderTuple();
        }
        echo '\)<br>DP? ';
        if ($this->isDepPres($bcnf, true)) {
            echo 'yes';
        } else {
            echo 'no';
        }

        echo '<br><br>3NF Decomposition<br>';
        $tnf = $this->decompose3NF(true);
        echo '\(R = ';
        foreach ($tnf as $ri) {
            $ri->attrs->renderTuple();
        }
        echo '\)';

        echo '<br><br>Decompositions are ';
        if (!$this->isLossless($bcnf) || !$this->isLossless($tnf)) {
            echo 'NOT EVEN AAAAAAAAAAAAAAA ';
        }
        echo 'both lossless.';
    }
}
