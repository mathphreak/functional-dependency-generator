<?php
function ensure_array($x) {
    if (is_array($x)) {
        return $x;
    }
    return [$x];
}

class AttributeSet {
    public $contents;
    function __construct($contents) {
        $this->contents = array_values($contents);
    }

    function __toString() {
        return implode('', $this->contents);
    }

    static function range($start, $end) {
        return new AttributeSet(range($start, $end));
    }

    static function from($str) {
        return new AttributeSet(str_split($str));
    }

    function renderAttr($i) {
        echo $this->contents[$i];
    }

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

    function renderList() {
        for ($i = 0; $i < count($this->contents); $i++) {
            $this->renderAttr($i);
        }
    }

    function containsAll($other) {
        // debug_print_backtrace();
        return count(array_diff($other->contents, $this->contents)) == 0;
    }

    function addAll($other) {
        $this->contents = array_unique(array_merge($this->contents, $other->contents));
        sort($this->contents);
    }

    function equals($other) {
        return $this->containsAll($other) && $other->containsAll($this);
    }

    function getSubset($n) {
        $include = str_split(str_pad(base_convert($n, 10, 2), count($this->contents), '0', STR_PAD_LEFT));
        $arr = array_combine($this->contents, $include);
        $arr = array_filter($arr, function($v) {
            return $v == '1';
        });
        $arr = array_keys($arr);
        return new AttributeSet($arr);
    }

    function randSubset() {
        $n = rand(1, pow(2, count($this->contents)) - 1);
        return $this->getSubset($n);
    }

    function allSubsets() {
        $arr = range(0, pow(2, count($this->contents)) - 1);
        $arr = array_map(function($n) {return $this->getSubset($n);}, $arr);
        return $arr;
    }
}

function closuresEqual($one, $two) {
    $pairs = array_map(null, $one, $two);
    return array_reduce($pairs, function ($wasGood, $pair) {
        return $wasGood && $pair[0]->equals($pair[1]);
    }, true);
}

class Relation {
    public $attrs;
    public $deps;
    function __construct($attrs, $deps, $depsGood = true) {
        if (!$depsGood) {
            foreach (array_keys($deps) as $i) {
                list($lhs, $rhs) = $deps[$i];
                if (!$attrs->containsAll($lhs)) {
                    unset($deps[$i]);
                    continue;
                }
                $rhs = new AttributeSet(array_intersect($rhs->contents, $attrs->contents));
                if (count($rhs->contents) == 0) {
                    unset($deps[$i]);
                    continue;
                } else {
                    $deps[$i][1] = $rhs;
                }
            }
        }
        $this->attrs = $attrs;
        $this->deps = array_values($deps);
    }

    static function random() {
        $attrs = AttributeSet::range('A', chr(ord('A') + rand(3, 5)));
        $deps = [];
        $minDeps = count($attrs->contents) / 2;
        $maxDeps = count($attrs->contents);
        $depCount = rand((int)$minDeps, (int)$maxDeps);
        for ($i = 0; $i < $depCount; $i++) {
            $deps[] = [$attrs->randSubset(), $attrs->randSubset()];
        }
        return new Relation($attrs, $deps);
    }

    function renderDep($i, $tex = false) {
        $dep = $this->deps[$i];
        $dep[0]->renderList();
        if ($tex) {
            echo '\\rightarrow ';
        } else {
            echo '&rarr;';
        }
        $dep[1]->renderList();
    }

    function renderDeps($tex = false) {
        if ($tex) {
            echo '\\';
        }
        echo '{';
        for ($i = 0; $i < count($this->deps); $i++) {
            $this->renderDep($i, $tex);
            if ($i < count($this->deps) - 1) {
                echo ', ';
            }
        }
        if ($tex) {
            echo '\\';
        }
        echo '}';
    }

    function render() {
        $this->attrs->renderTuple();
        echo ' ';
        $this->renderDeps();
    }

