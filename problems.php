<?php
error_reporting(-1);
ini_set('display_errors', 'On');
require_once('lib.php');
require_once('lib_problems.php');
?><html>
<head>
<title>Functional Dependency Practice Questions</title>
<meta name="viewport", content="width=device-width, initial-scale=1">
<script src='https://cdnjs.cloudflare.com/ajax/libs/mathjax/2.7.2/MathJax.js?config=TeX-AMS_CHTML'></script>
<style>
    #q-closures span.prompt {
        text-align: right;
        width: 7em;
        display: inline-block;
        /* padding-right: 1em; */
    }
    .table th, .table td {
        border: 1px solid black;
        padding: 2px 5px;
    }
    .table td:not(.answer) {
        text-align: center;
    }
    .table td:first-child {
        text-align: right;
    }
    .note::before {
        content: 'Note: ';
        font-weight: bold;
    }
    .note {
        font-style: italic;
    }
    kbd {
        padding: 2px;
        background-color: #CCC;
    }
    #show-secret:not(:checked) + .secret {
        display: none;
    }
    .secret {
        margin: 20px;
    }
    .correct::before, .incorrect::before {
        font-weight: bold;
    }
    .correct::before {
        content: '\2714';
    }
    #problem .correct::before {
        content: '\1F4AF';
    }
    .correct {
        color: hsl(120, 100%, 30%);
    }
    .incorrect::before {
        content: '\2718';
    }
    .incorrect {
        color: hsl(0, 100%, 30%);
    }
    #problem {
        position: sticky;
        top: 0px;
        background-color: white;
        z-index: 1;
    }
</style>
</head>
<body>
<?php if (error_get_last() != NULL) { ?>
    <h2>it looks like something exploded, your questions might be weird, just generate a new set, email me if this keeps happening to you</h2>
<?php } ?>
<h2>If things are on fire or give results you don't think are right, you should <a href="mailto:M_Horn@baylor.edu?subject=%F0%9F%92%A9%F0%9F%94%9D%F0%9F%94%A5%F0%9F%91%8B&body=<?php
    echo rawurlencode(dumpEverything());
