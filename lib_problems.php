<?php
// Be verbose when reporting errors
error_reporting(-1);
ini_set('display_errors', 'On');
// Grab the classes
require_once('lib.php');
// If this is production (since I develop on Windows)...
if (php_uname('s') == 'Linux') {
    // Configure sessions to store properly
    ini_set('session.save_path', '/tmp');
    ini_set('session.name', 'NotPHPSESSID');
    ini_set('session.cookie_lifetime', 1000000);
    ini_set('session.cookie_path', '/~hornm/');
    ini_set('session.cookie_domain', 'cs.baylor.edu');
    ini_set('session.cookie_httponly', true);
}
session_start();

// Compare two attribute sets
$attrSetsCmp = function($a, $b) {
    if ($a->equals($b)) return 0;
    if ($a->containsAll($b)) return 1;
    return -1;
};

// Align an actual array with an expected array so as to minimize the Levenshtein distance between aligned pairs
// (probably; I haven't tested this exhaustively or even directly, but it's only used to get hints)
function levAlign($exp, $act) {
    // Lengths should be the same
    assert(count($exp) == count($act));
    // Find all the pairs and their Levenshtein distance
    $allPairs = [];
    for ($i = 0; $i < count($exp); $i++) {
        for ($j = 0; $j < count($act); $j++) {
            $allPairs[] = [levenshtein($exp[$i], $act[$j]), $i, $j];
        }
    }
    // Sort by Levenshtein distance
    sort($allPairs);
    $result = [];
    // For every pair...
    for ($i = 0; $i < count($allPairs); $i++) {
        list(, $eI, $aI) = $allPairs[$i];
        // If we haven't used either the expected or the actual yet...
        if (isset($exp[$eI]) && isset($act[$aI])) {
            // Align this actual with this expected
            $result[$eI] = $act[$aI];
            unset($exp[$eI]);
            unset($act[$aI]);
        }
    }
    return $result;
}