    function closure($attrs) {
        $closure = clone $attrs;
        $found = true;
        while ($found) {
            $found = false;
            foreach ($this->deps as $dep) {
                $lhs = $dep[0];
                $rhs = $dep[1];
                if ($closure->containsAll($lhs)) {
                    if (!$closure->containsAll($rhs)) {
                        $found = true;
                        $closure->addAll($rhs);
                    }
                }
            }
        }
        return $closure;
    }

    function allClosures() {
        $subsets = $this->attrs->allSubsets();
        $result = [];
        foreach ($subsets as $subset) {
            $result[implode('', $subset->contents)] = $this->closure($subset);
        }
        return $result;
    }

    function superkeys() {
        $allClosures = $this->allClosures();
        $superkeys = array_filter($allClosures, function ($c) {
            return $c->containsAll($this->attrs);
        });
        $superkeys = array_keys($superkeys);
        sort($superkeys);
        return array_map(function ($x) {return AttributeSet::from($x);}, $superkeys);
    }

    function candidateKeys() {
        $superkeys = $this->superkeys();
        $isCandidate = function($key) use ($superkeys) {
            foreach ($superkeys as $sk) {
                if ($key->containsAll($sk) && !$sk->containsAll($key)) {
                    return false;
                }
            }
            return true;
        };
        $candKeys = array_filter($superkeys, $isCandidate);
        return array_values($candKeys);
    }