?>">email me</a>.</h2>
<p id="problem"><?php if($grading) { ?>
    <span class="<?php if ($ptsEarned < $ptsPossible) { ?>in<?php } ?>correct">
        <?php echo $ptsEarned . '/' . $ptsPossible; ?>
    </span>
<?php } ?>For the following questions, use \(R\) is \(<?php $rel->attrs->renderTuple();?>\) and \(\mathcal{F}=<?php $rel->renderDeps(true);?>\).</p>
<form action='problems.php' method='POST'>
    <ol>
        <li id="q-closures">
            <p><?php if($grading) { ?>
                <span class="<?php
                    if ($closurePtsEarned < $closurePtsPossible) {
                        ?>in<?php
                    } ?>correct">
                    <?php echo $closurePtsEarned . '/' . $closurePtsPossible; ?>
                </span>
            <?php } ?> Find each of the following closures.</p>
            <p class="note">If you have \(X^+=\{X,Y,Z\}\), type <kbd>XYZ</kbd>. Order shouldn't matter.</p>
            <ul>
                <?php
                $closureTargets = $_SESSION['closure-targets'];
                for ($i = 0; $i < count($closureTargets); $i++) {
                    $ct = $closureTargets[$i]; ?>
                    <li>
                        <span class='prompt'>\(<?php echo $ct; ?>^+={}\)</span><input type="text" name="q-closures-c<?php echo $i; ?>"
                            <?php if ($grading) { ?> value="<?php echo $closureSubAnswers[$i]; ?>" <?php } ?>
                            >
                        <?php if ($grading) {
                            if ($closureCorrect[$i]) { ?>
                                <span class="correct">
                            <?php } else { ?>
                                <span class="incorrect">
                            <?php }
                            if ($show_answer) {
                                echo $closureCorrAnswers[$i];
                            } else if ($show_hint) {
                                echo $closureHints[$i];
                            } ?>
                            </span>
                        <?php } ?>
                    </li>
                <?php } ?>
            </ul>
        </li>
        <li id="q-skck">
            <p><?php if($grading) { ?>
                <span class="<?php if ($keyPtsEarned < $keyPtsPossible) { ?>in<?php } ?>correct">
                    <?php echo $keyPtsEarned . '/' . $keyPtsPossible; ?>
                </span>
            <?php } ?>For each of the following sets of attributes, indicate whether or not they are superkeys and whether or not they are candidate keys.</p>
            <table class="table">
            <thead>
                <tr>
                    <th>Attributes</th><th>Superkey?</th><th>Candidate Key?</th>
                    <?php if ($grading) { ?>
                        <th>
                            <?php if ($show_answer) { ?> Answer <?php } else { ?> Correct? <?php } ?>
                        </th>
                    <?php } ?>
                </tr>
            </thead>
            <tbody>
                <?php
                $keyOpts = $_SESSION['key-opts'];
                for ($i = 0; $i < count($keyOpts); $i++) {
                    $opt = $keyOpts[$i]; ?>
                    <tr>
                        <td>\(<?php echo $opt; ?>\)</td>
                        <td><label>SK <input type="checkbox" name="q-skck-k<?php echo $i;?>sk"
                            <?php if ($grading && $keySubAnswers[$i][0]) { ?> checked <?php } ?>
                            ></label></td>
                        <td><label>CK <input type="checkbox" name="q-skck-k<?php echo $i;?>ck"
                            <?php if ($grading && $keySubAnswers[$i][1]) { ?> checked <?php } ?>
                            ></label></td>
                        <?php if ($grading) { ?>
                            <td class="answer">
                                <span class="<?php if ($keyCorrect[$i]) { ?>correct<?php } else { ?>incorrect<?php } ?>">
                                    <?php if ($show_answer) { ?>
                                        SK <?php if ($keyCorrAnswers[$i][0]) { ?>&#x2611;<?php } else { ?>&#x2610;<?php } ?>
                                        CK <?php if ($keyCorrAnswers[$i][1]) { ?>&#x2611;<?php } else { ?>&#x2610;<?php } ?>
                                    <?php } else if ($show_hint) {
                                        echo $keyHints[$i];
                                    } ?>
                                </span>
                            </td>
                        <?php } ?>
                    </tr>
                <?php } ?>
            </tbody>
            </table>
        </li>
        <li id="q-ck">
            <p><?php if($grading) { ?>
                <span class="<?php if (!$ckCorrect) { ?>in<?php } ?>correct"> <?php echo $ckPtsEarned . '/' . $ckPtsPossible; ?></span>
            <?php } ?>List all candidate keys for \(R\).</p>
            <p class="note">If the candidate keys are \(XZ\) and \(YZ\), type <kbd>XZ, YZ</kbd>. Any order and any spacing should be acceptable, just include the comma.</p>
            <input type="text" name="q-ck" placeholder="ex. XZ, YZ" <?php if ($grading) { ?>value="<?php echo $ckSubAnswerRaw; ?>"<?php } ?> >
            <?php if ($grading) { ?>
                <span class="<?php if ($ckCorrect) { ?>correct<?php } else { ?>incorrect<?php } ?>">
                    <?php if ($show_answer) {
                        echo $ckCorrAnswerRaw;
                    } else if ($show_hint) {
                        echo $ckHint;
                    } ?>
                </span>
            <?php } ?>
        </li>
        <li id="q-implied">
            <p><?php if($grading) { ?>
                <span class="<?php if ($implPtsEarned < $implPtsPossible) { ?>in<?php } ?>correct"> <?php echo $implPtsEarned . '/' . $implPtsPossible; ?></span>
            <?php } ?>For each of the following functional dependencies, determine whether or not they are reachable from \(\mathcal{F}\) using Armstrong's axioms.</p>
            <p class="note">If you wind up with something like \(X\rightarrow\) with nothing determined, that counts as reachable. I tried to get rid of those, but I'm bad at this.</p>
            <table class="table">
            <thead>
                <tr>
                    <th>Dependency</th><th>Reachable</th><th>Unreachable</th>
                    <?php if ($grading) { ?>
                        <th>
                            <?php if ($show_answer) { ?> Answer <?php } else { ?> Correct? <?php } ?>
                        </th>
                    <?php } ?>
                </tr>
            </thead>
            <tbody>
                <?php
                $impls = $_SESSION['impls'];
                for ($i = 0; $i < count($impls); $i++) {
                    $impl = $impls[$i];
                    $lhs = $impl[0];
                    $rhs = $impl[1]; ?>
                    <tr>
                        <td>\(<?php echo $lhs; ?>\rightarrow <?php echo $rhs; ?>\)</td>
                        <td><input type="radio" name="q-impl-<?php echo $i; ?>" value="yes"
                            <?php if ($grading && $implSubAnswers[$i] == 'yes') { ?> checked <?php } ?>
                            ></td>
                        <td><input type="radio" name="q-impl-<?php echo $i; ?>" value="no"
                            <?php if ($grading && $implSubAnswers[$i] == 'no') { ?> checked <?php } ?>
                            ></td>
                        <?php if ($grading) { ?>
                            <td class="answer">
                                <span class="<?php if ($implCorrect[$i]) { ?>correct<?php } else { ?>incorrect<?php } ?>">
                                    <?php if ($show_answer) {
                                        if ($implCorrAnswers[$i] == 'yes') { ?>&#x25CF;&#x25CB; R<?php } else { ?>&#x25CB;&#x25CF; Unr<?php } ?>eachable
                                    <?php } else if ($show_hint) {
                                        echo $implHint[$i];
                                    } ?>
                                </span>
                            </td>
                        <?php } ?>
                    </tr>
                <?php } ?>
            </tbody>
            </table>
        <li id="q-cc">
            <p><?php if($grading) { ?>
                <span class="<?php if (!$ccCorrect) { ?>in<?php } ?>correct"> <?php echo $ccPtsEarned . '/' . $ccPtsPossible; ?></span>
            <?php } ?>Find a canonical cover for \(\mathcal{F}\) on \(R\).</p>
            <p class="note">If the canonical cover is \(\{X\rightarrow Y, Y\rightarrow XZ\}\), type <kbd>X->Y, Y->XZ</kbd>. Spacing and order should still not matter.</p>
            <input type="text" name="q-cc" placeholder="ex. X->Y, Y->XZ" size="30"
                <?php if ($grading) { ?>value="<?php echo $ccSubAnswerRaw; ?>"<?php } ?> >
            <?php if ($grading) { ?>
                <span class="<?php if ($ccCorrect) { ?>correct<?php } else { ?>incorrect<?php } ?>">
                    <?php if ($show_answer) {
                        echo $ccCorrAnswerRaw;
                    } else if ($show_hint) {
                        echo $ccHint;
                    } ?>
                </span>
            <?php } ?>
        </li>
        <li id="q-dec">
            <p><?php if($grading) { ?>
                <span class="<?php if ($decPtsEarned < $decPtsPossible) { ?>in<?php } ?>correct">
                    <?php echo $decPtsEarned . '/' . $decPtsPossible; ?>
                </span>
            <?php } ?>For each of the following decompositions of \(R\), indicate the highest normal form of the schema and whether or not it is a lossless join and/or dependency preserving.</p>
            <table class="table">
            <thead>
                <tr>
                    <th>Decomposition</th>
                    <th>1NF</th>
                    <th>3NF</th>
                    <th>BCNF</th>
                    <th>4NF</th>
                    <th>5NF</th>
                    <?php if ($grading) { ?>
                        <th><?php if ($show_answer) { ?>Answer<?php } else { ?>Correct?<?php } ?></th>
                    <?php } ?>
                    <th>LJ</th>
                    <th>DP</th>
                    <?php if ($grading) { ?>
                        <th><?php if ($show_answer) { ?>Answer<?php } else { ?>Correct?<?php } ?></th>
                    <?php } ?>
                </tr>
            </thead>
            <tbody>
                <?php $decOpts = $_SESSION['dec-opts'];
                for ($i = 0; $i < count($decOpts); $i++) {
                    $decOpt = $decOpts[$i]; ?>
                    <tr>
                        <td>\(<?php foreach ($decOpt as $d) { $d->attrs->renderTuple(); } ?>\)</td>
                        <td><input type="radio" name="dec-<?php echo $i; ?>-hnf" value="1"
                            <?php if ($grading && $decSubAnswers[$i][0] == 1) { ?>checked<?php } ?>
                            ></td>
                        <td><input type="radio" name="dec-<?php echo $i; ?>-hnf" value="3"
                            <?php if ($grading && $decSubAnswers[$i][0] == 3) { ?>checked<?php } ?>
                            ></td>
                        <td><input type="radio" name="dec-<?php echo $i; ?>-hnf" value="3.5"
                            <?php if ($grading && $decSubAnswers[$i][0] == 3.5) { ?>checked<?php } ?>
                            ></td>
                        <td><input type="radio" name="dec-<?php echo $i; ?>-hnf" value="4"
                            <?php if ($grading && $decSubAnswers[$i][0] == 4) { ?>checked<?php } ?>
                            ></td>
                        <td><input type="radio" name="dec-<?php echo $i; ?>-hnf" value="5"
                            <?php if ($grading && $decSubAnswers[$i][0] == 5) { ?>checked<?php } ?>
                            ></td>
                        <?php if ($grading) { ?>
                            <td class="answer">
                                <span class="<?php if (!$decCorrect[$i][0]) { echo 'in'; } ?>correct">
                                    <?php if ($show_answer) {
                                        echo $decCorrAnswers[$i][0];
                                    } else if ($show_hint) {
                                        echo $decHint[$i][0];
                                    } ?>
                                </span>
                            </td>
                        <?php } ?>
                        <td><input type="checkbox" name="dec-<?php echo $i; ?>-lj"
                            <?php if ($grading && $decSubAnswers[$i][1]) { ?>checked<?php } ?>
                            ></td>
                        <td><input type="checkbox" name="dec-<?php echo $i; ?>-dp"
                            <?php if ($grading && $decSubAnswers[$i][2]) { ?>checked<?php } ?>
                            ></td>
                        <?php if ($grading) { ?>
                            <td class="answer">
                                <span class="<?php if (!$decCorrect[$i][1]) { echo 'in'; } ?>correct">
                                    <?php if ($show_answer) {
                                        echo 'LJ ';
                                        if ($decCorrAnswers[$i][1]) {
                                            echo '&#x2611;';
                                        } else {
                                            echo '&#x2610;';
                                        }
                                        echo ' DP ';
                                        if ($decCorrAnswers[$i][2]) {
                                            echo '&#x2611;';
                                        } else {
                                            echo '&#x2610;';
                                        };
                                    } else if ($show_hint) {
                                        echo $decHint[$i][1];
                                    } ?>
                                </span>
                            </td>
                        <?php } ?>
                    </tr>
                <?php } ?>
            </tbody>
            </table>
        </li>
        <li id="q-3nf">
            <p><?php if($grading) { ?>
                <span class="<?php if (!$tnfCorrect) { ?>in<?php } ?>correct">
                    <?php echo $tnfPtsEarned . '/' . $tnfPtsPossible; ?>
                </span>
            <?php } ?>Decompose \(R\) into 3NF following the algorithm in the book.</p>
            <p class="note">If the decomposition is \((X,Y)(Y,Z)\), type <kbd>XY, YZ</kbd>. Spacing and order should not matter.</p>
            <input type="text" name="q-3nf" placeholder="ex. XY, YZ"
                <?php if ($grading) { ?>value="<?php echo $tnfSubAnswerRaw; ?>"<?php } ?>
                >
            <?php if ($grading) { ?>
                <span class="<?php if (!$tnfCorrect) { ?>in<?php } ?>correct">
                    <?php if ($show_answer) {
                        echo $tnfCorrAnswerRaw;
                    } else if ($show_hint) {
                        echo $tnfHint;
                    } ?>
                </span>
            <?php } ?>
        </li>
        <li id="q-bcnf">
            <p><?php if($grading) { ?>
                <span class="<?php if (!$bcnfCorrect) { ?>in<?php } ?>correct">
                    <?php echo $bcnfPtsEarned . '/' . $bcnfPtsPossible; ?>
                </span>
            <?php } ?>Decompose \(R\) into BCNF following the algorithm in the book.</p>
            <p class="note">If the decomposition is \((X,Y)(Y,Z)\), type <kbd>XY, YZ</kbd>. Spacing and order should not matter.</p>
            <input type="text" name="q-bcnf" placeholder="ex. XY, YZ"
                <?php if ($grading) { ?>value="<?php echo $bcnfSubAnswerRaw; ?>"<?php } ?>
                >
            <?php if ($grading) { ?>
                <span class="<?php if (!$bcnfCorrect) { ?>in<?php } ?>correct">
                    <?php if ($show_answer) {
                        echo $bcnfCorrAnswerRaw;
                    } else if ($show_hint) {
                        echo $bcnfHint;
                    } ?>
                </span>
            <?php } ?>
        </li>
    </ol>
    <input type='submit' name='grade' value='Check Answers'>
    <input type='submit' name='grade' value='Check Answers & Show Hints'>
    <input type='submit' name='grade' value='Check Answers & Reveal Correct Answers'>
</form>
<label for='show-secret'>Explain Everything?</label>
<input type='checkbox' id='show-secret'>
<div class="secret">
<?php
$rel->debug();
?>
</div>
<p>
    <form action='problems.php' method='POST'>
        <input type="submit" name="reroll" value="Generate New Questions">
    </form>
</p>
<p>Curious? Bored? Check out <a href="https://github.com/mathphreak/functional-dependency-generator">the source for this problem generator</a>.</p>
</body>
</html>
