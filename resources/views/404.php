<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>404 - <?= APP_SHORT_NAME ?></title>

    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/output.css">
</head>

<body class="font-poppins bg-gray-100">

    <div class="min-h-screen flex items-center justify-center px-6">

        <div class="text-center">

            <h1 class="text-7xl font-bold text-primary">
                404
            </h1>

            <h2 class="mt-4 text-2xl font-semibold text-gray-800">
                Page Not Found
            </h2>

            <p class="mt-2 text-gray-500">
                The page you are looking for does not exist.
            </p>

            <a
                href="<?= BASE_URL ?>/"
                class="inline-block mt-6 px-5 py-2.5 rounded-lg bg-primary text-white font-medium hover:bg-primary-dark transition"
            >
                Back to Home
            </a>

        </div>

    </div>

</body>

</html>