<?php
error_reporting(-1);
ini_set('display_errors', 'On');
require_once('lib.php');
if (php_uname('s') == 'Linux') {
    ini_set('session.save_path', '/tmp');
    ini_set('session.name', 'NotPHPSESSID');
    ini_set('session.cookie_lifetime', 1000000);
    ini_set('session.cookie_path', '/~hornm/');
    ini_set('session.cookie_domain', 'cs.baylor.edu');
    ini_set('session.cookie_httponly', true);
}
session_start();

$attrSetsCmp = function($a, $b) {
    if ($a->equals($b)) return 0;
    if ($a->containsAll($b)) return 1;
    return -1;
};

function levAlign($exp, $act) {
    assert(count($exp) == count($act));
    $allPairs = [];
    for ($i = 0; $i < count($exp); $i++) {
        for ($j = 0; $j < count($act); $j++) {
            $allPairs[] = [levenshtein($exp[$i], $act[$j]), $i, $j];
        }
    }
    sort($allPairs);
    $result = [];
    for ($i = 0; $i < count($allPairs); $i++) {
        list(, $eI, $aI) = $allPairs[$i];
        if (isset($exp[$eI]) && isset($act[$aI])) {
            $result[$eI] = $act[$aI];
            unset($exp[$eI]);
            unset($act[$aI]);
        }
    }
    return $result;
}