// If there isn't a relation, or there's an old batch of problems from before I added decompositions,
// or the user asked to reroll...
if (!isset($_SESSION['rel']) || !isset($_SESSION['dec-opts']) || isset($_REQUEST['reroll'])) {
    // Make and save a random relation R
    $_SESSION['rel'] = Relation::random();
    $rel = $_SESSION['rel'];

    // Get some subsets of R's attributes for later
    $attrSets = $rel->attrs->allSubsets();
    $nonempty = function($x) { return count($x->contents) > 0; };
    $attrSets = array_values(array_filter($attrSets, $nonempty));

    // Find the single-attribute subsets of R
    $smallAttrSets = array_values(array_filter($attrSets, function($x) { return count($x->contents) == 1; }));
    // Find the multi-attribute subsets of R
    $largeAttrSets = array_values(array_filter($attrSets, function($x) { return count($x->contents) > 1; }));
    // Pick some single-attribute subsets (by index)
    $smallClosureIdxs = array_rand($smallAttrSets, min(count($smallAttrSets), rand(2, 4)));
    // Pick some multi-attribute subsets (by index)
    $largeClosureIdxs = ensure_array(array_rand($largeAttrSets, rand(1, 3)));
    // Find each of those subsets
    $closureTargets = [];
    foreach ($smallClosureIdxs as $i) {
        $closureTargets[] = $smallAttrSets[$i];
    }
    foreach ($largeClosureIdxs as $i) {
        $closureTargets[] = $largeAttrSets[$i];
    }
    // Sort by length, then lexicographically (apparently)
    sort($closureTargets);
    // Those are the subsets to take the closure of
    $_SESSION['closure-targets'] = $closureTargets;

    // Didn't I move this above here?
    $attrSetsCmp = function($a, $b) {
        if ($a->equals($b)) return 0;
        if ($a->containsAll($b)) return 1;
        return -1;
    };
    // Grab the candidate keys
    $candKeys = array_values($rel->candidateKeys());
    // Grab the superkeys
    $superkeys = array_values($rel->superkeys());
    // Find the subsets that aren't superkeys
    $nonKeys = array_udiff($attrSets, $superkeys, $candKeys, $attrSetsCmp);
    // Find the superkeys that aren't candidate keys
    $superkeys = array_udiff($superkeys, $candKeys, $attrSetsCmp);
    // Find the subsets that still aren't superkeys or candidate keys (I am like 80% sure this still fixes things)
    $nonKeys = array_udiff($nonKeys, $superkeys, $candKeys, $attrSetsCmp);
    // If everything is a candidate key or everything is a superkey...
    if (count($superkeys) == 0 || count($nonKeys) == 0) {
        // Print a less-ugly error message to precede the uglier error message that's coming later
        // and prompt to retry
        echo "<h1>RNG machine &#x1F171;roke</h1><form action='problems.php' method='POST'><input type='submit' name='reroll' value='Understandable have a nice day'></form>";
    }
    // Pick one or two non-keys
    $nonKeyIdxs = ensure_array(array_rand($nonKeys, rand(1, 2)));
    // Pick one or two superkeys
    $superkeyIdxs = ensure_array(array_rand($superkeys, min(count($superkeys), rand(1, 2))));
    // Pick one or two candidate keys
    $candKeyIdxs = ensure_array(array_rand($candKeys, min(count($candKeys), rand(1, 2))));
    // Grab all those
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
    // Shuffle them around a bit
    shuffle($keyOpts);
    // Those are the attribute sets that might or might not be superkeys or candidate keys
    $_SESSION['key-opts'] = $keyOpts;

    // Guarantee there will be an implication that does not hold
    // by picking a non-key as the left hand side
    $implBadLHSIdx = array_rand($nonKeys, 1);
    $implBadLHS = $nonKeys[$implBadLHSIdx];
    // and something that isn't a subset of its closure as the right hand side
    $implBadRHSs = array_udiff($attrSets, $rel->closure($implBadLHS)->allSubsets(), $attrSetsCmp);
    $implBadRHSIdx = array_rand($implBadRHSs, 1);
    $implBadRHS = $implBadRHSs[$implBadRHSIdx];
    // Start out with that implication
    $implications = [[$implBadLHS, $implBadRHS]];
    // Don't reuse that LHS
    $implNotBadLHSs = array_udiff($attrSets, [$implBadLHS], $keyOpts, $attrSetsCmp);
    // Pick a few other LHSs
    $implLHSIdxs = array_rand($implNotBadLHSs, rand(3, 5));
    // For each of those...
    foreach ($implLHSIdxs as $i) {
        $lhs = $implNotBadLHSs[$i];
        // Find things it does determine
        $goodRHSs = $rel->closure($lhs)->allSubsets();
        $goodRHSs = array_values(array_filter($goodRHSs, $nonempty));
        // Find things it doesn't determine
        $badRHSs = array_udiff($attrSets, $goodRHSs, $attrSetsCmp);
        // If we're being nice or there aren't things it doesn't determine...
        if (rand(0, 1) == 0 || count($badRHSs) == 0) {
            // Pick something it does determine (so the dependency should hold)
            $goodIdx = array_rand($goodRHSs, 1);
            $rhs = $goodRHSs[$goodIdx];
        } else {
            // Pick something it doesn't determine (so the dependency shouldn't hold)
            $badIdx = array_rand($badRHSs, 1);
            $rhs = $badRHSs[$badIdx];
        }
        // Save the implication
        $implications[] = [$lhs, $rhs];
    }
    // Mix around the implications
    shuffle($implications);
    // Those are the implications we should ask about
    $_SESSION['impls'] = $implications;

    // The first decomposition is always the entire relation
    $decOpts = [[clone $rel]];
    // There are a few others
    $decCount = rand(3, 4);
    // We want to pick out some but not all of the attributes
    $decSubsets = range(1, pow(2, count($rel->attrs->contents)) - 2);
    // Pick some alphas to split out but include
    $decAlphaSubsets = array_values(array_rand($decSubsets, $decCount));
    // Pick some betas to split out and exclude
    $decBetaSubsets = array_values(array_rand($decSubsets, $decCount));
    // Pick one to be evil and re-split (if it's 0, we don't re-split anything)
    $evilIdx = array_rand(range(0, $decCount - 1));
    // Shuffle around the alphas
    shuffle($decAlphaSubsets);
    // Shuffle around the betas
    shuffle($decBetaSubsets);
    // For every decomposition that isn't the entire relation...
    for ($i = 1; $i < $decCount; $i++) {
        $alpha = $rel->attrs->getSubset($decSubsets[$decAlphaSubsets[$i]]);
        $beta = $rel->attrs->getSubset($decSubsets[$decBetaSubsets[$i]]);
        // Fracture the relation with those sets
        $decOpts[$i] = $rel->fracture($alpha, $beta);
        // If we're being evil...
        if ($i == $evilIdx) {
            // Pick one of the subrelations to refracture
            $refracIdx = array_rand($decOpts[$i], 1);
            $refrac = $decOpts[$i][$refracIdx];
            // Refracture it the same way we fractured the original relation
            $refSubsets = range(1, pow(2, count($refrac->attrs->contents)) - 2);
            $refAlphaIdx = array_rand($refSubsets, 1);
            $refBetaIdx = array_rand($refSubsets, 1);
            $alpha = $refrac->attrs->getSubset($refSubsets[$refAlphaIdx]);
            $beta = $refrac->attrs->getSubset($refSubsets[$refBetaIdx]);
            $refd = $refrac->fracture($alpha, $beta);
            // Replace the original subrelation with its fractured version
            unset($decOpts[$i][$refracIdx]);
            $decOpts[$i] = array_values($decOpts[$i]);
            $decOpts[$i][] = $refd[0];
            $decOpts[$i][] = $refd[1];
        }
        sort($decOpts[$i]);
    }
    $nice = rand(0, 1) == 1;
    // If we want to be nice, and the relation isn't BCNF already...
    if ($nice && !$rel->isBCNF()) {
        // If we want to give BCNF or it's already 3NF...
        if (rand(0, 1) == 1 || $rel->is3NF()) {
            // Throw in the BCNF decomposition too
            $decOpts[] = $rel->decomposeBCNF();
        } else {
            // Throw in the 3NF decomposition too
            $decOpts[] = $rel->decompose3NF();
        }
    }
    // Purge any duplicate decompositions we accidentally generated
    $decOpts = array_values(array_unique($decOpts, SORT_REGULAR));
    shuffle($decOpts);
    // Those are the decompositions we should be evaluating
    $_SESSION['dec-opts'] = $decOpts;
}

