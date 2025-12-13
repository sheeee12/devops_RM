<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    
    <!-- Titre Dynamique : Si la variable $pageTitle existe, on l'affiche, sinon titre par défaut -->
    <title><?php echo isset($pageTitle) ? $pageTitle : 'Gestion Frais Pro'; ?></title>

    <!-- 1. POLICE GOOGLE FONTS (Poppins) - Pour le style -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- 2. BOOTSTRAP 5 CSS (CDN) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- 3. BOOTSTRAP ICONS (Pour les petits symboles) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <!-- 4. VOTRE CSS PERSONNALISÉ (Pour surcharger Bootstrap) -->
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="bg-light d-flex flex-column min-vh-100">
    
    <!-- Le body est en flex-column pour que le footer reste toujours en bas -->
</body>
</html>