if (!isset($_SESSION['rel']) || !isset($_SESSION['dec-opts']) || isset($_REQUEST['reroll'])) {
    $_SESSION['rel'] = Relation::random();
    $rel = $_SESSION['rel'];

    $attrSets = $rel->attrs->allSubsets();
    $nonempty = function($x) { return count($x->contents) > 0; };
    $attrSets = array_values(array_filter($attrSets, $nonempty));

    $smallAttrSets = array_values(array_filter($attrSets, function($x) { return count($x->contents) == 1; }));
    $largeAttrSets = array_values(array_filter($attrSets, function($x) { return count($x->contents) > 1; }));
    $smallClosureIdxs = array_rand($smallAttrSets, min(count($smallAttrSets), rand(2, 4)));
    $largeClosureIdxs = ensure_array(array_rand($largeAttrSets, rand(1, 3)));
    $closureTargets = [];
    foreach ($smallClosureIdxs as $i) {
        $closureTargets[] = $smallAttrSets[$i];
    }
    foreach ($largeClosureIdxs as $i) {
        $closureTargets[] = $largeAttrSets[$i];
    }
    sort($closureTargets);
    $_SESSION['closure-targets'] = $closureTargets;

    $attrSetsCmp = function($a, $b) {
        if ($a->equals($b)) return 0;
        if ($a->containsAll($b)) return 1;
        return -1;
    };
    $candKeys = array_values($rel->candidateKeys());
    $superkeys = array_values($rel->superkeys());
    $nonKeys = array_udiff($attrSets, $superkeys, $candKeys, $attrSetsCmp);
    $superkeys = array_udiff($superkeys, $candKeys, $attrSetsCmp);
    $nonKeys = array_udiff($nonKeys, $superkeys, $candKeys, $attrSetsCmp);
    if (count($superkeys) == 0 || count($nonKeys) == 0) {
        echo "<h1>RNG machine &#x1F171;roke</h1><form action='problems.php' method='POST'><input type='submit' name='reroll' value='Understandable have a nice day'></form>";
    }
    $nonKeyIdxs = ensure_array(array_rand($nonKeys, rand(1, 2)));
    $superkeyIdxs = ensure_array(array_rand($superkeys, min(count($superkeys), rand(1, 2))));
    $candKeyIdxs = ensure_array(array_rand($candKeys, min(count($candKeys), rand(1, 2))));
    $keyOpts = [];
    foreach ($nonKeyIdxs as $i) {
        $keyOpts[] = $nonKeys[$i];
    }
    foreach ($superkeyIdxs as $i) {
        $keyOpts[] = $superkeys[$i];
    }
    foreach ($candKeyIdxs as $i) {
        $keyOpts[] = $candKeys[$i];
    }
    shuffle($keyOpts);
    $_SESSION['key-opts'] = $keyOpts;

    $implBadLHSIdx = array_rand($nonKeys, 1);
    $implBadLHS = $nonKeys[$implBadLHSIdx];
    $implBadRHSs = array_udiff($attrSets, $rel->closure($implBadLHS)->allSubsets(), $attrSetsCmp);
    $implBadRHSIdx = array_rand($implBadRHSs, 1);
    $implBadRHS = $implBadRHSs[$implBadRHSIdx];
    $implications = [[$implBadLHS, $implBadRHS]];
    $implNotBadLHSs = array_udiff($attrSets, [$implBadLHS], $keyOpts, $attrSetsCmp);
    $implLHSIdxs = array_rand($implNotBadLHSs, rand(3, 5));
    foreach ($implLHSIdxs as $i) {
        $lhs = $implNotBadLHSs[$i];
        $goodRHSs = $rel->closure($lhs)->allSubsets();
        $goodRHSs = array_values(array_filter($goodRHSs, $nonempty));
        $badRHSs = array_udiff($attrSets, $goodRHSs, $attrSetsCmp);
        if (rand(0, 1) == 0 || count($badRHSs) == 0) {
            $goodIdx = array_rand($goodRHSs, 1);
            $rhs = $goodRHSs[$goodIdx];
        } else {
            $badIdx = array_rand($badRHSs, 1);
            $rhs = $badRHSs[$badIdx];
        }
        $implications[] = [$lhs, $rhs];
    }
    shuffle($implications);
    $_SESSION['impls'] = $implications;

    $decOpts = [[clone $rel]];
    $decCount = rand(3, 4);
    $decSubsets = range(1, pow(2, count($rel->attrs->contents)) - 2);
    $decAlphaSubsets = array_values(array_rand($decSubsets, $decCount));
    $decBetaSubsets = array_values(array_rand($decSubsets, $decCount));
    $evilIdx = array_rand(range(0, $decCount - 1));
    shuffle($decAlphaSubsets);
    shuffle($decBetaSubsets);
    for ($i = 1; $i < $decCount; $i++) {
        $alpha = $rel->attrs->getSubset($decSubsets[$decAlphaSubsets[$i]]);
        $beta = $rel->attrs->getSubset($decSubsets[$decBetaSubsets[$i]]);
        $decOpts[$i] = $rel->fracture($alpha, $beta);
        if ($i == $evilIdx) {
            $refracIdx = array_rand($decOpts[$i], 1);
            $refrac = $decOpts[$i][$refracIdx];
            $refSubsets = range(1, pow(2, count($refrac->attrs->contents)) - 2);
            $refAlphaIdx = array_rand($refSubsets, 1);
            $refBetaIdx = array_rand($refSubsets, 1);
            $alpha = $refrac->attrs->getSubset($refSubsets[$refAlphaIdx]);
            $beta = $refrac->attrs->getSubset($refSubsets[$refBetaIdx]);
            $refd = $refrac->fracture($alpha, $beta);
            unset($decOpts[$i][$refracIdx]);
            $decOpts[$i] = array_values($decOpts[$i]);
            $decOpts[$i][] = $refd[0];
            $decOpts[$i][] = $refd[1];
        }
        sort($decOpts[$i]);
    }
    $nice = rand(0, 1) == 1;
    if ($nice && !$rel->isBCNF()) {
        if (rand(0, 1) == 1 || $rel->is3NF()) {
            $decOpts[] = $rel->decomposeBCNF();
        } else {
            $decOpts[] = $rel->decompose3NF();
        }
    }
    $decOpts = array_values(array_unique($decOpts, SORT_REGULAR));
    shuffle($decOpts);
    $_SESSION['dec-opts'] = $decOpts;
}

$rel = $_SESSION['rel'];

