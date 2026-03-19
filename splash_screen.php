<?php
// splash_screen.php - Écran de bienvenue avec le même fond que login.php
// Redirection automatique vers login.php après 10 secondes
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bienvenue - IG-FARDC</title>
    <!-- Font Awesome local -->
    <link rel="stylesheet" href="assets/fontawesome/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Barlow', sans-serif;
            overflow: hidden;
        }

        /* Même fond que login.php */
        #splash-screen {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(0, 0, 0, 0.7) 0%, rgba(0, 0, 0, 0.7) 100%),
                url('assets/img/fardc2.png') no-repeat center center fixed;
            background-size: cover;
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-align: center;
            animation: fadeIn 0.5s ease-out;
        }

        .splash-content {
            max-width: 80%;
            padding: 20px;
            animation: slideUp 0.8s ease-out;
        }

        .splash-logo {
            width: 120px;
            height: auto;
            margin-bottom: 20px;
            border: 2px solid white;
            border-radius: 50%;
            padding: 10px;
            background: rgba(255, 255, 255, 0.2);
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.3);
            /* Animation du logo façon Gmail (pulsation discrète) */
            animation: pulse 2s infinite ease-in-out;
        }

        .splash-content h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 10px;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.5);
            letter-spacing: 0.3px;
        }

        .splash-content p {
            font-size: 1.1rem;
            opacity: 0.9;
            margin-bottom: 30px;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.5);
        }

        .splash-spinner {
            width: 50px;
            height: 50px;
            border: 4px solid rgba(255, 255, 255, 0.3);
            border-top-color: #ffc107;
            /* couleur jaune comme le bouton login */
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 20px auto;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        @keyframes slideUp {
            from {
                transform: translateY(30px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* Animation de pulsation pour le logo (inspirée de Gmail) */
        @keyframes pulse {
            0% {
                transform: scale(1);
                opacity: 1;
            }

            50% {
                transform: scale(1.05);
                opacity: 0.9;
            }

            100% {
                transform: scale(1);
                opacity: 1;
            }
        }
    </style>
</head>

<body>
    <div id="splash-screen">
        <div class="splash-content">
            <img src="assets/img/logo-fardc.png" alt="FARDC" class="splash-logo">
            <h1>IG-FARDC</h1>
            <p>Inspectorat Général des Forces Armées de la RDC</p>
            <div class="splash-spinner"></div>
            <!-- Le bouton "Passer" a été supprimé -->
        </div>
    </div>

    <script>
        // Redirection vers login.php après 10 secondes (10000 ms)
        setTimeout(function() {
            window.location.href = 'login.php';
        }, 10000);
    </script>
</body>

</html>