    function canonicalCover($verbose = true) {
        $deps = array_values($this->deps);
        $shrunk = true;
        $closures = $this->allClosures();
        $iters = 0;
        while ($shrunk && $iters < 1000) {
            if ($verbose) {
                (new Relation($this->attrs, $deps))->renderDeps();
                echo '<br>';
            }
            $shrunk = false;
            $firstDeps = [];
            for ($i = 0; $i < count($deps); $i++) {
                $dep = $deps[$i];
                $lhs = '' . $dep[0];
                $removing = false;
                if (isset($firstDeps[$lhs])) {
                    $deps[$firstDeps[$lhs]][1] = clone $deps[$firstDeps[$lhs]][1];
                    $deps[$firstDeps[$lhs]][1]->addAll($dep[1]);
                    $removing = true;
                }
                if (count($dep[0]->contents) == 0 || count($dep[1]->contents) == 0) {
                    $removing = true;
                }
                if ($removing) {
                    $shrunk = true;
                    if ($verbose) {
                        echo 'Removed ' . $dep[0] . '&rarr;' . $dep[1] . ' entirely.<br>';
                    }
                    unset($deps[$i]);
                    $deps = array_values($deps);
                    break;
                } else {
                    $firstDeps[$lhs] = $i;
                }
            }
            for ($i = 0; $i < count($deps); $i++) {
                for ($j = 0; $j < count($deps[$i]); $j++) {
                    for ($k = 0; $k < count($deps[$i][$j]->contents); $k++) {
                        $iters++;
                        $newDeps = $deps;
                        $newDeps[$i][$j] = clone $newDeps[$i][$j];
                        $removed = $newDeps[$i][$j]->contents[$k];
                        unset($newDeps[$i][$j]->contents[$k]);
                        $newDeps[$i][$j]->contents = array_values($newDeps[$i][$j]->contents);
                        $newThis = new Relation($this->attrs, $newDeps);
                        $newClosures = $newThis->allClosures();
                        if (closuresEqual($closures, $newClosures)) {
                            if ($verbose) {
                                echo 'Removed ' . $removed . ' to get ' . $newDeps[$i][0] . '&rarr;' . $newDeps[$i][1] . '<br>';
                            }
                            $deps = $newDeps;
                            $shrunk = true;
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
        $deps = array_filter($deps, function ($dep) {
            return count($dep[0]->contents) > 0 && count($dep[1]->contents) > 0;
        });
        return array_values($deps);
    }

    function isBCNF() {
        $keys = $this->superkeys();
        foreach ($this->deps as $dep) {
            list($lhs, $rhs) = $dep;
            if ($lhs->containsAll($rhs)) {
                continue;
            } else if (in_array($lhs, $keys)) {
                continue;
            } else {
                return false;
            }
        }
        return true;
    }

    function is3NF() {
        $superkeys = $this->superkeys();
        $candKeys = $this->candidateKeys();
        $acceptableAttrs = clone $candKeys[0];
        foreach ($candKeys as $ck) {
            $acceptableAttrs->addAll($ck);
        }
        foreach ($this->deps as $dep) {
            list($lhs, $rhs) = $dep;
            if ($lhs->containsAll($rhs)) {
                continue;
            } else if (in_array($lhs, $superkeys)) {
                continue;
            } else {
                $goodRHS = clone $acceptableAttrs;
                $goodRHS->addAll($lhs);
                if (!$goodRHS->containsAll($rhs)) {
                    return false;
                }
            }
        }
        return true;
    }

    function fracture($alpha, $beta) {
        // make a new Relation with only those attributes
        $ab = clone $alpha;
        $ab->addAll($beta);
        $other = new Relation($ab, $this->deps, false);
        // make a new Relation without beta
        $mine = new AttributeSet(array_diff($this->attrs->contents, $beta->contents));
        $me = new Relation($mine, $this->deps, false);
        // give both back
        return [$me, $other];
    }

    // SKS fig 8.11
    function decomposeBCNF() {
        $result = [$this];
        $done = false;
        $allClosures = $this->allClosures();
        while (!$done) {
            $done = true;
            for ($i = 0; $i < count($result); $i++) {
                $ri = $result[$i];
                if (!$ri->isBCNF()) {
                    $done = false;
                    // we want to find some a->b where a is not a superkey of ri and a and b share nothing
                    $riSuperkeys = $ri->superkeys();
                    $riSubsets = $ri->attrs->allSubsets();
                    $riAlphas = array_diff($riSubsets, $riSuperkeys);
                    $riAlphas = array_filter($riAlphas, function ($alpha) use ($ri) {
                        return !$alpha->containsAll($ri->closure($alpha));
                    });
                    $riAlphas = array_values($riAlphas);
                    assert(count($riAlphas) > 0);
                    $alpha = $riAlphas[0];
                    $beta = $ri->closure($alpha);
                    $beta = new AttributeSet(array_diff($beta->contents, $alpha->contents));
                    $newBits = $ri->fracture($alpha, $beta);
                    array_splice($result, $i, 1, $newBits);
                    break;
                }
            }
        }
        sort($result);
        return $result;
    }

    // SKS fig 8.12
    function decompose3NF() {
        $fc = $this->canonicalCover(false);
        $i = 0;
        $result = [];
        foreach ($fc as $dep) {
            $ab = clone $dep[0];
            $ab->addAll($dep[1]);
            $result[$i] = new Relation($ab, $fc, false);
            $i++;
        }
        $candKeyFound = false;
        $candKeys = $this->candidateKeys();
        foreach ($result as $ri) {
            foreach ($candKeys as $ck) {
                if ($ri->attrs->containsAll($ck)) {
                    $candKeyFound = true;
                    break 2;
                }
            }
        }
        if (!$candKeyFound) {
            $result[$i] = new Relation($candKeys[0], $fc, false);
            $i++;
        }
        $resCount = $i;
        for ($i = 0; $i < $resCount; $i++) {
            foreach (array_keys($result) as $j) {
                if ($i != $j && $result[$j]->attrs->containsAll($result[$i]->attrs)) {
                    unset($result[$i]);
                    break;
                }
            }
        }
        sort($result);
        return array_values($result);
    }

    function isDepPres($decomp) {
        $allSubsets = $this->attrs->allSubsets();
        foreach ($allSubsets as $attrs) {
            $changed = true;
            $goodClosure = $this->closure($attrs);
            $realClosure = clone $attrs;
            while ($changed) {
                $changed = false;
                foreach ($decomp as $ri) {
                    $newStuff = $ri->closure($realClosure);
                    if (!$realClosure->containsAll($newStuff)) {
                        $realClosure->addAll($ri->closure($realClosure));
                        $changed = true;
                    }
                }
            }
            if (!$goodClosure->equals($realClosure)) {
                // var_dump(''.$attrs, ''.$goodClosure, ''.$realClosure);
                return false;
            }
        }
        return true;
    }

    function isLossless($decomp) {
        if (count($decomp) == 1) {
            return $decomp[0]->attrs->equals($this->attrs);
        } else if (count($decomp) == 2) {
            list($d0, $d1) = $decomp;
            $commonAttrs = array_intersect($d0->attrs->contents, $d1->attrs->contents);
            $commonAttrs = new AttributeSet($commonAttrs);
            $acceptable = array_merge($d0->superkeys(), $d1->superkeys());
            if (in_array($commonAttrs, $acceptable)) {
                return true;
            }
            return false;
        } else if (count($decomp) > 2) {
            for ($i = 0; $i < count($decomp) - 1; $i++) {
                for ($j = $i + 1; $j < count($decomp); $j++) {
                    $newDecomp = $decomp;
                    $a = $decomp[$i];
                    unset($newDecomp[$i]);
                    $b = $decomp[$j];
                    unset($newDecomp[$j]);
                    $abAttrs = clone $a->attrs;
                    $abAttrs->addAll($b->attrs);
                    $ab = new Relation($abAttrs, array_merge($a->deps, $b->deps));
                    if ($ab->isLossless([$a, $b])) {
                        $newDecomp = array_values($newDecomp);
                        $newDecomp[] = $ab;
                        assert(count($newDecomp) < count($decomp));
                        if ($this->isLossless($newDecomp)) {
                            return true;
                        }
                    }
                }
            }
            return false;
        }
    }
    
    function debug() {
        $this->render();
        echo '<br><br>';
        echo 'Closure of A is ';
        $this->closure(AttributeSet::from('A'))->renderSet();
        echo '<br><br>';
        echo 'Superkeys:<ul>';
        $superkeys = $this->superkeys();
        foreach ($superkeys as $sk) {
            echo '<li>' . $sk . '</li>';
        }
        echo '</ul>Candidate Keys:<ul>';
        $candKeys = $this->candidateKeys();
        foreach ($candKeys as $ck) {
            echo '<li>' . $ck . '</li>';
        }
        echo '</ul>';
        $cc = $this->canonicalCover();
        $rel2 = new Relation($this->attrs, $cc);
        echo 'Canonical cover is ';
        $rel2->renderDeps();
        echo '<br><br>';
        $closures = $this->allClosures();
        foreach (array_keys($closures) as $k) {
            echo $k . '<sup>+</sup> = ';
            $closures[$k]->renderSet();
            echo '<br>';
        }
        echo '<br>BCNF: ';
        if ($this->isBCNF()) {
            echo 'yes';
        } else {
            echo 'no';
        }
        echo '<br>3NF: ';
        if ($this->is3NF()) {
            echo 'yes';
        } else {
            echo 'no';
        }

        echo '<br>';

        echo '<br>BCNF Decomposition: ';
        $bcnf = $this->decomposeBCNF();
        foreach ($bcnf as $ri) {
            $ri->attrs->renderTuple();
            echo ' ';
        }
        if (!$this->isDepPres($bcnf)) {
            echo 'NOT ';
        }
        echo 'Dependency Preserving';

        echo '<br>3NF Decomposition: ';
        foreach ($this->decompose3NF() as $ri) {
            $ri->attrs->renderTuple();
            echo ' ';
        }

        echo '<br>The world does ';
        if (!$this->isLossless($bcnf) || !$this->isLossless($this->decompose3NF())) {
            echo 'NOT AT ALL ';
        }
        echo 'make sense.';
    }
}
