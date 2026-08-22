<?php

$pageTitle = $pageTitle ?? APP_SHORT_NAME;

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        <?= htmlspecialchars($pageTitle) ?> | <?= APP_SHORT_NAME ?>
    </title>

    <link
        rel="stylesheet"
        href="<?= BASE_URL ?>/public/assets/css/output.css"
    >

</head>

<body class="font-poppins bg-gray-50 text-gray-800">

    <?= $content ?? '' ?>

</body>

</html>