// Grab the relation
$rel = $_SESSION['rel'];

// Usually we aren't grading
$grading = false;
// If we're grading...
if (isset($_REQUEST['grade'])) {
    $grading = true;
    // Check whether to show the answer or a hint
    $show_answer = $_REQUEST['grade'] == 'Reveal Correct Answers';
    $show_hint = $_REQUEST['grade'] == 'Show Hints';

    // Grab the closure targets
    $closureTargets = $_SESSION['closure-targets'];
    $closureSubAnswers = [];
    $closureCorrAnswers = [];
    $closureHints = [];
    $closureCorrect = [];
    $closurePtsEarned = 0;
    $closurePtsPossible = 0;
    // For every closure that we asked for...
    for ($i = 0; $i < count($closureTargets); $i++) {
        // Find what the user put
        $closureSubAnswers[$i] = AttributeSet::from($_REQUEST['q-closures-c' . $i]);
        // Find the actual closure
        $closureCorrAnswers[$i] = $rel->closure($closureTargets[$i]);
        // Check if they match
        $closureCorrect[$i] = $closureSubAnswers[$i]->equals($closureCorrAnswers[$i]);
        $closurePtsPossible++;
        if ($closureCorrect[$i]) {
            $closurePtsEarned++;
            $closureHints[$i] = '';
        } else if ($closureSubAnswers[$i]->containsAll($closureCorrAnswers[$i])) {
            // If they submitted all the right things and also some other stuff, say something to that effect
            $closureHints[$i] = "Not all of these are right";
        } else {
            // If not, they're missing some of the right things
            $closureHints[$i] = "You're missing something";
        }
    }

    // Grab the key options
    $keyOpts = $_SESSION['key-opts'];
    // Grab the superkeys and candidate keys
    $superkeys = $rel->superkeys();
    $candKeys = $rel->candidateKeys();
    $keySubAnswers = [];
    $keyCorrAnswers = [];
    $keyHints = [];
    $keyCorrect = [];
    $keyPtsEarned = 0;
    $keyPtsPossible = 0;
    // For all the keys we asked about...
    for ($i = 0; $i < count($keyOpts); $i++) {
        $opt = $keyOpts[$i];
        // Find what the user said
        $subSK = isset($_REQUEST['q-skck-k' . $i . 'sk']);
        $subCK = isset($_REQUEST['q-skck-k' . $i . 'ck']);
        // Find what the correct answer was
        $corrSK = in_array($opt, $superkeys);
        $corrCK = in_array($opt, $candKeys);
        $keySubAnswers[$i] = [$subSK, $subCK];
        $keyCorrAnswers[$i] = [in_array($opt, $superkeys), in_array($opt, $candKeys)];
        // Check if they match
        $keyCorrect[$i] = $keySubAnswers[$i] == $keyCorrAnswers[$i];
        $keyPtsPossible++;
        if ($keyCorrect[$i]) {
            // (cSK, cCK, sSK, cCK), (cSK, !cCK, sSK, !sCK), (!cSK, !cCK, !sSK, !sCK)
            $keyPtsEarned++;
            $keyHints[$i] = '';
        } else if ($corrSK != $subSK) {
            // (cSK, ?cCK, !sSK, ?sCK), (!cSK, !cCK, sSK, ?sCK)
            // Either it isn't a superkey but they said it was, or it is but they said it wasn't
            $keyHints[$i] = 'Think about \(' . $opt . '^+\)';
        } else if ($subCK && !$subSK) {
            // (!cSK, !cCK, !sSK, sCK);
            // They said it was a candidate key but not a superkey, which is weird
            $keyHints[$i] = 'What is the definition of a candidate key?';
        } else if ($subCK && !$corrCK) {
            // (cSK, !cCK, sSK, sCK)
            // They said it was a candidate key but it was just a superkey
            $keyHints[$i] = 'wait how is this not a candidate key - yell at matt plz';
            // Find the candidate key that this superkey is a superset of
            foreach ($candKeys as $realCK) {
                if ($opt->containsAll($realCK) && !$realCK->containsAll($opt)) {
                    $keyHints[$i] = 'Think about \(' . $realCK . '^+\)';
                }
            }
        } else {
            // (cSK, cCK, sSK, !sCK)
            // They said it wasn't a candidate key but it was
            $keyHints[$i] = "Think about the subsets of " . $opt;
        }
    }

    // Find the candidate keys the user gave
    $ckSubAnswerRaw = $_REQUEST['q-ck'];
    $ckSubAnswer = explode(',', $ckSubAnswerRaw);
    $ckSubAnswer = array_map(function ($x) {
        $result = AttributeSet::from(trim($x));
        sort($result->contents);
        return $result;
    }, $ckSubAnswer);
    sort($ckSubAnswer);
    // Find the candidate keys that are right
    $ckCorrAnswer = $candKeys;
    sort($ckCorrAnswer);
    $ckCorrAnswerRaw = array_map(function ($x) { return implode('', $x->contents); }, $ckCorrAnswer);
    $ckCorrAnswerRaw = implode(', ', $ckCorrAnswerRaw);
    // Check if they match
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
        // Try to align what they put with what is right
        $ckSubAnswer = levAlign($ckCorrAnswer, $ckSubAnswer);
        // For each pair...
        for ($i = 0; $i < count($ckCorrAnswer); $i++) {
            if (!$ckSubAnswer[$i]->containsAll($ckCorrAnswer[$i])) {
                // If they missed a spot, nudge them about it
                $ckHint = 'Think about \(' . $ckSubAnswer[$i] . '^+\)';
            } else if (!$ckCorrAnswer[$i]->containsAll($ckSubAnswer[$i])) {
                // If they havde too much, ask why
                $ckHint = 'Something is redundant';
            }
        }
        // Disclaim everything in this case since levAlign() isn't perfect or clairvoyant
        $ckHint = $ckHint . ' (unless I screwed up)';
    }

    // Grab the dependencies we asked about that may or may not be implied by R
    $impls = $_SESSION['impls'];
    $implSubAnswers = [];
    $implCorrAnswers = [];
    $implCorrect = [];
    $implHint = [];
    $implPtsEarned = 0;
    $implPtsPossible = 0;
    // For each potential dependency...
    for ($i = 0; $i < count($impls); $i++) {
        list($lhs, $rhs) = $impls[$i];
        // If the user said something...
        if (isset($_REQUEST['q-impl-' . $i])) {
            // Remember what they said
            $implSubAnswers[$i] = $_REQUEST['q-impl-' . $i];
        } else {
            $implSubAnswers[$i] = '';
        }
        // Find the closure of the left-hand side
        $lhsClosure = $rel->closure($lhs);
        // The dependency should be true if the closure of the LHS contains the RHS
        if ($lhsClosure->containsAll($rhs)) {
            $implCorrAnswers[$i] = 'yes';
        } else {
            $implCorrAnswers[$i] = 'no';
        }
        // Check if the user was right
        $implCorrect[$i] = $implSubAnswers[$i] == $implCorrAnswers[$i];
        $implPtsPossible++;
        if ($implCorrect[$i]) {
            $implPtsEarned++;
            $implHint[$i] = '';
        } else if ($implSubAnswers[$i] == '') {
            $implHint[$i] = 'It helps if you pick an answer';
        } else if ($lhs->containsAll($rhs)) {
            // If it's trivial, point that out
            $implHint[$i] = 'Think about reflexivity';
        } else {
            // It's a yes or no question, saying "you're wrong" is already a pretty big hint
            $implHint[$i] = 'Think about \(' . $lhs . '^+\)';
        }
    }

    // Grab the canonical cover the user submitted
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
    // Find the actual canonical cover
    $ccCorrAnswer = $rel->canonicalCover(false);
    sort($ccCorrAnswer);
    $ccCorrAnswerHalfRaw = array_map(function ($x) {
        list($lhs, $rhs) = $x;
        return $lhs . '->' . $rhs;
    }, $ccCorrAnswer);
    $ccCorrAnswerRaw = implode(', ', $ccCorrAnswerHalfRaw);
    // Check if they match
    $ccCorrect = $ccSubAnswer == $ccCorrAnswer;
    $ccPtsEarned = 0 + $ccCorrect;
    $ccPtsPossible = 1;
    if ($ccCorrect) {
        $ccHint = '';
    } else if (count($ccSubAnswer) < count($ccCorrAnswer)) {
        // If they're missing a dependency, say so
        $ccHint = "You've taken out too much";
    } else if (count($ccSubAnswer) > count($ccCorrAnswer)) {
        // If they have too many dependencies, say so
        $ccHint = "You can eliminate more";
    } else {
        // Align the dependencies, hopefully
        $ccSubAnswerHalfRaw = levAlign($ccCorrAnswerHalfRaw, $ccSubAnswerHalfRaw);
        // For each dependency...
        for ($i = 0; $i < count($ccCorrAnswerHalfRaw); $i++) {
            $corr = $ccCorrAnswerHalfRaw[$i];
            $sub = $ccSubAnswerHalfRaw[$i];
            list($subLHS, $subRHS) = explode('->', $sub);
            if (strlen($sub) > strlen($corr)) {
                // If the submitted dependency has too much, say so
                $ccHint = "Something's (probably) redundant in \\(" . $subLHS . '\rightarrow ' . $subRHS . "\)";
            } else if (strlen($sub) < strlen($corr)) {
                // If the submitted dependency has too little, say so
                $ccHint = "Something (probably) wasn't redundant in \(" . $subLHS . '\rightarrow ' . $subRHS . "\)";
            } else if ($sub != $corr) {
                // Just give a general hint as to where an issue might be
                $ccHint = "Something's (probably) fishy with \(" . $subLHS . '\rightarrow ' . $subRHS . "\)";
            }
        }
        // Disclaim again
        $ccHint .= ' (unless your issue is somewhere else instead)';
    }

    // Find the decompositions we asked about
    $decOpts = $_SESSION['dec-opts'];
    $decSubAnswers = [];
    $decCorrAnswers = [];
    $decCorrect = [];
    $decHint = [];
    $decPtsPossible = count($decOpts) * 2;
    $decPtsEarned = 0;
    // For each of them...
    for ($i = 0; $i < count($decOpts); $i++) {
        $dec = $decOpts[$i];
        // Grab the highest normal form the user gave
        if (isset($_REQUEST['dec-' . $i . '-hnf'])) {
            $subHNF = (float) $_REQUEST['dec-' . $i . '-hnf'];
        } else {
            $subHNF = '';
        }
        // Check the user's answers for LJ and DP
        $subLJ = isset($_REQUEST['dec-' . $i . '-lj']);
        $subDP = isset($_REQUEST['dec-' . $i . '-dp']);
        $decSubAnswers[$i] = [$subHNF, $subLJ, $subDP];
        // Find if the decomposition is actually BC and 3
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
        // Find if the decomposition is actually DP or LJ
        $corrDP = $rel->isDepPres($dec);
        $corrLJ = $rel->isLossless($dec);
        $decCorrAnswers[$i] = [$corrHNFRaw, $corrLJ, $corrDP];
        // Check if highest normal form matches
        $decCorrect[$i] = [false, false];
        if ($corrHNF == $subHNF) {
            $decPtsEarned++;
            $decCorrect[$i][0] = true;
            $decHNFHint = '';
        } else if ($subHNF == '') {
            // Demand an answer
            $decHNFHint = 'It helps if you pick an answer';
        } else if ($corrHNF < $subHNF) {
            // If overestimated, prompt to reconsider
            $decHNFHint = 'Is this really in ';
            if ($subHNF == 3.5) {
                $decHNFHint .= 'BC';
            } else {
                $decHNFHint .= $subHNF;
            }
            $decHNFHint .= 'NF?';
        } else if ($corrHNF > $subHNF) {
            // If underestimated, prompt to reconsider
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
        // Check if DP/LJ match
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

    // Find the submitted 3NF decomposition
    $tnfSubAnswerRaw = $_REQUEST['q-3nf'];
    $tnfSubAnswer = explode(',', $tnfSubAnswerRaw);
    $tnfSubAnswer = array_map(function ($x) {
        $result = AttributeSet::from(trim($x));
        sort($result->contents);
        return $result;
    }, $tnfSubAnswer);
    sort($tnfSubAnswer);
    // Find the correct 3NF decomposition
    $tnfCorrAnswer = $rel->decompose3NF();
    $tnfCorrAnswer = array_map(function($x) {
        return $x->attrs;
    }, $tnfCorrAnswer);
    sort($tnfCorrAnswer);
    $tnfCorrAnswerRaw = array_map(function ($x) {
        return implode('', $x->contents);
    }, $tnfCorrAnswer);
    $tnfCorrAnswerRaw = implode(', ', $tnfCorrAnswerRaw);
    // Check if they match
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

    // Find the submitted BCNF decomposition
    $bcnfSubAnswerRaw = $_REQUEST['q-bcnf'];
    $bcnfSubAnswer = explode(',', $bcnfSubAnswerRaw);
    $bcnfSubAnswer = array_map(function ($x) {
        $result = AttributeSet::from(trim($x));
        sort($result->contents);
        return $result;
    }, $bcnfSubAnswer);
    sort($bcnfSubAnswer);
    // Find the actual BCNF decomposition
    $bcnfCorrAnswer = $rel->decomposeBCNF();
    $bcnfCorrAnswer = array_map(function($x) {
        return $x->attrs;
    }, $bcnfCorrAnswer);
    sort($bcnfCorrAnswer);
    $bcnfCorrAnswerRaw = array_map(function ($x) {
        return implode('', $x->contents);
    }, $bcnfCorrAnswer);
    $bcnfCorrAnswerRaw = implode(', ', $bcnfCorrAnswerRaw);
    // Check if they match
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

// This generates the email body for the link to yell at me when things don't work.
// It probably should handle serialization itself rather than deferring to JSON,
// but I already did it this way and I'm lazy.
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