$grading = false;
$triviaSubAnswer = [];
if (isset($_REQUEST['grade'])) {
    $grading = true;
    $show_answer = $_REQUEST['grade'] == 'Reveal Correct Answers';
    $show_hint = $_REQUEST['grade'] == 'Show Hints';

    $closureTargets = $_SESSION['closure-targets'];
    $closureSubAnswers = [];
    $closureCorrAnswers = [];
    $closureHints = [];
    $closureCorrect = [];
    $closurePtsEarned = 0;
    $closurePtsPossible = 0;
    for ($i = 0; $i < count($closureTargets); $i++) {
        $closureSubAnswers[$i] = AttributeSet::from($_REQUEST['q-closures-c' . $i]);
        $closureCorrAnswers[$i] = $rel->closure($closureTargets[$i]);
        $closureCorrect[$i] = $closureSubAnswers[$i]->equals($closureCorrAnswers[$i]);
        $closurePtsPossible++;
        if ($closureCorrect[$i]) {
            $closurePtsEarned++;
            $closureHints[$i] = '';
        } else if ($closureSubAnswers[$i]->containsAll($closureCorrAnswers[$i])) {
            $closureHints[$i] = "Not all of these are right";
        } else {
            $closureHints[$i] = "You're missing something";
        }
    }

    $keyOpts = $_SESSION['key-opts'];
    $superkeys = $rel->superkeys();
    $candKeys = $rel->candidateKeys();
    $keySubAnswers = [];
    $keyCorrAnswers = [];
    $keyHints = [];
    $keyCorrect = [];
    $keyPtsEarned = 0;
    $keyPtsPossible = 0;
    for ($i = 0; $i < count($keyOpts); $i++) {
        $opt = $keyOpts[$i];
        $subSK = isset($_REQUEST['q-skck-k' . $i . 'sk']);
        $subCK = isset($_REQUEST['q-skck-k' . $i . 'ck']);
        $corrSK = in_array($opt, $superkeys);
        $corrCK = in_array($opt, $candKeys);
        $keySubAnswers[$i] = [$subSK, $subCK];
        $keyCorrAnswers[$i] = [in_array($opt, $superkeys), in_array($opt, $candKeys)];
        $keyCorrect[$i] = $keySubAnswers[$i] == $keyCorrAnswers[$i];
        $keyPtsPossible++;
        if ($keyCorrect[$i]) {
            // (cSK, cCK, sSK, cCK), (cSK, !cCK, sSK, !sCK), (!cSK, !cCK, !sSK, !sCK)
            $keyPtsEarned++;
            $keyHints[$i] = '';
        } else if ($corrSK != $subSK) {
            // (cSK, ?cCK, !sSK, ?sCK), (!cSK, !cCK, sSK, ?sCK)
            $keyHints[$i] = 'Think about \(' . $opt . '^+\)';
        } else if ($subCK && !$subSK) {
            // (!cSK, !cCK, !sSK, sCK);
            $keyHints[$i] = 'What is the definition of a candidate key?';
        } else if ($subCK && !$corrCK) {
            // (cSK, !cCK, sSK, sCK)
            $keyHints[$i] = 'wait how is this not a candidate key - yell at matt plz';
            foreach ($candKeys as $realCK) {
                if ($opt->containsAll($realCK) && !$realCK->containsAll($opt)) {
                    $keyHints[$i] = 'Think about \(' . $realCK . '^+\)';
                }
            }
        } else {
            // (cSK, cCK, sSK, !sCK)
            $keyHints[$i] = "Think about the subsets of " . $opt;
        }
    }

    $ckSubAnswerRaw = $_REQUEST['q-ck'];
    $ckSubAnswer = explode(',', $ckSubAnswerRaw);
    $ckSubAnswer = array_map(function ($x) {
        $result = AttributeSet::from(trim($x));
        sort($result->contents);
        return $result;
    }, $ckSubAnswer);
    sort($ckSubAnswer);
    $ckCorrAnswer = $candKeys;
    sort($ckCorrAnswer);
    $ckCorrAnswerRaw = array_map(function ($x) { return implode('', $x->contents); }, $ckCorrAnswer);
    $ckCorrAnswerRaw = implode(', ', $ckCorrAnswerRaw);
    $ckCorrect = $ckSubAnswer == $ckCorrAnswer;
    $ckPtsEarned = 0 + $ckCorrect;
    $ckPtsPossible = 1;
    if ($ckCorrect) {
        $ckHint = '';
    } else if (count($ckSubAnswer) < count($ckCorrAnswer)) {
        $ckHint = "You're missing a key";
    } else if (count($ckSubAnswer) > count($ckCorrAnswer)) {
        $ckHint = "You've got too many keys";
    } else {
        $ckSubAnswer = levAlign($ckCorrAnswer, $ckSubAnswer);
        for ($i = 0; $i < count($ckCorrAnswer); $i++) {
            if (!$ckSubAnswer[$i]->containsAll($ckCorrAnswer[$i])) {
                $ckHint = 'Think about \(' . $ckSubAnswer[$i] . '^+\)';
            } else if (!$ckCorrAnswer[$i]->containsAll($ckSubAnswer[$i])) {
                $ckHint = 'Something is redundant';
            }
        }
        $ckHint = $ckHint . ' (unless I screwed up)';
    }

    $impls = $_SESSION['impls'];
    $implSubAnswers = [];
    $implCorrAnswers = [];
    $implCorrect = [];
    $implHint = [];
    $implPtsEarned = 0;
    $implPtsPossible = 0;
    for ($i = 0; $i < count($impls); $i++) {
        list($lhs, $rhs) = $impls[$i];
        if (isset($_REQUEST['q-impl-' . $i])) {
            $implSubAnswers[$i] = $_REQUEST['q-impl-' . $i];
        } else {
            $implSubAnswers[$i] = '';
        }
        $lhsClosure = $rel->closure($lhs);
        if ($lhsClosure->containsAll($rhs)) {
            $implCorrAnswers[$i] = 'yes';
        } else {
            $implCorrAnswers[$i] = 'no';
        }
        $implCorrect[$i] = $implSubAnswers[$i] == $implCorrAnswers[$i];
        $implPtsPossible++;
        if ($implCorrect[$i]) {
            $implPtsEarned++;
            $implHint[$i] = '';
        } else if ($implSubAnswers[$i] == '') {
            $implHint[$i] = 'It helps if you pick an answer';
        } else if ($lhs->containsAll($rhs)) {
            $implHint[$i] = 'Think about reflexivity';
        } else {
            $implHint[$i] = 'Think about \(' . $lhs . '^+\)';
        }
    }

    $ccSubAnswerRaw = $_REQUEST['q-cc'];
    $ccSubAnswerHalfRaw = explode(',', $ccSubAnswerRaw);
    $ccSubAnswerHalfRaw = array_map('trim', $ccSubAnswerHalfRaw);
    if (strlen($ccSubAnswerRaw) > 0) {
        $ccSubAnswer = array_map(function ($x) {
            list($lhs, $rhs) = explode('->', trim($x));
            $lhs = AttributeSet::from(trim($lhs));
            sort($lhs->contents);
            $rhs = AttributeSet::from(trim($rhs));
            sort($rhs->contents);
            return [$lhs, $rhs];
        }, $ccSubAnswerHalfRaw);
    } else {
        $ccSubAnswer = [];
    }
    sort($ccSubAnswer);
    $ccCorrAnswer = $rel->canonicalCover(false);
    sort($ccCorrAnswer);
    $ccCorrAnswerHalfRaw = array_map(function ($x) {
        list($lhs, $rhs) = $x;
        return $lhs . '->' . $rhs;
    }, $ccCorrAnswer);
    $ccCorrAnswerRaw = implode(', ', $ccCorrAnswerHalfRaw);
    $ccCorrect = $ccSubAnswer == $ccCorrAnswer;
    $ccPtsEarned = 0 + $ccCorrect;
    $ccPtsPossible = 1;
    if ($ccCorrect) {
        $ccHint = '';
    } else if (count($ccSubAnswer) < count($ccCorrAnswer)) {
        $ccHint = "You've taken out too much";
    } else if (count($ccSubAnswer) > count($ccCorrAnswer)) {
        $ccHint = "You can eliminate more";
    } else {
        $ccSubAnswerHalfRaw = levAlign($ccCorrAnswerHalfRaw, $ccSubAnswerHalfRaw);
        for ($i = 0; $i < count($ccCorrAnswerHalfRaw); $i++) {
            $corr = $ccCorrAnswerHalfRaw[$i];
            $sub = $ccSubAnswerHalfRaw[$i];
            list($subLHS, $subRHS) = explode('->', $sub);
            if (strlen($sub) > strlen($corr)) {
                $ccHint = "Something's (probably) redundant in \\(" . $subLHS . '\rightarrow ' . $subRHS . "\)";
            } else if (strlen($sub) < strlen($corr)) {
                $ccHint = "Something (probably) wasn't redundant in \(" . $subLHS . '\rightarrow ' . $subRHS . "\)";
            } else if ($sub != $corr) {
                $ccHint = "Something's fishy with \(" . $subLHS . '\rightarrow ' . $subRHS . "\)";
            }
        }
    }

    $decOpts = $_SESSION['dec-opts'];
    $decSubAnswers = [];
    $decCorrAnswers = [];
    $decCorrect = [];
    $decHint = [];
    $decPtsPossible = count($decOpts) * 2;
    $decPtsEarned = 0;
    for ($i = 0; $i < count($decOpts); $i++) {
        $dec = $decOpts[$i];
        if (isset($_REQUEST['dec-' . $i . '-hnf'])) {
            $subHNF = (float) $_REQUEST['dec-' . $i . '-hnf'];
        } else {
            $subHNF = '';
        }
        $subLJ = isset($_REQUEST['dec-' . $i . '-lj']);
        $subDP = isset($_REQUEST['dec-' . $i . '-dp']);
        $decSubAnswers[$i] = [$subHNF, $subLJ, $subDP];
        $allBCNF = true;
        $all3NF = true;
        foreach ($dec as $d) {
            if (!$d->isBCNF()) {
                $allBCNF = false;
            }
            if (!$d->is3NF()) {
                $all3NF = false;
            }
        }
        if ($allBCNF) {
            $corrHNF = 3.5;
            $corrHNFRaw = 'BCNF';
        } else if ($all3NF) {
            $corrHNF = 3;
            $corrHNFRaw = '3NF';
        } else {
            $corrHNF = 1;
            $corrHNFRaw = '1NF';
        }
        $corrDP = $rel->isDepPres($dec);
        $corrLJ = $rel->isLossless($dec);
        $decCorrAnswers[$i] = [$corrHNFRaw, $corrLJ, $corrDP];
        $decCorrect[$i] = [false, false];
        if ($corrHNF == $subHNF) {
            $decPtsEarned++;
            $decCorrect[$i][0] = true;
            $decHNFHint = '';
        } else if ($subHNF == '') {
            $decHNFHint = 'It helps if you pick an answer';
        } else if ($corrHNF < $subHNF) {
            $decHNFHint = 'Is this really in ';
            if ($subHNF == 3.5) {
                $decHNFHint .= 'BC';
            } else {
                $decHNFHint .= $subHNF;
            }
            $decHNFHint .= 'NF?';
        } else if ($corrHNF > $subHNF) {
            $decHNFHint = 'Is this also in ';
            if ($subHNF == 1) {
                $decHNFHint .= '3';
            } else if ($subHNF == 3) {
                $decHNFHint .= 'BC';
            } else if ($subHNF == 3.5) {
                $decHNFHint .= '4';
            } else if ($subHNF == 4) {
                $decHNFHint .= '5';
            }
            $decHNFHint .= 'NF?';
        }
        $decOtherHint = '';
        if ($corrDP == $subDP && $corrLJ == $subLJ) {
            $decPtsEarned++;
            $decCorrect[$i][1] = true;
        } else {
            if ($corrDP != $subDP) {
                $decOtherHint = 'Is this dependency-preserving?';
            }
            if ($corrLJ == $subLJ) {
                $decOtherHint .= ' Is this lossless?';
            }
        }
        $decHint[$i] = [$decHNFHint, $decOtherHint];
    }

    $tnfSubAnswerRaw = $_REQUEST['q-3nf'];
    $tnfSubAnswer = explode(',', $tnfSubAnswerRaw);
    $tnfSubAnswer = array_map(function ($x) {
        $result = AttributeSet::from(trim($x));
        sort($result->contents);
        return $result;
    }, $tnfSubAnswer);
    sort($tnfSubAnswer);
    $tnfCorrAnswer = $rel->decompose3NF();
    $tnfCorrAnswer = array_map(function($x) {
        return $x->attrs;
    }, $tnfCorrAnswer);
    sort($tnfCorrAnswer);
    $tnfCorrAnswerRaw = array_map(function ($x) {
        return implode('', $x->contents);
    }, $tnfCorrAnswer);
    $tnfCorrAnswerRaw = implode(', ', $tnfCorrAnswerRaw);
    $tnfCorrect = $tnfSubAnswer == $tnfCorrAnswer;
    $tnfPtsEarned = 0 + $tnfCorrect;
    $tnfPtsPossible = 1;
    if ($tnfCorrect) {
        $tnfHint = '';
    } else if (count($tnfCorrAnswer) > count($tnfSubAnswer)) {
        $tnfHint = 'You (probably) missed an initial \(R_i\)';
    } else if (count($tnfCorrAnswer) < count($tnfSubAnswer)) {
        $tnfHint = 'You (probably) can combine two of these';
    } else {
        $tnfHint = "You've got the right number of relations, they're just wrong";
    }

    $bcnfSubAnswerRaw = $_REQUEST['q-bcnf'];
    $bcnfSubAnswer = explode(',', $bcnfSubAnswerRaw);
    $bcnfSubAnswer = array_map(function ($x) {
        $result = AttributeSet::from(trim($x));
        sort($result->contents);
        return $result;
    }, $bcnfSubAnswer);
    sort($bcnfSubAnswer);
    $bcnfCorrAnswer = $rel->decompose3NF();
    $bcnfCorrAnswer = array_map(function($x) {
        return $x->attrs;
    }, $bcnfCorrAnswer);
    sort($bcnfCorrAnswer);
    $bcnfCorrAnswerRaw = array_map(function ($x) {
        return implode('', $x->contents);
    }, $bcnfCorrAnswer);
    $bcnfCorrAnswerRaw = implode(', ', $bcnfCorrAnswerRaw);
    $bcnfCorrect = $bcnfSubAnswer == $bcnfCorrAnswer;
    $bcnfPtsEarned = 0 + $bcnfCorrect;
    $bcnfPtsPossible = 1;
    if ($bcnfCorrect) {
        $bcnfHint = '';
    } else if (count($bcnfCorrAnswer) > count($bcnfSubAnswer)) {
        $bcnfHint = "You don't have enough relations";
        foreach ($bcnfSubAnswer as $s) {
            $ri = new Relation($s, $rel->deps, false);
            if (!$ri->isBCNF()) {
                $bcnfHint = "One of your relations is not in BCNF";
            }
        }
    } else if (count($bcnfCorrAnswer) < count($bcnfSubAnswer)) {
        $bcnfHint = 'You have too many relations';
    } else {
        $bcnfHint = 'Things are just wrong';
        foreach ($bcnfSubAnswer as $s) {
            $ri = new Relation($s, $rel->deps, false);
            if (!$ri->isBCNF()) {
                $bcnfHint = 'One of your relations is not in BCNF';
            }
        }
    }

    $ptsPossible = $closurePtsPossible + $keyPtsPossible + $ckPtsPossible + $implPtsPossible + $ccPtsPossible + $decPtsPossible + $tnfPtsPossible + $bcnfPtsPossible;
    $ptsEarned = $closurePtsEarned + $keyPtsEarned + $ckPtsEarned + $implPtsEarned + $ccPtsEarned + $decPtsEarned+ $tnfPtsEarned + $bcnfPtsEarned;
}

function dumpEverything() {
    $result = "DESCRIBE YOUR ISSUE HERE PLEASE" . PHP_EOL . PHP_EOL . PHP_EOL . 'Context: ';
    $stringify = function ($x) {
        return '' . $x;
    };
    $stringifyDep = function ($x) {
        return $x[0] . '->' . $x[1];
    };
    $stringifyAllAttrs = function ($x) {
        $result = '';
        foreach ($x as $a) {
            $result .= '(' . $a->attrs . ')';
        }
        return $result;
    };
    $data = [
        'rel' => [
            'attrs' => '' . $_SESSION['rel']->attrs,
            'deps' => array_map($stringifyDep, $_SESSION['rel']->deps)
        ],
        'closure-targets' => array_map($stringify, $_SESSION['closure-targets']),
        'key-opts' => array_map($stringify, $_SESSION['key-opts']),
        'impls' => array_map($stringifyDep, $_SESSION['impls']),
        'dec-opts' => array_map($stringifyAllAttrs, $_SESSION['dec-opts'])
    ];
    $result .= json_encode($data);
    return $result;
}
