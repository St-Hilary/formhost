<?php
/**
 * The school's Angel Fund moved out of this parish app on 2026-09-13 and now
 * lives in St-Hilary/shs-web. Everything under /shs/ redirects there.
 *
 * The target is a herokuapp.com address on purpose: sainthilaryschool.org is
 * not under the school's control yet. When it is, change AF_NEW_HOME here and
 * the link on public/index.php.
 *
 * 301 because the move is permanent. These stubs stay rather than the directory
 * being deleted outright, so any link that was ever handed out lands on a
 * working form instead of a 404.
 */
const AF_NEW_HOME = 'https://shs-web-96de67e14db0.herokuapp.com/angelfund/';

header('Location: ' . AF_NEW_HOME, true, 301);
header('Cache-Control: public, max-age=3600');
?>
<!doctype html>
<meta charset="utf-8">
<title>Saint Hilary School Angel Fund has moved</title>
<p>The Saint Hilary School Angel Fund is now at
   <a href="<?= AF_NEW_HOME ?>"><?= AF_NEW_HOME ?></a>.</p>
